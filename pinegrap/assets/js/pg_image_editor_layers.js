/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: layers and the Select tool: drawing the layers, the selection
 * and its handles, snapping, the layers panel, the keys, pasting, and the
 * overlay a save draws.
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

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, find = X.find, t = X.t, el = X.el, section = X.section, button = X.button;

    // The eight handles of a box and the turning handle. The corners come
    // after the edges, so on a small box their touch targets lie on top.
    var BOX_HANDLES = ['n', 'e', 's', 'w', 'nw', 'ne', 'se', 'sw', 'rot'];

    // What a layer's type adds (Editor.layerTypes, the list in
    // pg_image_editor.js); an empty object for a type no tool file knows.
    function kind(l) {
        return Editor.layerTypes[l.type] || {};
    }

    // A press inside a layer's box hits it unless its type's own test says
    // no (a line is thinner than its box).
    function layerHit(l, p, slack) {
        var type = kind(l);

        return !type.hit || type.hit(l, p, slack);
    }

    Editor.prototype.layer = function (id) {
        return find(this.doc, id);
    };

    Editor.prototype.selected = function () {
        var self = this;

        return this.doc.layers.filter(function (l) { return self.sel.indexOf(l.id) >= 0; });
    };

    Editor.prototype.select = function (ids) {
        this.sel = ids;
        this.render();
    };

    // After an undo, only ids still in the document stay selected.
    Editor.prototype.keepSelection = function () {
        var self = this;

        this.sel = this.sel.filter(function (id) { return !!self.layer(id); });
    };

    // A picture by address, loaded once, for the overlay and the artboard.
    Editor.prototype.picture = function (src) {
        var self = this;

        if (!this.pictures[src]) {
            this.pictures[src] = new Promise(function (resolve, reject) {
                var img = new Image();

                img.onload = function () {
                    self.pictures[src] = img;
                    resolve(img);
                };
                img.onerror = reject;
                img.src = src;
            });
        }

        return Promise.resolve(this.pictures[src]);
    };

    Editor.prototype.renderLayers = function () {
        var self = this, svg = this.layersSvg;

        if (!this.layersDirty) {
            return;
        }

        this.layersDirty = false;

        while (svg.firstChild) {
            svg.removeChild(svg.firstChild);
        }

        this.doc.layers.forEach(function (l) {
            var n = l.hidden ? null : self.layerNode(l);

            if (n) {
                svg.appendChild(n);
            }
        });
    };

    // One layer as SVG in the rotated space, drawn by its type
    // (Editor.layerTypes); null for a type no tool file knows.
    Editor.prototype.layerNode = function (l) {
        var type = kind(l);

        return type.node ? type.node.call(this, l) : null;
    };

    // A picture layer: the picture in its box, turned around its centre.
    function pictureNode(l) {
        var n = document.createElementNS(SVGNS, 'image');

        n.setAttribute('href', l.src);
        n.setAttribute('x', l.x);
        n.setAttribute('y', l.y);
        n.setAttribute('width', l.w);
        n.setAttribute('height', l.h);
        n.setAttribute('preserveAspectRatio', 'none');

        if (l.rot) {
            n.setAttribute('transform', 'rotate(' + l.rot + ' ' + (l.x + l.w / 2) + ' ' + (l.y + l.h / 2) + ')');
        }

        if ((l.opacity !== undefined) && (l.opacity < 1)) {
            n.setAttribute('opacity', l.opacity);
        }

        return n;
    }

    function picturePaint(g, l) {
        var img = this.pictures[l.src];

        if (img && img.complete) {
            g.translate(l.x + l.w / 2, l.y + l.h / 2);
            g.rotate((l.rot || 0) * Math.PI / 180);
            g.drawImage(img, -l.w / 2, -l.h / 2, l.w, l.h);
        }
    }

    // The outlines of the selected layers on the stage; one unlocked layer
    // gets eight handles and the turning handle; guides while a move snaps.
    Editor.prototype.renderSelection = function () {
        var self = this, box = this.selEl, v = this.view, vr = this.viewRect(), k = v.k, picked = this.selected();

        box.textContent = '';

        if (!this.ready || this.cropping() || (Editor.selectTools.indexOf(this.tool) < 0)) {
            return;
        }

        picked.forEach(function (l) {
            var d = el('div', { class: 'pgie-selbox' + (l.locked ? ' pgie-locked' : '') }), type = kind(l);

            d.style.left = (v.x + (l.x - vr.x) * k) + 'px';
            d.style.top = (v.y + (l.y - vr.y) * k) + 'px';
            d.style.width = (l.w * k) + 'px';
            d.style.height = (l.h * k) + 'px';
            d.style.transform = l.rot ? 'rotate(' + l.rot + 'deg)' : '';

            if ((picked.length === 1) && !l.locked) {
                ((type.box === false) ? [] : BOX_HANDLES).forEach(function (h) {
                    d.appendChild(el('span', { class: 'pgie-sh', 'data-h': h, title: (h === 'rot') ? t('rotate') : null }));
                });

                // A type's own handles, placed in the layer's frame from the
                // box's corner; they turn with the box.
                (type.handles ? type.handles.call(self, l) : []).forEach(function (m) {
                    var s = el('span', { class: 'pgie-sh pgie-shx', 'data-h': m.h, title: m.title || null });

                    s.style.left = (m.x * k) + 'px';
                    s.style.top = (m.y * k) + 'px';
                    d.appendChild(s);
                });
            }

            box.appendChild(d);
        });

        (this.guides ? this.guides.gx : []).forEach(function (x) {
            box.appendChild(el('div', { class: 'pgie-guide pgie-gx', style: 'left: ' + (v.x + (x - vr.x) * k) + 'px' }));
        });

        (this.guides ? this.guides.gy : []).forEach(function (y) {
            box.appendChild(el('div', { class: 'pgie-guide pgie-gy', style: 'top: ' + (v.y + (y - vr.y) * k) + 'px' }));
        });
    };

    // A press with the Select tool: a handle of the one selected layer
    // resizes or turns it; a layer is selected (Shift adds or removes it)
    // and moved with the rest of the selection; the empty stage clears it.
    Editor.prototype.startDrag = function (e) {
        var p = this.toDoc(e.clientX, e.clientY), h = e.target.getAttribute && e.target.getAttribute('data-h');
        var one = (this.sel.length === 1) ? this.layer(this.sel[0]) : null, hit = null;

        // On touch a box handle's 44 px target covers a small layer's middle:
        // a press well inside the box moves the layer instead of resizing it.
        if (h && one && (h !== 'rot') && (BOX_HANDLES.indexOf(h) >= 0) && M.insideBox(one, p[0], p[1], 10 / this.view.k)) {
            h = null;
            hit = one;
        }

        if (h && one) {
            this.drag = { kind: (h === 'rot') ? 'rotate' : ((BOX_HANDLES.indexOf(h) >= 0) ? 'resize' : 'handle'), h: h, start: M.clone(one) };
        } else {
            hit = hit || M.hitLayer(this.doc, p[0], p[1], 4 / this.view.k, layerHit);

            if (!hit) {
                if (this.sel.length && !e.shiftKey) {
                    this.select([]);
                }

                return;
            }

            if (e.shiftKey) {
                this.select((this.sel.indexOf(hit.id) >= 0) ? this.sel.filter(function (id) { return id !== hit.id; }) : this.sel.concat([hit.id]));
                return;
            }

            if (this.sel.indexOf(hit.id) < 0) {
                this.select([hit.id]);
            }

            this.drag = { kind: 'move', at: p, start: this.selected().filter(function (l) { return !l.locked; }).map(M.clone) };
        }

        e.preventDefault();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }
    };

    Editor.prototype.moveDrag = function (e) {
        var d = this.drag, p = this.toDoc(e.clientX, e.clientY), k = this.view.k;

        if (d.kind === 'move') {
            var dx = p[0] - d.at[0], dy = p[1] - d.at[1], moved = M.bounds(d.start);

            this.guides = null;

            // Alt moves freely; else an edge or the middle snaps to the
            // canvas and the other layers.
            if (moved && !e.altKey) {
                var lines = this.snapLines(), s = M.snapBox({ x: moved.x + dx, y: moved.y + dy, w: moved.w, h: moved.h }, lines.x, lines.y, 6 / k);

                dx += s.dx;
                dy += s.dy;
                this.guides = (s.gx.length || s.gy.length) ? s : null;
            }

            this.change(function (doc) {
                d.start.forEach(function (o) {
                    var l = find(doc, o.id);

                    l.x = o.x + dx;
                    l.y = o.y + dy;
                });
            }, 'drag', true);
        } else if (d.kind === 'resize') {
            // A type with ratio (a picture) keeps its shape from a corner;
            // Shift does the other.
            // A type's resize(start, box, handle) answers the fields that
            // change (a text scales its letters).
            var b = M.resizeBox(d.start, d.h, p[0], p[1], e.shiftKey !== !!kind(d.start).ratio, 4 / k);

            if (kind(d.start).resize) {
                b = kind(d.start).resize.call(this, d.start, b, d.h);
            }

            this.change(function (doc) {
                var l = find(doc, d.start.id);

                Object.keys(b).forEach(function (key) { l[key] = b[key]; });
            }, 'drag', true);
        } else if (d.kind === 'handle') {
            // A type's own handle: its drag() names the fields it changes.
            var fields = kind(d.start).drag ? kind(d.start).drag.call(this, d.start, d.h, p[0], p[1], e) : null;

            this.change(function (doc) {
                var l = find(doc, d.start.id);

                Object.keys(fields || {}).forEach(function (key) { l[key] = fields[key]; });
            }, 'drag', true);
        } else {
            var a = M.angleTo(d.start, p[0], p[1], e.shiftKey);

            this.change(function (doc) { find(doc, d.start.id).rot = a; }, 'drag', true);
        }
    };

    // A double click on a layer whose type edits in place (a text) opens it
    // there; true when it did, else the view zooms.
    Editor.prototype.editLayer = function (e) {
        var p = this.toDoc(e.clientX, e.clientY), hit = M.hitLayer(this.doc, p[0], p[1], 4 / this.view.k, layerHit);

        if (!hit || !kind(hit).edit) {
            return false;
        }

        this.select([hit.id]);
        kind(hit).edit.call(this, hit);

        return true;
    };

    Editor.prototype.endDrag = function () {
        if (!this.drag) {
            return;
        }

        this.drag = null;
        this.guides = null;
        this.history.key = null;
        this.render();
    };

    // What a moved layer snaps to: the canvas's edges and middle, and the
    // edges and middles of the other visible layers.
    Editor.prototype.snapLines = function () {
        var self = this, vr = this.viewRect(), xs = [vr.x, vr.x + vr.w / 2, vr.x + vr.w], ys = [vr.y, vr.y + vr.h / 2, vr.y + vr.h];

        this.doc.layers.forEach(function (l) {
            if (!l.hidden && (self.sel.indexOf(l.id) < 0)) {
                var b = M.bounds([l]);

                xs.push(b.x, b.x + b.w / 2, b.x + b.w);
                ys.push(b.y, b.y + b.h / 2, b.y + b.h);
            }
        });

        return { x: xs, y: ys };
    };

    // The keys of the selection: arrows nudge (Shift: 10), Delete removes,
    // Ctrl+D duplicates, Ctrl+C copies, Ctrl+A selects all. True when used.
    Editor.prototype.layerKey = function (e, mod, k) {
        var ids = this.sel, step = e.shiftKey ? 10 : 1, free = this.selected().filter(function (l) { return !l.locked; });

        if (Editor.selectTools.indexOf(this.tool) < 0) {
            return false;
        }

        if (mod && (k === 'a' || k === 'A')) {
            this.select(this.doc.layers.map(function (l) { return l.id; }));
            return true;
        }

        if (!ids.length) {
            return false;
        }

        if (mod && (k === 'c' || k === 'C')) {
            this.clip = this.selected().map(M.clone);
            return true;
        }

        if (mod && (k === 'd' || k === 'D')) {
            this.addLayers(this.selected(), 10);
            return true;
        }

        if (!mod && (k === 'Delete' || k === 'Backspace') && free.length) {
            this.removeLayers();
            return true;
        }

        var dir = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[k];

        if (!mod && dir && free.length) {
            this.change(function (doc) {
                free.forEach(function (o) {
                    var l = find(doc, o.id);

                    l.x += dir[0] * step;
                    l.y += dir[1] * step;
                });
            }, 'nudge');
            return true;
        }

        return false;
    };

    // Copies of layers with new ids, moved by offset, on top and selected.
    Editor.prototype.addLayers = function (list, offset) {
        var added = [];

        this.change(function (doc) {
            list.forEach(function (o) {
                var l = M.clone(o);

                l.id = M.newId(doc);
                l.x += offset;
                l.y += offset;
                doc.layers.push(l);
                added.push(l.id);
            });
        });
        this.select(added);
    };

    Editor.prototype.removeLayers = function () {
        var ids = this.selected().filter(function (l) { return !l.locked; }).map(function (l) { return l.id; });

        this.change(function (doc) {
            doc.layers = doc.layers.filter(function (l) { return ids.indexOf(l.id) < 0; });
        });
        this.select(this.sel.filter(function (id) { return ids.indexOf(id) < 0; }));
    };

    // A picture pasted from the clipboard becomes a layer; else layers
    // copied with Ctrl+C come back. Not into a text field.
    Editor.prototype.onPaste = function (e) {
        var items = (e.clipboardData && e.clipboardData.items) || [], file = null, i;

        if (!this.ready || this.saving || this.confirmBox || /^(INPUT|TEXTAREA)$/.test(e.target.tagName || '')) {
            return;
        }

        for (i = 0; i < items.length; i++) {
            if ((items[i].kind === 'file') && /^image\//.test(items[i].type)) {
                file = items[i].getAsFile();
            }
        }

        if (file) {
            e.preventDefault();
            this.addPicture(file);
        } else if (this.clip && this.clip.length) {
            e.preventDefault();
            this.toSelect();
            this.addLayers(this.clip, 10);
        }
    };

    // The Select tool, keeping a crop being drawn.
    Editor.prototype.toSelect = function () {
        if (this.cropping()) {
            this.applyCrop('select');
        } else {
            this.tool = 'select';
        }
    };

    // A picture file as a layer in the middle of the canvas, at most 60 % of
    // it; one larger than 2000 px is kept at that size.
    Editor.prototype.addPicture = function (file) {
        var self = this, reader = new FileReader();

        reader.onload = function () {
            var img = new Image();

            img.onload = function () {
                var src = String(reader.result), w = img.naturalWidth, h = img.naturalHeight, k = Math.min(1, 2000 / Math.max(w, h));

                if (k < 1) {
                    var canvas = document.createElement('canvas');

                    canvas.width = Math.round(w * k);
                    canvas.height = Math.round(h * k);
                    canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                    src = canvas.toDataURL('image/png');
                }

                self.picture(src).then(function () {
                    var vr = self.viewRect(), fit = Math.min(1, 0.6 * vr.w / w, 0.6 * vr.h / h), lw = w * fit, lh = h * fit;

                    self.toSelect();
                    self.addLayers([{ type: 'image', src: src, x: vr.x + (vr.w - lw) / 2, y: vr.y + (vr.h - lh) / 2, w: lw, h: lh, rot: 0, opacity: 1 }], 0);
                });
            };
            img.src = String(reader.result);
        };
        reader.readAsDataURL(file);
    };

    // The layers list's name: the type's own (a text's words), else its
    // label, numbered when there are more of the type.
    Editor.prototype.layerName = function (l) {
        var same = this.doc.layers.filter(function (o) { return o.type === l.type; }), own = kind(l).name ? kind(l).name.call(this, l) : '';

        return own || (t('layer_' + l.type) + ((same.length > 1) ? ' ' + (same.indexOf(l) + 1) : ''));
    };

    // The Select tool's panel: the selection's arrange, align, opacity,
    // duplicate and delete; then every layer, the top one first.
    Editor.prototype.layersPanel = function (body) {
        var self = this, picked = this.selected(), free = picked.filter(function (l) { return !l.locked; });
        var row = function (items) { return el('div', { class: 'pgie-btnrow' }, items); };
        var act = function (name, label, fn, off) {
            var b = button('pgie-ibtn', label, name, fn);

            b.disabled = !!off;

            return b;
        };

        if (!this.doc.layers.length) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('noLayers') }));
            return;
        }

        if (picked.length) {
            var arrange = function (how) {
                self.change(function (doc) { doc.layers = M.reorder(doc.layers, self.sel, how); });
            };
            var align = function (how) {
                var target = (picked.length > 1) ? M.bounds(free) : self.viewRect(), moves = M.align(free.map(function (l) { return M.bounds([l]); }), how, target);

                self.change(function (doc) {
                    free.forEach(function (o, i) {
                        var l = find(doc, o.id);

                        l.x += moves[i][0];
                        l.y += moves[i][1];
                    });
                });
            };
            var spread = function (axis) {
                var moves = M.distribute(free.map(function (l) { return M.bounds([l]); }), axis);

                self.change(function (doc) {
                    free.forEach(function (o, i) {
                        var l = find(doc, o.id);

                        l.x += moves[i][0];
                        l.y += moves[i][1];
                    });
                });
            };
            var opacity = Math.round(100 * ((picked[0].opacity === undefined) ? 1 : picked[0].opacity));
            var value = el('span', { class: 'pgie-val', text: opacity + ' %' });
            var slider = el('input', { type: 'range', class: 'pgie-range', min: '0', max: '100', step: '1', value: String(opacity), 'aria-label': t('opacity') });

            slider.addEventListener('input', function () {
                var n = Number(slider.value);

                value.textContent = n + ' %';
                self.change(function (doc) {
                    picked.forEach(function (o) { find(doc, o.id).opacity = n / 100; });
                }, 'opacity', true);
            });
            slider.addEventListener('change', function () { self.history.key = null; });

            // Layers of one type get that type's own controls first (a
            // shape's stroke and fill, a text's font).
            var own = kind(picked[0]);

            if (own.panel && picked.every(function (l) { return l.type === picked[0].type; })) {
                own.panel.call(self, body, picked);
            }

            body.appendChild(section(t('arrange'), row([
                act('front', t('bringFront'), function () { arrange('top'); }),
                act('forward', t('bringForward'), function () { arrange('up'); }),
                act('backward', t('sendBackward'), function () { arrange('down'); }),
                act('back', t('sendBack'), function () { arrange('bottom'); })
            ])));
            body.appendChild(section(t('align'), row([
                act('alignleft', t('alignLeft'), function () { align('left'); }, !free.length),
                act('aligncenter', t('alignCenter'), function () { align('center'); }, !free.length),
                act('alignright', t('alignRight'), function () { align('right'); }, !free.length),
                act('aligntop', t('alignTop'), function () { align('top'); }, !free.length),
                act('alignmiddle', t('alignMiddle'), function () { align('middle'); }, !free.length),
                act('alignbottom', t('alignBottom'), function () { align('bottom'); }, !free.length),
                act('distx', t('distributeX'), function () { spread('x'); }, free.length < 3),
                act('disty', t('distributeY'), function () { spread('y'); }, free.length < 3)
            ])));
            body.appendChild(section(t('opacity'), slider, value));
            body.appendChild(row([
                act('duplicate', t('duplicate'), function () { self.addLayers(self.selected(), 10); }),
                act('trash', t('delete'), function () { self.removeLayers(); }, !free.length)
            ]));
        }

        var list = el('ul', { class: 'pgie-layerlist', 'aria-label': t('layers') });

        this.doc.layers.slice().reverse().forEach(function (l) {
            var on = (self.sel.indexOf(l.id) >= 0);
            var flag = function (key, label, iconName) {
                return button('pgie-ibtn sm', label, iconName, function () {
                    self.change(function (doc) {
                        var x = find(doc, l.id);

                        x[key] = !x[key];
                    });
                });
            };

            list.appendChild(el('li', { class: 'pgie-lrow' + (on ? ' on' : '') + (l.hidden ? ' off' : '') }, [
                el('button', { type: 'button', class: 'pgie-lname', 'aria-pressed': on ? 'true' : 'false', text: self.layerName(l), onclick: function (e) {
                    self.select(e.shiftKey ? ((on ? self.sel.filter(function (id) { return id !== l.id; }) : self.sel.concat([l.id]))) : [l.id]);
                } }),
                flag('hidden', l.hidden ? t('show') : t('hide'), l.hidden ? 'eyeoff' : 'eye'),
                flag('locked', l.locked ? t('unlock') : t('lock'), l.locked ? 'lock' : 'unlock')
            ]));
        });

        body.appendChild(list);
    };

    // The layers over a picture, drawn at its output size into a clear PNG
    // the server lays over the pixels it makes; null without layers.
    Editor.prototype.drawOverlay = function () {
        if (!this.doc.layers.some(function (l) { return !l.hidden; })) {
            return null;
        }

        var out = M.outputSize(this.doc), canvas = document.createElement('canvas');

        canvas.width = out.w;
        canvas.height = out.h;
        this.paintLayers(canvas.getContext('2d'), M.cropBox(this.doc), out.w, out.h);

        return canvas.toDataURL('image/png');
    };

    // Each visible layer onto g, the box of the rotated space drawn at w x h:
    // its type paints it in rotated-space units, its opacity set.
    Editor.prototype.paintLayers = function (g, box, w, h) {
        var self = this, sx = w / box.w, sy = h / box.h;

        this.doc.layers.forEach(function (l) {
            var type = kind(l);

            if (l.hidden || !type.paint) {
                return;
            }

            g.save();
            g.setTransform(sx, 0, 0, sy, -box.x * sx, -box.y * sy);
            g.globalAlpha = (l.opacity === undefined) ? 1 : l.opacity;
            type.paint.call(self, g, l);
            g.restore();
        });
    };

    // What the visible layers need for painting (pictures, fonts), loaded.
    Editor.prototype.layersReady = function () {
        var self = this;

        return Promise.all(this.doc.layers.map(function (l) {
            return (!l.hidden && kind(l).ready) ? kind(l).ready.call(self, l) : null;
        }));
    };

    Editor.layerTypes.image = {
        node: pictureNode,
        paint: picturePaint,
        ratio: true,
        ready: function (l) { return this.picture(l.src); }
    };
    Editor.selectTools.push('select');
    Editor.panels.select = Editor.prototype.layersPanel;
    Editor.hints.select = 'hintSelect';
    Editor.pointers.select = { down: Editor.prototype.startDrag, move: Editor.prototype.moveDrag, up: Editor.prototype.endDrag, double: Editor.prototype.editLayer };
    Editor.keys.push(Editor.prototype.layerKey);
    Editor.views.push(Editor.prototype.renderLayers, Editor.prototype.renderSelection);
}(window.PgImageEditor.parts));
