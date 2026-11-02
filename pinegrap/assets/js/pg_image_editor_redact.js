/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: redaction: areas pixelated, blurred or covered, over a box
 * or along a brush stroke.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * The stage shows each area as SVG over the picture; a save sends one mask
 * per area, drawn at the output size, and the server burns the redaction
 * into the pixels (step 4, before the overlay), so nothing of the original
 * shows through a preview's rounding. A redaction layer paints nothing into
 * the overlay.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, find = X.find, t = X.t, el = X.el, button = X.button;

    // The solid box's colour, as the server's pg_image_edit_redact_solid().
    var SOLID = '#111317';
    var MODE_LABELS = { pixel: 'redactPixel', blur: 'redactBlur', solid: 'redactSolid' };
    var serial = 0;

    function svg(tag, attrs) {
        var n = document.createElementNS(SVGNS, tag);

        Object.keys(attrs).forEach(function (k) {
            n.setAttribute(k, String(attrs[k]));
        });

        return n;
    }

    function isRedact(l) {
        return l.type === 'redact';
    }

    // The area's shape filled white: a turned box, or the brush's stroke.
    function areaShape(l) {
        if (l.shape === 'brush') {
            var pts = M.brushPoints(l);

            return svg('path', {
                d: 'M' + pts.map(function (p) { return p[0] + ' ' + p[1]; }).join('L'),
                fill: 'none', stroke: '#fff', 'stroke-width': Math.max(1, l.size || 1), 'stroke-linecap': 'round', 'stroke-linejoin': 'round'
            });
        }

        var r = svg('rect', { x: l.x, y: l.y, width: Math.max(1, l.w), height: Math.max(1, l.h), fill: '#fff' });

        if (l.rot) {
            r.setAttribute('transform', 'rotate(' + l.rot + ' ' + (l.x + l.w / 2) + ' ' + (l.y + l.h / 2) + ')');
        }

        return r;
    }

    // The same shape onto a canvas, in the turned space's units.
    function paintShape(g, l) {
        g.fillStyle = '#fff';
        g.strokeStyle = '#fff';

        if (l.shape === 'brush') {
            var pts = M.brushPoints(l);

            g.lineWidth = Math.max(1, l.size || 1);
            g.lineCap = 'round';
            g.lineJoin = 'round';
            g.beginPath();
            pts.forEach(function (p, i) {
                if (i) {
                    g.lineTo(p[0], p[1]);
                } else {
                    g.moveTo(p[0], p[1]);
                }
            });

            // One point is a dot.
            if (pts.length === 1) {
                g.lineTo(pts[0][0] + 0.01, pts[0][1]);
            }

            g.stroke();
            return;
        }

        g.translate(l.x + l.w / 2, l.y + l.h / 2);
        g.rotate((l.rot || 0) * Math.PI / 180);
        g.fillRect(-l.w / 2, -l.h / 2, Math.max(1, l.w), Math.max(1, l.h));
    }

    // The area's upright box in the turned space, a brush grown by half its
    // width.
    function areaBox(l) {
        var grow = (l.shape === 'brush') ? (l.size || 0) / 2 : 0, pts = (l.shape === 'brush') ? M.brushPoints(l) : M.corners(l);
        var xs = pts.map(function (p) { return p[0]; }), ys = pts.map(function (p) { return p[1]; });
        var x0 = Math.min.apply(null, xs) - grow, y0 = Math.min.apply(null, ys) - grow;

        return { x: x0, y: y0, w: Math.max.apply(null, xs) + grow - x0, h: Math.max.apply(null, ys) + grow - y0 };
    }

    // An area on the stage: the picture under it pixelated or blurred by an
    // SVG filter (or the solid colour), cut to its shape by a mask.
    function redactNode(l) {
        var id = 'pgie-rd-' + (++serial), g = svg('g', {}), defs = svg('defs', {}), mask = svg('mask', { id: id + '-m', maskUnits: 'userSpaceOnUse' });
        var b = areaBox(l), src = this.img ? this.img.src : '', content;

        mask.setAttribute('x', b.x - 1);
        mask.setAttribute('y', b.y - 1);
        mask.setAttribute('width', b.w + 2);
        mask.setAttribute('height', b.h + 2);
        mask.appendChild(areaShape(l));
        defs.appendChild(mask);
        g.appendChild(defs);

        if ((l.mode === 'solid') || !src || !this.doc.source) {
            content = svg('rect', { x: b.x - 1, y: b.y - 1, width: b.w + 2, height: b.h + 2, fill: SOLID });
        } else {
            var size = M.redactSizes(l.strength || 60, Math.max(this.doc.source.width, this.doc.source.height));
            var filter = svg('filter', { id: id + '-f', filterUnits: 'userSpaceOnUse', 'color-interpolation-filters': 'sRGB' });
            var m = M.pictureMatrix(this.doc), image = svg('image', {
                href: src, x: 0, y: 0, width: this.doc.source.width, height: this.doc.source.height, preserveAspectRatio: 'none',
                transform: 'matrix(' + m.map(function (n) { return Math.round(n * 1e6) / 1e6; }).join(' ') + ')'
            });
            var colour = svg('g', { filter: 'url(#' + this.fxId + ')' });

            if (l.mode === 'blur') {
                var grow = size.sigma * 3;

                filter.setAttribute('x', b.x - grow);
                filter.setAttribute('y', b.y - grow);
                filter.setAttribute('width', b.w + 2 * grow);
                filter.setAttribute('height', b.h + 2 * grow);
                filter.appendChild(svg('feGaussianBlur', { stdDeviation: size.sigma, edgeMode: 'duplicate' }));
            } else {
                // One sample a block, grown to the block: the grid starts at
                // the crop box's corner, as the server's starts at the output's.
                var crop = M.cropBox(this.doc), k = size.block, half = k / 2;
                var gx = crop.x + Math.floor((b.x - crop.x) / k) * k, gy = crop.y + Math.floor((b.y - crop.y) / k) * k;

                filter.setAttribute('x', gx);
                filter.setAttribute('y', gy);
                filter.setAttribute('width', Math.ceil((b.x + b.w - gx) / k) * k);
                filter.setAttribute('height', Math.ceil((b.y + b.h - gy) / k) * k);
                filter.appendChild(svg('feFlood', { x: gx + half - 0.5, y: gy + half - 0.5, width: 1, height: 1 }));
                filter.appendChild(svg('feComposite', { x: gx, y: gy, width: k, height: k }));
                filter.appendChild(svg('feTile', { result: 'grid' }));
                filter.appendChild(svg('feComposite', { in: 'SourceGraphic', in2: 'grid', operator: 'in' }));
                filter.appendChild(svg('feMorphology', { operator: 'dilate', radius: half }));
            }

            defs.appendChild(filter);
            colour.appendChild(image);
            content = svg('g', { filter: 'url(#' + id + '-f)' });
            content.appendChild(colour);
        }

        var cut = svg('g', { mask: 'url(#' + id + '-m)' });

        cut.appendChild(content);
        g.appendChild(cut);

        return g;
    }

    Editor.layerTypes.redact = {
        node: redactNode,
        name: function (l) { return t('layer_redact') + ' · ' + t(MODE_LABELS[l.mode] || 'redactPixel'); },
        panel: function (body, layers) { this.redactStyle(body, layers); }
    };

    Editor.inits.push(function () {
        this.redactDefaults = { mode: this.tools.mini ? 'blur' : 'pixel', strength: 60, shape: 'box', size: 0 };
    });

    // The brush's width in the turned space: the chosen one, else 4 % of
    // the picture's longer side.
    Editor.prototype.redactBrushSize = function () {
        var size = M.docSize(this.doc);

        return this.redactDefaults.size || Math.max(4, Math.round(Math.max(size.w, size.h) * 0.04));
    };

    Editor.prototype.newRedaction = function () {
        var d = this.redactDefaults;

        return { type: 'redact', mode: d.mode, strength: d.strength };
    };

    Editor.prototype.redactDown = function (e) {
        var h = e.target.getAttribute && e.target.getAttribute('data-h'), p = this.toDoc(e.clientX, e.clientY);

        if (h && (this.sel.length === 1)) {
            this.startDrag(e);
            return;
        }

        // A press on a selected layer moves it; anywhere else draws an area.
        var hit = M.hitLayer(this.doc, p[0], p[1], 4 / this.view.k);

        if (hit && (this.sel.indexOf(hit.id) >= 0)) {
            this.startDrag(e);
            return;
        }

        this.drag = { kind: 'redact', shape: this.redactDefaults.shape, at: p, pts: [p], id: null, hit: hit ? hit.id : null };
        e.preventDefault();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }
    };

    Editor.prototype.redactMove = function (e) {
        var self = this, d = this.drag, p = this.toDoc(e.clientX, e.clientY), k = this.view.k, geo;

        if (d.kind !== 'redact') {
            this.moveDrag(e);
            return;
        }

        // A few screen pixels before anything is drawn: a click selects.
        if (!d.id && (Math.hypot(p[0] - d.at[0], p[1] - d.at[1]) * k < 3)) {
            return;
        }

        if (d.shape === 'brush') {
            var last = d.pts[d.pts.length - 1];

            if (Math.hypot(p[0] - last[0], p[1] - last[1]) * k < 2) {
                return;
            }

            d.pts.push(p);
            geo = M.brushLayer(d.pts, this.redactBrushSize());
        } else {
            geo = M.shapeBox(d.at[0], d.at[1], p[0], p[1], e.shiftKey);
            geo.shape = 'box';
        }

        this.change(function (doc) {
            var l = d.id ? find(doc, d.id) : null;

            if (!l) {
                l = self.newRedaction();
                l.id = M.newId(doc);
                d.id = l.id;
                doc.layers.push(l);
                self.sel = [l.id];
            }

            Object.keys(geo).forEach(function (key) { l[key] = geo[key]; });
        }, 'redact', true);
    };

    Editor.prototype.redactUp = function (e) {
        var self = this, d = this.drag;

        if (d.kind !== 'redact') {
            this.endDrag(e);
            return;
        }

        this.drag = null;
        this.history.key = null;

        if (!d.id && (d.shape === 'brush')) {
            // A click with the brush leaves a dot.
            this.change(function (doc) {
                var l = self.newRedaction(), geo = M.brushLayer([d.at], self.redactBrushSize());

                Object.keys(geo).forEach(function (key) { l[key] = geo[key]; });
                l.id = M.newId(doc);
                d.id = l.id;
                doc.layers.push(l);
            });
        }

        this.select(d.id ? [d.id] : (d.hit ? [d.hit] : []));
    };

    // The method, strength and brush of the selected areas, or of the next
    // one (layers null).
    Editor.prototype.redactStyle = function (body, layers) {
        var self = this, d = this.redactDefaults, shown = layers ? layers[0] : d;
        var set = function (field, value, live) {
            if (!layers || field !== 'shape') {
                d[field] = value;
            }

            if (!layers) {
                self.render();
                return;
            }

            self.change(function (doc) {
                layers.forEach(function (o) {
                    var l = find(doc, o.id);

                    if (l && !l.locked) {
                        l[field] = value;
                    }
                });
            }, 'redact-' + field, live);
        };

        body.appendChild(X.section(t('redactMethod'), this.drawSegment(M.REDACT_MODES.map(function (m) { return [m, t(MODE_LABELS[m])]; }),
            shown.mode, function (v) { set('mode', v, false); })));

        if (!layers) {
            body.appendChild(X.section(t('redactArea'), this.drawSegment([['box', t('redactBox')], ['brush', t('redactBrush')]],
                d.shape, function (v) { set('shape', v, false); })));
        }

        var brush = layers ? (layers[0].shape === 'brush') : (d.shape === 'brush');

        if (brush) {
            var size = layers ? Math.round(layers[0].size || 1) : this.redactBrushSize(), top = Math.max(200, size * 2);

            body.appendChild(this.drawSlider(t('redactBrushSize'), 2, top, size, function (v) { return v + ' px'; }, function (v, live) { set('size', v, live); }));
        }

        if (shown.mode !== 'solid') {
            body.appendChild(this.drawSlider(t('redactStrength'), 1, 100, Math.round(shown.strength || 60), String, function (v, live) { set('strength', v, live); }));
        }

        body.appendChild(el('p', { class: 'pgie-note pgie-warn', text: t('redactNote') }));
    };

    Editor.prototype.redactPanel = function (body) {
        var self = this, picked = this.selected().filter(isRedact);

        if (!picked.length) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('redactIntro') }));
        }

        this.redactStyle(body, picked.length ? picked : null);

        if (picked.length) {
            var free = picked.filter(function (l) { return !l.locked; });
            var arrange = function (how) {
                self.change(function (doc) { doc.layers = M.reorder(doc.layers, self.sel, how); });
            };
            var act = function (name, label, fn, off) {
                var b = button('pgie-ibtn', label, name, fn);

                b.disabled = !!off;

                return b;
            };

            body.appendChild(el('div', { class: 'pgie-btnrow' }, [
                act('duplicate', t('duplicate'), function () { self.addLayers(self.selected(), 10); }),
                act('forward', t('bringForward'), function () { arrange('up'); }),
                act('backward', t('sendBackward'), function () { arrange('down'); }),
                act('trash', t('delete'), function () { self.removeLayers(); }, !free.length)
            ]));
        }
    };

    // One mask per visible area, drawn at the output size over its own box:
    // the server pixelates, blurs or covers what lies under the opaque part.
    Editor.prototype.redactMasks = function () {
        var box = M.cropBox(this.doc), out = M.outputSize(this.doc), list = [];

        this.doc.layers.forEach(function (l) {
            var r = (isRedact(l) && !l.hidden) ? M.redactRegion(l, box, out.w, out.h) : null;

            if (!r) {
                return;
            }

            var canvas = document.createElement('canvas'), g;

            canvas.width = r.w;
            canvas.height = r.h;
            g = canvas.getContext('2d');
            g.setTransform(out.w / box.w, 0, 0, out.h / box.h, -box.x * out.w / box.w - r.x, -box.y * out.h / box.h - r.y);
            paintShape(g, l);
            list.push({ mode: l.mode, strength: Math.min(100, Math.max(1, Math.round(l.strength || 60))), x: r.x, y: r.y, mask: canvas.toDataURL('image/png') });
        });

        return list;
    };

    // save.url ('url') draws the picture on another endpoint, which burns the
    // masks in as a replace does (pg_image_editor_render()).
    Editor.saveHooks.push(function (body, mode) {
        if ((mode === 'replace') || (mode === 'copy') || (mode === 'url')) {
            var masks = this.redactMasks();

            if (masks.length) {
                body.redact = masks;
            }
        }
    });

    Editor.panels.redact = Editor.prototype.redactPanel;
    Editor.hints.redact = 'hintRedact';
    Editor.pointers.redact = { down: Editor.prototype.redactDown, move: Editor.prototype.redactMove, up: Editor.prototype.redactUp };
    Editor.selectTools.push('redact');
}(window.PgImageEditor.parts));
