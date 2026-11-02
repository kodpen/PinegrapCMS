/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: stickers (built-in SVG stickers, a picture from Files) and frames.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, find = X.find, t = X.t, el = X.el, section = X.section, button = X.button, config = X.config, swatches = X.swatches;

    // System fonts only: the stage and the saved overlay draw the same
    // letters without waiting for a web font.
    var FONTS = {
        display: 'Impact, Haettenschweiler, "Arial Narrow Bold", "Franklin Gothic Bold", sans-serif',
        sans: 'system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif'
    };

    var TITLES = { burst: 'stickerBurst', check: 'stickerCheck', star: 'stickerStar', heart: 'stickerHeart' };

    var measurer = null;

    function family(kind) {
        return (kind === 'bubble') ? FONTS.sans : FONTS.display;
    }

    // The label's font in its box, measured with the font it is drawn in.
    function labelFont(l, lb) {
        var text = String(l.label || ''), weight = (l.kind === 'bubble') ? 700 : 400, em = 0.5;

        if (text) {
            if (!measurer) {
                measurer = document.createElement('canvas').getContext('2d');
            }

            measurer.font = weight + ' 100px ' + family(l.kind);
            em = measurer.measureText(text).width / 100 / text.length;
        }

        var f = M.stickerFont(l.kind, lb, em, text.length);

        f.family = family(l.kind);

        return f;
    }

    function svg(tag, attrs) {
        var n = document.createElementNS(SVGNS, tag);

        Object.keys(attrs).forEach(function (k) {
            if ((attrs[k] !== null) && (attrs[k] !== undefined)) {
                n.setAttribute(k, String(attrs[k]));
            }
        });

        return n;
    }

    // The stroke widths of a stamp's two rings.
    function ringWidths(l) {
        return [Math.min(l.w, l.h) * 0.06, Math.min(l.w, l.h) * 0.035];
    }

    // A sticker as SVG in the rotated space: its outline, its second path
    // and its label, turned around its centre.
    function stickerNode(l) {
        return stickerParts(l, svg('g', {
            transform: l.rot ? 'rotate(' + l.rot + ' ' + (l.x + l.w / 2) + ' ' + (l.y + l.h / 2) + ')' : null,
            opacity: ((l.opacity !== undefined) && (l.opacity < 1)) ? l.opacity : null
        }));
    }

    function stickerParts(l, g) {
        var s = M.stickerShape(l), rings = ringWidths(l);

        if (s.outline) {
            g.appendChild(svg('path', { d: s.d, fill: 'none', stroke: l.color, 'stroke-width': rings[0] }));
            g.appendChild(svg('path', { d: s.hd, fill: 'none', stroke: l.color, 'stroke-width': rings[1] }));
        } else {
            g.appendChild(svg('path', { d: s.d, fill: l.color, 'fill-rule': s.evenodd ? 'evenodd' : null }));

            if (s.hd) {
                g.appendChild(svg('path', { d: s.hd, fill: 'none', stroke: l.ink, 'stroke-width': s.tick, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }));
            }
        }

        if (s.lb && l.label) {
            var f = labelFont(l, s.lb), text = svg('text', {
                x: s.lb.x + s.lb.w / 2,
                y: s.lb.y + s.lb.h / 2,
                fill: l.ink,
                'font-family': f.family,
                'font-size': f.size,
                'font-weight': f.weight,
                'text-anchor': 'middle',
                'dominant-baseline': 'central'
            });

            text.textContent = l.label;
            g.appendChild(text);
        }

        return g;
    }

    // The same sticker onto the save's canvas, in rotated-space units.
    function stickerPaint(g, l) {
        var s = M.stickerShape(l), rings = ringWidths(l);

        g.translate(l.x + l.w / 2, l.y + l.h / 2);
        g.rotate((l.rot || 0) * Math.PI / 180);
        g.translate(-(l.x + l.w / 2), -(l.y + l.h / 2));

        if (s.outline) {
            g.strokeStyle = l.color;
            g.lineWidth = rings[0];
            g.stroke(new Path2D(s.d));
            g.lineWidth = rings[1];
            g.stroke(new Path2D(s.hd));
        } else {
            g.fillStyle = l.color;
            g.fill(new Path2D(s.d), s.evenodd ? 'evenodd' : 'nonzero');

            if (s.hd) {
                g.strokeStyle = l.ink;
                g.lineWidth = s.tick;
                g.lineCap = 'round';
                g.lineJoin = 'round';
                g.stroke(new Path2D(s.hd));
            }
        }

        if (s.lb && l.label) {
            var f = labelFont(l, s.lb);

            g.fillStyle = l.ink;
            g.font = f.weight + ' ' + f.size + 'px ' + f.family;
            g.textAlign = 'center';
            g.textBaseline = 'middle';
            g.fillText(l.label, s.lb.x + s.lb.w / 2, s.lb.y + s.lb.h / 2);
        }
    }

    // A small picture of a sticker for its tile, in a 96 x 56 box.
    function stickerPreview(def) {
        var k = Math.min(80 / def.w, 44 / def.h), w = def.w * k, h = def.h * k;
        var box = svg('svg', { viewBox: '0 0 96 56', 'aria-hidden': 'true', focusable: 'false' });

        box.appendChild(stickerParts({ kind: def.kind, label: stickerText(def), color: def.fill, ink: def.ink, x: (96 - w) / 2, y: (56 - h) / 2, w: w, h: h }, svg('g', {})));

        return box;
    }

    // A built-in sticker's label: a translated word, a fixed text, or none.
    function stickerText(def) {
        return def.label ? ((/^sticker/.test(def.label)) ? t(def.label) : def.label) : '';
    }

    // ---- stickers ----

    // A built-in sticker in the middle of what the stage shows, sized to
    // the picture's shorter side, selected.
    Editor.prototype.addSticker = function (def) {
        var vr = this.viewRect(), m = Math.min(vr.w, vr.h), w = def.w * m / 100, h = def.h * m / 100, c = this.stickerCentre();

        this.addLayers([{ type: 'sticker', kind: def.kind, label: stickerText(def), color: def.fill, ink: def.ink, x: c[0] - w / 2, y: c[1] - h / 2, w: w, h: h, rot: 0, opacity: 1 }], 0);
    };

    // The middle of the stage in the rotated space, kept on the picture.
    Editor.prototype.stickerCentre = function () {
        var r = this.stage.getBoundingClientRect(), vr = this.viewRect(), cover = this.panelCover();
        var p = this.toDoc(r.left + (r.width - cover) / 2, r.top + r.height / 2);

        return [M.clamp(p[0], vr.x, vr.x + vr.w), M.clamp(p[1], vr.y, vr.y + vr.h)];
    };

    // Colours and label of selected stickers: Select's panel and the
    // sticker tool's.
    Editor.prototype.stickerPanel = function (body, picked) {
        var self = this, first = picked[0], shape = M.stickerShape(first);
        // live: the colour input moving, one undo step; its closing change
        // only ends that step.
        var set = function (field, value, live) {
            if (!live && picked.every(function (o) { return self.layer(o.id) && (self.layer(o.id)[field] === value); })) {
                self.history.key = null;
                self.render();
                return;
            }

            self.change(function (doc) {
                picked.forEach(function (o) { find(doc, o.id)[field] = value; });
            }, live ? 'sticker-' + field : null, !!live);
        };

        body.appendChild(swatches(t('stickerColor'), first.color, function (c, live) { set('color', c, live); }));

        if (picked.some(function (l) { return l.kind !== 'star' && l.kind !== 'heart'; })) {
            body.appendChild(swatches(t('stickerInk'), first.ink, function (c, live) { set('ink', c, live); }));
        }

        if ((picked.length === 1) && shape.lb) {
            var input = el('input', { type: 'text', class: 'pgie-input pgie-stklabel', maxlength: '40', value: first.label || '', 'aria-label': t('stickerLabel') });

            input.addEventListener('input', function () {
                var v = input.value;

                self.change(function (doc) { find(doc, first.id).label = v; }, 'sticker-label', true);
            });
            input.addEventListener('change', function () { self.history.key = null; });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self.history.key = null;
                    self.stage.focus({ preventScroll: true });
                }
            });
            body.appendChild(section(t('stickerLabel'), input));
        }
    };

    Editor.prototype.stickerToolPanel = function (body) {
        var self = this, grid = el('div', { class: 'pgie-stk' });
        var picked = this.selected(), stickers = picked.length && picked.every(function (l) { return l.type === 'sticker'; });

        M.STICKERS.forEach(function (def) {
            var title = stickerText(def) || t(TITLES[def.kind] || 'layer_sticker'), b = el('button', { type: 'button', class: 'pgie-stb', title: title, 'aria-label': title, onclick: function () { self.addSticker(def); } });

            b.appendChild(stickerPreview(def));
            grid.appendChild(b);
        });

        body.appendChild(grid);

        if (stickers) {
            this.stickerPanel(body, picked);
        }

        if (picked.length) {
            var free = picked.filter(function (l) { return !l.locked; });

            body.appendChild(section(t('stickerDetails'), el('div', { class: 'pgie-btnrow pgie-stkacts' }, [
                button('pgie-tile', t('duplicate'), 'duplicate', function () { self.addLayers(self.selected(), 10); }, true),
                button('pgie-tile', t('bringForward'), 'forward', function () { self.change(function (doc) { doc.layers = M.reorder(doc.layers, self.sel, 'up'); }); }, true),
                button('pgie-tile', t('sendBackward'), 'backward', function () { self.change(function (doc) { doc.layers = M.reorder(doc.layers, self.sel, 'down'); }); }, true),
                free.length ? button('pgie-tile', t('delete'), 'trash', function () { self.removeLayers(); }, true) : null
            ])));
        }

        if (config().api && this.saveMode !== 'callback') {
            this.stickerFilesSection(body);
        }
    };

    // "Picture from Files": opened on demand, the newest pictures, or those
    // whose name holds the search; a press adds one as a picture layer.
    Editor.prototype.stickerFilesSection = function (body) {
        var self = this, st = this.stickerFiles, grid, find, timer = null;
        var toggle = el('button', { type: 'button', class: 'pgie-btn pgie-stkopen', 'aria-expanded': st.open ? 'true' : 'false', onclick: function () {
            st.open = !st.open;
            self.renderPanel();
        } }, [X.icon(st.open ? 'minus' : 'plus'), el('span', { text: t('stickerFiles') })]);

        if (!st.open) {
            body.appendChild(toggle);
            return;
        }

        grid = el('div', { class: 'pgie-stkfiles' });
        find = el('input', { type: 'search', class: 'pgie-input pgie-stkfind', value: st.search, placeholder: t('stickerFilesFind'), 'aria-label': t('stickerFilesFind') });
        find.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { self.loadStickerFiles(find.value); }, 350);
        });

        if (!st.list && !st.loading && !st.error) {
            this.loadStickerFiles(st.search);
        }

        if (st.error) {
            grid.appendChild(el('p', { class: 'pgie-note', text: t('stickerFilesError') }));
        } else if (st.list && !st.list.length) {
            grid.appendChild(el('p', { class: 'pgie-note', text: t('stickerFilesNone') }));
        }

        (st.list || []).forEach(function (f) {
            grid.appendChild(el('button', { type: 'button', class: 'pgie-stkfile', title: f.name, 'aria-label': f.name, onclick: function () { self.addFilePicture(f); } }, [
                el('img', { src: f.url, alt: '', loading: 'lazy', decoding: 'async', draggable: 'false' })
            ]));
        });

        body.appendChild(el('div', { class: 'pgie-stkfilebox' }, [toggle, find, grid]));
    };

    Editor.prototype.loadStickerFiles = function (search) {
        var self = this, st = this.stickerFiles, ask = ++st.serial;

        st.search = search || '';
        st.loading = true;
        this.request({ action: 'sticker_files', search: st.search }).then(function (r) {
            if (ask !== st.serial) {
                return;
            }

            st.loading = false;
            st.error = !r || (r.status !== 'success');
            st.list = st.error ? null : (r.files || []);
            self.refreshStickerFiles();
        }, function () {
            if (ask === st.serial) {
                st.loading = false;
                st.error = true;
                self.refreshStickerFiles();
            }
        });
    };

    // The answer redraws the panel, keeping the search box's focus.
    Editor.prototype.refreshStickerFiles = function () {
        if (this.finished || ((this.tool !== 'sticker') && (this.tool !== 'image'))) {
            return;
        }

        var focused = document.activeElement && document.activeElement.classList && document.activeElement.classList.contains('pgie-stkfind');
        var scroll = this.panel ? this.panel.scrollTop : 0;

        this.renderPanel();

        if (this.panel) {
            this.panel.scrollTop = scroll;
        }

        if (focused) {
            var box = this.panel && this.panel.querySelector('.pgie-stkfind');

            if (box) {
                box.focus();
                box.setSelectionRange(box.value.length, box.value.length);
            }
        }
    };

    // A picture from Files as a layer in the middle of the stage, at most
    // 40 % of the picture; it keeps the file's address and id.
    Editor.prototype.addFilePicture = function (f) {
        var self = this;

        this.picture(f.url).then(function (img) {
            var vr = self.viewRect(), w = img.naturalWidth || f.width || 1, h = img.naturalHeight || f.height || 1;
            var fit = Math.min(1, 0.4 * vr.w / w, 0.4 * vr.h / h), lw = w * fit, lh = h * fit, c = self.stickerCentre();

            self.addLayers([{ type: 'image', src: f.url, fileId: f.id, x: c[0] - lw / 2, y: c[1] - lh / 2, w: lw, h: lh, rot: 0, opacity: 1 }], 0);
        }, function () {
            self.showMessage(t('stickerFilesError'), true);
        });
    };

    // A double click on a sticker with a label: its text field.
    function editSticker(l) {
        if (!M.stickerShape(l).lb) {
            return;
        }

        this.sheetOpen = true;
        this.render();

        var input = this.panel && this.panel.querySelector('.pgie-stklabel');

        if (input) {
            input.focus();
            input.select();
        }
    }

    // ---- frames ----

    // The frame over the stage, on the edge of what is saved; hidden while
    // cropping. An artboard's fill follows its document too (undo).
    Editor.prototype.renderFrame = function () {
        var a = this.doc.artboard, vr = this.viewRect();

        if (a) {
            this.frame.style.background = (a.background === 'transparent') ? '' : a.background;
            this.frame.classList.toggle('pgie-checker', a.background === 'transparent');
        }

        if (!this.frameSvg) {
            this.frameSvg = svg('svg', { class: 'pgie-frameov', 'aria-hidden': 'true' });
            this.framePath = svg('path', { 'fill-rule': 'evenodd' });
            this.frameSvg.appendChild(this.framePath);
            this.frame.appendChild(this.frameSvg);
        }

        var shape = this.cropping() ? null : M.frameShape(this.doc.frame, vr);

        this.frameSvg.setAttribute('viewBox', [vr.x, vr.y, vr.w, vr.h].join(' '));
        this.framePath.setAttribute('d', shape ? shape.d : '');
        this.framePath.setAttribute('fill', shape ? this.doc.frame.color : 'none');
    };

    // The frame onto a canvas that draws box at w x h.
    Editor.prototype.paintFrame = function (g, box, w, h) {
        var shape = M.frameShape(this.doc.frame, box), sx = w / box.w, sy = h / box.h;

        if (!shape) {
            return;
        }

        g.save();
        g.setTransform(sx, 0, 0, sy, -box.x * sx, -box.y * sy);
        g.fillStyle = this.doc.frame.color;
        g.fill(new Path2D(shape.d), 'evenodd');
        g.restore();
    };

    // A save with a frame draws it over the layers: into the overlay of a
    // picture, onto an artboard's PNG (a new one, or a canvas saved again).
    function frameSave(body, mode) {
        if (!M.frameShape(this.doc.frame, { x: 0, y: 0, w: 100, h: 100 })) {
            return;
        }

        var canvas = document.createElement('canvas'), g, a = this.doc.artboard, box, out;

        if (a) {
            box = { x: 0, y: 0, w: a.width, h: a.height };
            out = { w: a.width, h: a.height };
        } else {
            box = M.saveBox(this.doc);
            out = M.saveSize(this.doc);
        }

        canvas.width = out.w;
        canvas.height = out.h;
        g = canvas.getContext('2d');

        if (a && a.background && (a.background !== 'transparent')) {
            g.fillStyle = a.background;
            g.fillRect(0, 0, out.w, out.h);
        }

        this.paintLayers(g, box, out.w, out.h);
        this.paintFrame(g, box, out.w, out.h);

        if (a) {
            body.picture = canvas.toDataURL('image/png');
        } else {
            body.overlay = canvas.toDataURL('image/png');
        }
    }

    Editor.prototype.setFrame = function (patch, key) {
        this.change(function (doc) {
            var now = doc.frame || { kind: 'none', color: '#ffffff', width: 4 }, next = {
                kind: (patch.kind !== undefined) ? patch.kind : now.kind,
                color: (patch.color !== undefined) ? patch.color : now.color,
                width: (patch.width !== undefined) ? patch.width : now.width
            };

            // Outside the picture, growing the canvas (M.frameOutward()).
            if ((patch.outside !== undefined) ? patch.outside : now.outside) {
                next.outside = true;
            }

            doc.frame = (next.kind === 'none') ? null : next;
        }, key || null, !!key);
    };

    Editor.prototype.framePanel = function (body) {
        var self = this, fr = this.doc.frame, kind = fr ? fr.kind : 'none', tiles = el('div', { class: 'pgie-frames' });

        M.FRAMES.forEach(function (name) {
            var on = (kind === name), label = t('frame' + name.charAt(0).toUpperCase() + name.slice(1));
            var pic = svg('svg', { viewBox: '0 0 56 40', 'aria-hidden': 'true', focusable: 'false' });
            var shape = M.frameShape({ kind: name, width: 4 }, { x: 4, y: 4, w: 48, h: 32 });

            pic.appendChild(svg('rect', { x: 4, y: 4, width: 48, height: 32, rx: 2, class: 'pgie-frpic' }));

            if (shape) {
                pic.appendChild(svg('path', { d: shape.d, 'fill-rule': 'evenodd', class: 'pgie-frink' }));
            } else {
                pic.appendChild(svg('rect', { x: 4.5, y: 4.5, width: 47, height: 31, rx: 2, class: 'pgie-frnone' }));
            }

            tiles.appendChild(el('button', {
                type: 'button',
                class: 'pgie-tile' + (on ? ' on' : ''),
                'aria-pressed': on ? 'true' : 'false',
                onclick: function () {
                    // On a picture the polaroid lies outside it, as a print's.
                    self.setFrame({ kind: name, color: (name === 'polaroid') ? '#ffffff' : (fr ? fr.color : '#ffffff'),
                        outside: (name === 'polaroid') ? !!self.doc.source : (fr ? !!fr.outside : false) });
                }
            }, [pic, el('span', { text: label })]));
        });

        body.appendChild(tiles);

        if (fr) {
            var value = el('span', { class: 'pgie-val', text: String(fr.width) });
            var slider = el('input', { type: 'range', class: 'pgie-range', min: '1', max: '10', step: '1', value: String(fr.width), 'aria-label': t('frameWidth') });

            slider.addEventListener('input', function () {
                value.textContent = slider.value;
                self.setFrame({ width: Number(slider.value) }, 'frame-width');
            });
            slider.addEventListener('change', function () { self.history.key = null; });

            body.appendChild(swatches(t('frameColor'), fr.color, function (c, live) {
                if (live || (c !== fr.color)) {
                    self.setFrame({ color: c }, live ? 'frame-color' : null);
                } else {
                    self.history.key = null;
                    self.render();
                }
            }));
            body.appendChild(section(t('frameWidth'), slider, value));
        }

        if (this.doc.artboard) {
            var fill = this.doc.artboard.background;

            body.appendChild(swatches(t('frameBackground'), fill, function (c, live) {
                if (live || (c !== fill)) {
                    self.change(function (doc) { doc.artboard.background = c; }, live ? 'artboard-background' : null, !!live);
                } else {
                    self.history.key = null;
                    self.render();
                }
            }, { transparent: true }));
        }
    };

    Editor.inits.push(function () {
        this.stickerFiles = { open: false, search: '', list: null, loading: false, error: false, serial: 0 };
        this.frameSvg = null;
    });

    Editor.layerTypes.sticker = {
        node: stickerNode,
        paint: stickerPaint,
        ratio: true,
        name: function (l) { return t('layer_sticker') + (l.label ? ' · ' + l.label : ''); },
        edit: editSticker,
        panel: function (body, layers) { this.stickerPanel(body, layers); }
    };

    Editor.selectTools.push('sticker');
    Editor.panels.sticker = Editor.prototype.stickerToolPanel;
    Editor.hints.sticker = 'hintSticker';
    Editor.pointers.sticker = Editor.pointers.select;

    Editor.panels.frame = Editor.prototype.framePanel;
    Editor.hints.frame = 'hintFrame';
    Editor.headers.frame = function () {
        var self = this;

        return this.doc.frame ? el('button', { type: 'button', class: 'pgie-link', text: t('reset'), onclick: function () { self.setFrame({ kind: 'none' }); } }) : null;
    };

    Editor.views.push(Editor.prototype.renderFrame);
    Editor.saveHooks.push(frameSave);
}(window.PgImageEditor.parts));
