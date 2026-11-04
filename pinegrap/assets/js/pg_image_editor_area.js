/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: area selection on the picture: rectangle, ellipse, lasso,
 * polygon and magic wand, added (Shift) or taken away (Alt), inverted and
 * feathered, shown as marching ants.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_redact.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * The selection is kept in the document (doc.selection, the model's
 * SELECTION section), so it opens again with the document; a wand keeps
 * its seed and its outline is worked out from the picture. Redact the
 * selection makes a redaction area of its outline (pg_image_editor_redact.js),
 * whose mask the save sends as any other.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, t = X.t, el = X.el, button = X.button, section = X.section, tile = X.tile;

    X.icons.area = 'M4 4h3M10 4h4M17 4h3v3M20 10v4M20 17v3h-3M14 20h-4M7 20H4v-3M4 14v-4M4 7V4';
    X.icons.areaRect = 'M4 6h16v12H4z';
    X.icons.areaEllipse = 'M12 5c4.4 0 8 3.1 8 7s-3.6 7-8 7-8-3.1-8-7 3.6-7 8-7z';
    X.icons.areaLasso = 'M7 16c-2.4-1-4-3-4-5.5C3 6.4 7 3.5 12 3.5s9 2.9 9 7-4 7-9 7c-1.6 0-3.1-.3-4.4-.8M7 16c-1 1.6-.4 3.6 1.4 4.5';
    X.icons.areaPolygon = 'M5 18l2-11 8-3 5 8-6 7z';
    X.icons.areaWand = 'M4 20L14 10M15 3v3M15 12v3M11 7h-3M22 7h-3M18 4l-1.5 1.5M18 10l-1.5-1.5';

    var KINDS = [['rect', 'areaRect'], ['ellipse', 'areaEllipse'], ['lasso', 'areaLasso'], ['polygon', 'areaPolygon'], ['wand', 'areaWand']];

    // The wand reads the picture at most this long a side.
    var WAND_SIDE = 1024;

    // A press closer than this to the polygon's first corner closes it, in
    // screen pixels.
    var CLOSE_PX = 10;

    function svg(tag, attrs) {
        var n = document.createElementNS(SVGNS, tag);

        Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });

        return n;
    }

    Editor.inits.push(function () {
        this.areaDefaults = { kind: 'rect', mode: 'new', tolerance: 20, contiguous: true };
        this.areaPoly = null;
        this.areaCache = null;
        this.areaPixelCache = null;
    });

    // The picture's pixels at most WAND_SIDE a side and the matrix from them
    // to the turned space; null when the picture cannot be read back.
    Editor.prototype.areaPixels = function () {
        var img = this.img;

        if (!img || !this.doc.source) {
            return null;
        }

        if (!this.areaPixelCache || (this.areaPixelCache.src !== img.src)) {
            var nw = img.naturalWidth, nh = img.naturalHeight, k = Math.min(1, WAND_SIDE / Math.max(nw, nh)), data = null;
            var canvas = document.createElement('canvas'), w = Math.max(1, Math.round(nw * k)), h = Math.max(1, Math.round(nh * k));

            canvas.width = w;
            canvas.height = h;

            try {
                var g = canvas.getContext('2d');

                g.drawImage(img, 0, 0, w, h);
                data = g.getImageData(0, 0, w, h).data;
            } catch (err) {
                // A picture from another site cannot be read back.
                data = null;
            }

            this.areaPixelCache = { src: img.src, w: w, h: h, data: data };
        }

        var px = this.areaPixelCache, src = this.doc.source;

        return px.data ? { w: px.w, h: px.h, data: px.data, m: M.mul(M.placedMatrix(this.doc), M.scale(src.width / px.w, src.height / px.h)) } : null;
    };

    // The selection's area in the turned space, kept while the selection
    // and the picture's place stay as they are. The place on the canvas
    // (doc.canvas.picture) is part of it: an unlocked picture moved or
    // resized carries its wand outlines and the frame an invert fills.
    Editor.prototype.areaRings = function () {
        var sel = this.doc.selection;

        if (!sel || !this.doc.source) {
            return [];
        }

        var key = JSON.stringify([sel.ops, sel.invert, this.doc.geometry, M.picturePlace(this.doc), this.img ? this.img.src : '']);

        if (!this.areaCache || (this.areaCache.key !== key)) {
            var px = null, wands = (sel.ops || []).map(function (o) {
                if (o.kind !== 'wand') {
                    return null;
                }

                px = px || this.areaPixels() || false;

                if (!px) {
                    return null;
                }

                var at = M.apply(M.inv(px.m), o.at[0], o.at[1]);

                return M.mapRings(M.maskRings(M.wandMask(px.data, px.w, px.h, at[0], at[1], o.tolerance, o.contiguous !== false), px.w, px.h), px.m);
            }, this);

            this.areaCache = { key: key, rings: M.selectionRings(sel, wands, M.pictureBox(this.doc)) };
        }

        return this.areaCache.rings;
    };

    // Whether the selection holds any area.
    Editor.prototype.areaSelected = function () {
        return this.areaRings().length > 0;
    };

    // The op a press makes: Alt takes away, Shift adds, else the panel's
    // choice (New starts again).
    Editor.prototype.areaOp = function (e) {
        return e.altKey ? 'sub' : (e.shiftKey ? 'add' : this.areaDefaults.mode);
    };

    // One shape (or wand seed) into the selection as one undo step. On an
    // inverted selection adding takes away from what was inverted, and the
    // other way round, so the inverted area grows or shrinks as asked.
    Editor.prototype.areaAdd = function (op, entry) {
        var self = this;

        if ((op !== 'new') && this.doc.selection && ((this.doc.selection.ops || []).length >= M.AREA_MAX_OPS)) {
            this.areaFull = true;
            this.render();
            return;
        }

        this.areaFull = false;
        this.change(function (doc) {
            var sel = (op === 'new') ? null : doc.selection;

            sel = sel ? sel : { ops: [], invert: false, feather: doc.selection ? (doc.selection.feather || 0) : 0 };

            if (op === 'new') {
                op = 'add';
            } else if (sel.invert) {
                op = (op === 'add') ? 'sub' : 'add';
            }

            entry.op = op;
            sel.ops.push(entry);
            doc.selection = sel;
        });
        this.history.key = null;
    };

    // Deselect: the selection goes from the document.
    Editor.prototype.areaClear = function () {
        this.areaPoly = null;
        this.areaFull = false;

        if (this.doc.selection) {
            this.change(function (doc) { delete doc.selection; });
            this.history.key = null;
        } else {
            this.render();
        }
    };

    Editor.prototype.areaInvert = function () {
        this.change(function (doc) {
            var sel = doc.selection || { ops: [], invert: false, feather: 0 };

            sel.invert = !sel.invert;
            doc.selection = sel;
        });
        this.history.key = null;
    };

    Editor.prototype.areaDown = function (e) {
        var p = this.toDoc(e.clientX, e.clientY), kind = this.areaDefaults.kind, op = this.areaOp(e), k = this.view.k;

        e.preventDefault();

        if (kind === 'polygon') {
            var poly = this.areaPoly;

            if (poly && (poly.pts.length > 2) && (Math.hypot(p[0] - poly.pts[0][0], p[1] - poly.pts[0][1]) * k < CLOSE_PX)) {
                this.areaClosePolygon();
                return;
            }

            if (poly) {
                poly.pts.push(p);
            } else {
                this.areaPoly = { op: op, pts: [p] };
            }

            this.renderView();
            return;
        }

        this.drag = { kind: 'area', shape: kind, op: op, at: p, to: p, pts: [p] };

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }
    };

    Editor.prototype.areaMove = function (e) {
        var d = this.drag, p = this.toDoc(e.clientX, e.clientY);

        if (!d || (d.kind !== 'area')) {
            return;
        }

        d.to = p;
        d.far = d.far || M.areaDragged(d.at, [p], 3 / this.view.k);

        if (d.shape === 'lasso') {
            var last = d.pts[d.pts.length - 1];

            if (Math.hypot(p[0] - last[0], p[1] - last[1]) * this.view.k >= 2) {
                d.pts.push(p);
            }
        }

        this.renderView();
    };

    Editor.prototype.areaUp = function () {
        var d = this.drag, ring = null;

        this.drag = null;

        if (!d || (d.kind !== 'area')) {
            return;
        }

        // How far the path went, not where it was let go: a lasso closed on
        // its first point is a shape.
        var moved = !!d.far;

        if (d.shape === 'wand') {
            this.areaAdd(d.op, { kind: 'wand', at: [Math.round(d.at[0] * 100) / 100, Math.round(d.at[1] * 100) / 100],
                tolerance: this.areaDefaults.tolerance, contiguous: this.areaDefaults.contiguous });
            return;
        }

        if (moved) {
            ring = (d.shape === 'rect') ? M.areaRect(d.at, d.to) : ((d.shape === 'ellipse') ? M.areaEllipse(d.at, d.to) : M.areaLasso(d.pts, 1 / this.view.k));
        }

        if (ring) {
            this.areaAdd(d.op, { kind: d.shape, ring: ring });
        } else if (!moved && (d.op === 'new') && this.doc.selection) {
            // A click without a drag deselects, as in other editors.
            this.areaClear();
        } else {
            this.renderView();
        }
    };

    Editor.prototype.areaClosePolygon = function () {
        var poly = this.areaPoly, ring = poly ? M.areaLasso(poly.pts, 0.5 / this.view.k) : null;

        this.areaPoly = null;

        if (ring) {
            this.areaAdd(poly.op, { kind: 'polygon', ring: ring });
        } else {
            this.renderView();
        }
    };

    // A double click closes the polygon; its two presses added the last
    // corner twice, which areaLasso() drops.
    Editor.prototype.areaDouble = function () {
        if (this.areaPoly) {
            this.areaClosePolygon();
            return true;
        }

        return false;
    };

    // Escape ends a polygon being drawn, before the selection.
    Editor.escapes.push(function () {
        if ((this.tool === 'area') && this.areaPoly) {
            this.areaPoly = null;
            this.renderView();
            return true;
        }

        return false;
    });

    // The marching ants round the selection, with any tool but Crop; with
    // Select area also the shape being drawn and the polygon's corners.
    Editor.prototype.renderArea = function () {
        if (!this.ready || !this.doc.source || this.cropping()) {
            return;
        }

        var v = this.view, vr = this.viewRect(), k = v.k, d = this.drag, poly = (this.tool === 'area') ? this.areaPoly : null;
        var over = svg('svg', { class: 'pgie-vover pgie-aover', 'aria-hidden': 'true' });
        var at = function (p) { return (v.x + (p[0] - vr.x) * k) + ' ' + (v.y + (p[1] - vr.y) * k); };
        var ring = function (r) { return r.map(function (p, i) { return (i ? 'L' : 'M') + at(p); }).join('') + 'Z'; };
        var ants = function (dPath) {
            over.appendChild(svg('path', { d: dPath, class: 'pgie-ants-under', 'fill-rule': 'evenodd' }));
            over.appendChild(svg('path', { d: dPath, class: 'pgie-ants', 'fill-rule': 'evenodd' }));
        };
        var rings = this.areaRings();

        if (rings.length) {
            ants(rings.map(ring).join(''));
        }

        if ((this.tool === 'area') && d && (d.kind === 'area') && (d.shape !== 'wand')) {
            var shape = (d.shape === 'rect') ? M.areaRect(d.at, d.to) : ((d.shape === 'ellipse') ? M.areaEllipse(d.at, d.to) : null);

            if (shape) {
                ants(ring(shape));
            } else if (d.shape === 'lasso') {
                ants(d.pts.map(function (p, i) { return (i ? 'L' : 'M') + at(p); }).join(''));
            }
        }

        if (poly) {
            ants(poly.pts.map(function (p, i) { return (i ? 'L' : 'M') + at(p); }).join(''));
            poly.pts.forEach(function (p, i) {
                over.appendChild(svg('circle', { cx: v.x + (p[0] - vr.x) * k, cy: v.y + (p[1] - vr.y) * k, r: i ? 3 : 5, class: 'pgie-acorner' }));
            });
        }

        if (over.firstChild) {
            this.selEl.insertBefore(over, this.selEl.firstChild);
        }
    };

    // Redact the selection: a redaction area of its outline, feathered as it
    // is, with the Redact tool's method; the area is selected in Redact.
    Editor.prototype.areaRedact = function () {
        var self = this, geo = M.areaLayer(this.areaRings()), id = null, feather = this.doc.selection ? (this.doc.selection.feather || 0) : 0;

        if (!geo || !this.newRedaction) {
            return;
        }

        this.change(function (doc) {
            var l = self.newRedaction();

            Object.keys(geo).forEach(function (key) { l[key] = geo[key]; });

            if (feather) {
                l.feather = feather;
            }

            l.id = M.newId(doc);
            id = l.id;
            doc.layers.push(l);
        });
        this.history.key = null;
        this.sel = [id];

        if (this.tools.list.indexOf('redact') >= 0) {
            this.pickTool('redact');
        } else {
            this.render();
        }
    };

    function check(label, on, change) {
        var input = el('input', { type: 'checkbox' });

        input.checked = !!on;
        input.addEventListener('change', function () { change(input.checked); });

        return el('label', { class: 'pgie-check' }, [input, el('span', { text: label })]);
    }

    Editor.prototype.areaPanel = function (body) {
        var self = this, d = this.areaDefaults, sel = this.doc.selection, has = this.areaSelected();
        var kinds = el('div', { class: 'pgie-tiles pgie-akinds', role: 'group', 'aria-label': t('areaShape') });

        if (!has) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('areaIntro') }));
        }

        KINDS.forEach(function (kind) {
            var b = tile(kind[1], t(kind[1]), function () {
                d.kind = kind[0];
                self.areaPoly = null;
                self.render();
            });

            b.classList.toggle('on', d.kind === kind[0]);
            b.setAttribute('aria-pressed', (d.kind === kind[0]) ? 'true' : 'false');
            kinds.appendChild(b);
        });

        body.appendChild(section(t('areaShape'), kinds));
        body.appendChild(section(t('areaMode'), this.drawSegment([['new', t('areaNew')], ['add', t('areaAdd')], ['sub', t('areaSub')]],
            d.mode, function (v) { d.mode = v; self.render(); })));

        if (d.kind === 'wand') {
            body.appendChild(this.drawSlider(t('areaTolerance'), 0, 100, d.tolerance, String, function (v) { d.tolerance = v; }));
            body.appendChild(check(t('areaContiguous'), d.contiguous, function (on) { d.contiguous = on; }));

            if (!this.areaPixels()) {
                body.appendChild(el('p', { class: 'pgie-note pgie-warn', text: t('areaWandNote') }));
            }
        } else if (d.kind === 'polygon') {
            body.appendChild(el('p', { class: 'pgie-note', text: t('areaPolygonNote') }));
        }

        if (sel) {
            body.appendChild(this.drawSlider(t('areaFeather'), 0, 200, Math.round(sel.feather || 0), function (v) { return v + ' px'; }, function (v, live) {
                self.change(function (doc) {
                    if (doc.selection) {
                        doc.selection.feather = v;
                    }
                }, 'area-feather', live);
            }));
        }

        if (this.areaFull) {
            body.appendChild(el('p', { class: 'pgie-note pgie-warn', text: t('areaLimit') }));
        }

        var invert = button('pgie-btn', t('areaInvert'), null, function () { self.areaInvert(); }, true);
        var clear = button('pgie-btn', t('areaClear'), null, function () { self.areaClear(); }, true);

        clear.disabled = !sel;
        body.appendChild(el('div', { class: 'pgie-arow' }, [invert, clear]));

        var redact = button('pgie-btn pgie-abtn', t('areaRedact'), null, function () { self.areaRedact(); }, true);

        redact.disabled = !has || !this.newRedaction;
        body.appendChild(redact);
    };

    Editor.panels.area = Editor.prototype.areaPanel;
    Editor.hints.area = 'hintArea';
    Editor.pointers.area = { down: Editor.prototype.areaDown, move: Editor.prototype.areaMove, up: Editor.prototype.areaUp, double: Editor.prototype.areaDouble };
    Editor.views.push(Editor.prototype.renderArea);
}(window.PgImageEditor.parts));
