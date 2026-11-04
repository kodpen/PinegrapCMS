/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: area selection on the picture: rectangle, ellipse, lasso,
 * polygon and magic wand, added (Shift) or taken away (Alt), inverted and
 * feathered, shown as marching ants; and what it does to pixels: Erase,
 * Keep only, Copy to a layer, Adjust inside and Redact.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_redact.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * The selection is kept in the document (doc.selection, the model's
 * SELECTION section), so it opens again with the document; a wand keeps
 * its seed and its outline is worked out from the picture. Redact the
 * selection makes a redaction area of its outline (pg_image_editor_redact.js),
 * whose mask the save sends as any other; Erase and Adjust inside are layers
 * of the outline too, whose masks the server's mask step draws
 * (includes/image_engine.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, find = X.find, t = X.t, el = X.el, button = X.button, section = X.section, tile = X.tile;

    X.icons.area = 'M4 4h3M10 4h4M17 4h3v3M20 10v4M20 17v3h-3M14 20h-4M7 20H4v-3M4 14v-4M4 7V4';
    X.icons.areaRect = 'M4 6h16v12H4z';
    X.icons.areaEllipse = 'M12 5c4.4 0 8 3.1 8 7s-3.6 7-8 7-8-3.1-8-7 3.6-7 8-7z';
    X.icons.areaLasso = 'M7 16c-2.4-1-4-3-4-5.5C3 6.4 7 3.5 12 3.5s9 2.9 9 7-4 7-9 7c-1.6 0-3.1-.3-4.4-.8M7 16c-1 1.6-.4 3.6 1.4 4.5';
    X.icons.areaPolygon = 'M5 18l2-11 8-3 5 8-6 7z';
    X.icons.areaWand = 'M4 20L14 10M15 3v3M15 12v3M11 7h-3M22 7h-3M18 4l-1.5 1.5M18 10l-1.5-1.5';

    var KINDS = [['rect', 'areaRect'], ['ellipse', 'areaEllipse'], ['lasso', 'areaLasso'], ['polygon', 'areaPolygon'], ['wand', 'areaWand']];

    // The wand reads the picture at most this long a side.
    var WAND_SIDE = 1024;

    // A copy to a layer is at most this long a side.
    var COPY_SIDE = 4096;

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
    // and the picture's place stay as they are.
    Editor.prototype.areaRings = function () {
        var sel = this.doc.selection;

        if (!sel || !this.doc.source) {
            return [];
        }

        var key = JSON.stringify([sel.ops, sel.invert, this.doc.geometry, this.img ? this.img.src : '']);

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

    // ---- what the selection does to pixels (E27b) ----
    // Erase and Adjust inside are layers of the selection's outline, as a
    // redaction of it is: they move, stretch, hide and undo as layers. They
    // paint nothing into the overlay; a save sends a mask of each and the
    // server erases or colours under it (the engine's mask step, before the
    // redaction).

    var serial = 0;

    function isMaskLayer(l) {
        return (l.type === 'erase') || (l.type === 'adjust');
    }

    // The colour step of an Adjust inside layer, null for none.
    function areaColor(l) {
        return M.colorMatrix(M.colorValues({ adjust: l.adjust || {}, filter: l.filter || 'none' }));
    }

    // An erased or adjusted area on the stage: a fill, a checkerboard for
    // see-through, or the picture again under the area's own colour filter,
    // cut to its shape by a mask.
    function maskNode(l) {
        var A = X.areaShapes, id = 'pgie-ar-' + (++serial), g = svg('g', { opacity: (l.opacity === undefined) ? 1 : l.opacity }), defs = svg('defs', {}), b = A.box(l), shape = A.node(l), content = null;
        var mask = svg('mask', { id: id + '-m', maskUnits: 'userSpaceOnUse', x: b.x - 1, y: b.y - 1, width: b.w + 2, height: b.h + 2 });
        var whole = { x: b.x - 1, y: b.y - 1, width: b.w + 2, height: b.h + 2 };

        if (l.feather) {
            var soft = svg('filter', { id: id + '-s', filterUnits: 'userSpaceOnUse', x: b.x - 1, y: b.y - 1, width: b.w + 2, height: b.h + 2 });

            soft.appendChild(svg('feGaussianBlur', { stdDeviation: l.feather / 2 }));
            defs.appendChild(soft);
            shape.setAttribute('filter', 'url(#' + id + '-s)');
        }

        mask.appendChild(shape);
        defs.appendChild(mask);
        g.appendChild(defs);

        if ((l.type === 'erase') && l.fill) {
            content = svg('rect', Object.assign({ fill: l.fill }, whole));
        } else if (l.type === 'erase') {
            // See-through as the canvas shows it: grey and white squares.
            var size = this.doc.source ? Math.max(4, Math.round(Math.max(this.doc.source.width, this.doc.source.height) / 80)) : 8;
            var pattern = svg('pattern', { id: id + '-p', patternUnits: 'userSpaceOnUse', width: size * 2, height: size * 2 });

            pattern.appendChild(svg('rect', { width: size * 2, height: size * 2, fill: '#fff' }));
            pattern.appendChild(svg('path', { d: 'M0 0h' + size + 'v' + size + 'h' + size + 'v' + size + 'h-' + size + 'v-' + size + 'h-' + size + 'z', fill: '#d0d3d9' }));
            defs.appendChild(pattern);
            content = svg('rect', Object.assign({ fill: 'url(#' + id + '-p)' }, whole));
        } else if (this.img && this.doc.source && areaColor(l)) {
            var c = areaColor(l), filter = svg('filter', { id: id + '-f', 'color-interpolation-filters': 'sRGB', x: 0, y: 0, width: '100%', height: '100%' });
            var funcs = svg('feComponentTransfer', {}), m = M.placedMatrix(this.doc);
            var image = svg('image', {
                href: this.img.src, x: 0, y: 0, width: this.doc.source.width, height: this.doc.source.height, preserveAspectRatio: 'none',
                transform: 'matrix(' + m.map(function (n) { return Math.round(n * 1e6) / 1e6; }).join(' ') + ')'
            });
            var colour = svg('g', { filter: 'url(#' + this.fxId + ')' });

            filter.appendChild(svg('feColorMatrix', { type: 'matrix', values: c.matrix.join(' ') }));
            ['feFuncR', 'feFuncG', 'feFuncB'].forEach(function (f) { funcs.appendChild(svg(f, { type: 'gamma', amplitude: 1, exponent: c.gamma, offset: 0 })); });
            filter.appendChild(funcs);
            defs.appendChild(filter);
            colour.appendChild(image);
            content = svg('g', { filter: 'url(#' + id + '-f)' });
            content.appendChild(colour);
        }

        if (content) {
            var cut = svg('g', { mask: 'url(#' + id + '-m)' });

            cut.appendChild(content);
            g.appendChild(cut);
        }

        return g;
    }

    // Whether the file is a JPEG, which keeps no see-through pixels.
    Editor.prototype.areaJpeg = function () {
        return M.fileFormat((this.file && this.file.name) || this.opts.fileName || '') === 'jpeg';
    };

    // An erased area's panel: see-through or a fill colour.
    Editor.prototype.erasePanel = function (body, layers) {
        var self = this, l = layers[0], filled = !!l.fill;
        var set = function (fill) {
            self.change(function (doc) {
                var it = find(doc, l.id);

                if (fill) {
                    it.fill = fill;
                } else {
                    delete it.fill;
                }
            }, 'erase-fill', true);
        };

        body.appendChild(check(t('eraseFill'), filled, function (on) {
            set(on ? '#ffffff' : null);
            self.history.key = null;
            self.render();
        }));

        if (filled) {
            var colour = el('input', { type: 'color', class: 'pgie-color', value: l.fill, 'aria-label': t('eraseColour') });

            colour.addEventListener('input', function () { set(colour.value); });
            colour.addEventListener('change', function () { self.history.key = null; });
            body.appendChild(section(t('eraseColour'), colour));
        } else if (this.areaJpeg()) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('eraseJpegNote') }));
        }
    };

    Editor.layerTypes.erase = {
        node: maskNode,
        name: function () { return t('layer_erase'); },
        panel: function (body, layers) {
            if (layers.length === 1) {
                this.erasePanel(body, layers);
            }
        }
    };

    Editor.layerTypes.adjust = {
        node: maskNode,
        name: function () { return t('layer_adjust'); },
        panel: function (body, layers) {
            if ((layers.length === 1) && this.doc.source) {
                this.adjustPanel(body);
            }
        }
    };

    // The sliders change an Adjust inside layer while it alone is selected.
    Editor.prototype.adjustTarget = function (doc) {
        var l = ((this.tool === 'select') && (this.sel.length === 1)) ? find(doc, this.sel[0]) : null;

        return (l && (l.type === 'adjust')) ? l : doc;
    };

    // A layer of the selection's outline (or of rings), feathered as the
    // selection is, pushed onto doc; its id, or null for no area. extra
    // holds the layer's own fields.
    function pushArea(doc, type, extra, rings) {
        var geo = M.areaLayer(rings), feather = doc.selection ? (doc.selection.feather || 0) : 0;

        if (!geo) {
            return null;
        }

        var l = Object.assign({ type: type }, geo, extra || {});

        if (feather) {
            l.feather = feather;
        }

        l.id = M.newId(doc);
        doc.layers.push(l);

        return l.id;
    }

    Editor.prototype.areaMake = function (type, extra) {
        var rings = this.areaRings(), id = null;

        if (rings.length) {
            this.change(function (doc) { id = pushArea(doc, type, extra, rings); });
            this.history.key = null;
        }

        return id;
    };

    // Show a new layer's panel with the Select tool.
    Editor.prototype.areaShow = function (id) {
        this.sel = id ? [id] : [];
        this.tool = 'select';
        this.sheetOpen = true;
        this.layersOpen = false;
        this.render();
    };

    Editor.prototype.areaErase = function () {
        this.areaShow(this.areaMake('erase'));
    };

    Editor.prototype.areaAdjust = function () {
        this.areaShow(this.areaMake('adjust', { adjust: {}, filter: 'none' }));
    };

    // Keep only: the picture cropped to the selection's box and the rest of
    // the box erased. A straightened picture or one on a canvas keeps its
    // crop (a new crop would move it under its layers): only the rest goes.
    Editor.prototype.areaKeep = function () {
        var rings = this.areaRings(), geo = M.areaLayer(rings), g = this.doc.geometry;

        if (!geo) {
            return;
        }

        var crop = !this.doc.canvas && !g.straighten, c = M.cropBox(this.doc), frame = M.pictureBox(this.doc), id = null;

        if (crop) {
            var x = Math.max(c.x, geo.x), y = Math.max(c.y, geo.y);

            frame = { x: x, y: y, w: Math.max(1, Math.min(c.x + c.w, geo.x + geo.w) - x), h: Math.max(1, Math.min(c.y + c.h, geo.y + geo.h) - y) };
        }

        var rest = M.areaRest(rings, frame);

        this.change(function (doc) {
            var o = doc.geometry.output;

            if (crop) {
                if (o) {
                    doc.geometry.output = { w: Math.max(1, Math.round(o.w * frame.w / c.w)), h: Math.max(1, Math.round(o.h * frame.h / c.h)) };
                }

                doc.geometry.crop = { x: frame.x, y: frame.y, w: frame.w, h: frame.h };
            }

            // A rectangle cropped to leaves nothing to erase.
            id = (M.ringsArea(rest) >= 1) ? pushArea(doc, 'erase', null, rest) : null;
            delete doc.selection;
        });
        this.history.key = null;

        if (crop) {
            this.renderMeta();
            this.fit();
        }

        this.areaShow(id);
    };

    // Copy to a layer: the picture inside the selection, with its colour
    // step, as a picture layer in place (a PNG the save keeps as a file).
    // Redacted and erased areas stay out of the copy.
    Editor.prototype.areaCopy = function () {
        var self = this, rings = this.areaRings(), geo = M.areaLayer(rings), img = this.img, src = this.doc.source;

        if (!geo || !img || !src) {
            return;
        }

        var m = M.placedMatrix(this.doc), unit = Math.sqrt(Math.abs(m[0] * m[3] - m[1] * m[2])) || 1;
        var k = Math.min((img.naturalWidth / src.width) / unit, COPY_SIDE / Math.max(geo.w, geo.h));
        var canvas = document.createElement('canvas'), g = canvas.getContext('2d'), url = null;
        var at = function () { g.setTransform(k, 0, 0, k, -geo.x * k, -geo.y * k); };

        canvas.width = Math.max(1, Math.ceil(geo.w * k));
        canvas.height = Math.max(1, Math.ceil(geo.h * k));

        try {
            at();
            g.transform(m[0], m[1], m[2], m[3], m[4], m[5]);
            g.drawImage(img, 0, 0, src.width, src.height);
            g.setTransform(1, 0, 0, 1, 0, 0);

            var c = M.colorMatrix(M.colorValues(this.doc));

            if (c) {
                var data = g.getImageData(0, 0, canvas.width, canvas.height);

                M.applyColor(data.data, c);
                g.putImageData(data, 0, 0);
            }

            g.globalCompositeOperation = 'destination-in';
            g.save();
            at();
            X.areaShapes.paint(g, Object.assign({ feather: this.doc.selection ? (this.doc.selection.feather || 0) : 0 }, geo), k);
            g.restore();
            g.globalCompositeOperation = 'destination-out';
            this.doc.layers.forEach(function (l) {
                if (!l.hidden && ((l.type === 'redact') || ((l.type === 'erase') && !l.fill))) {
                    g.save();
                    at();
                    X.areaShapes.paint(g, l, k);
                    g.restore();
                }
            });
            url = canvas.toDataURL('image/png');
        } catch (err) {
            // A picture from another site cannot be read back.
            url = null;
        }

        if (!url) {
            this.areaCopyFailed = true;
            this.render();
            return;
        }

        this.areaCopyFailed = false;
        this.addLayers([{ type: 'image', src: url, fileName: t('areaCutName') + '.png', x: geo.x, y: geo.y, w: geo.w, h: geo.h, rot: 0, opacity: 1 }], 0);
        this.areaShow(this.sel[0]);
        self.history.key = null;
    };

    // One mask per visible Erase or Adjust inside layer, drawn at the output
    // size over its own box, in the layers' order.
    Editor.prototype.areaMasks = function () {
        var box = M.pictureBox(this.doc), out = M.outputSize(this.doc), list = [];

        this.doc.layers.forEach(function (l) {
            var r = (isMaskLayer(l) && !l.hidden && ((l.type === 'erase') || areaColor(l))) ? M.redactRegion(l, box, out.w, out.h) : null;

            if (!r) {
                return;
            }

            var canvas = document.createElement('canvas'), g, entry;

            canvas.width = r.w;
            canvas.height = r.h;
            g = canvas.getContext('2d');
            g.setTransform(out.w / box.w, 0, 0, out.h / box.h, -box.x * out.w / box.w - r.x, -box.y * out.h / box.h - r.y);
            // The layer's opacity weakens the area's effect.
            g.globalAlpha = (l.opacity === undefined) ? 1 : l.opacity;
            X.areaShapes.paint(g, l, out.w / box.w);
            entry = { op: (l.type === 'erase') ? 'erase' : 'color', x: r.x, y: r.y, mask: canvas.toDataURL('image/png') };

            if (l.type === 'erase') {
                if (l.fill) {
                    entry.fill = l.fill;
                }
            } else {
                entry.adjust = l.adjust || {};
                entry.filter = l.filter || 'none';
            }

            list.push(entry);
        });

        return list;
    };

    // Whether a see-through erase is visible.
    Editor.prototype.areaSeeThrough = function () {
        return this.doc.layers.some(function (l) { return (l.type === 'erase') && !l.hidden && !l.fill; });
    };

    // The masks go with every save that draws the picture; a JPEG with a
    // see-through erase is written as a PNG copy beside it (the export part,
    // bundled later, replaces the format when its panel chose one).
    Editor.saveHooks.push(function (body, mode) {
        if ((mode !== 'replace') && (mode !== 'copy') && (mode !== 'url')) {
            return;
        }

        var masks = this.areaMasks();

        if (masks.length) {
            body.masks = masks;
        }

        if ((mode !== 'url') && this.areaJpeg() && this.areaSeeThrough()) {
            body.export = { format: 'png' };
            body.mode = 'copy';
            delete body.pictures;
        }
    });


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

        var acts = el('div', { class: 'pgie-aacts', role: 'group', 'aria-label': t('areaWith') });
        var act = function (label, fn, off) {
            var b = button('pgie-btn pgie-abtn', label, null, fn, true);

            b.disabled = !has || !!off;
            acts.appendChild(b);
        };

        act(t('areaErase'), function () { self.areaErase(); });
        act(t('areaKeep'), function () { self.areaKeep(); });
        act(t('areaCopy'), function () { self.areaCopy(); });
        act(t('areaAdjust'), function () { self.areaAdjust(); });
        act(t('areaRedact'), function () { self.areaRedact(); }, !this.newRedaction);
        body.appendChild(section(t('areaWith'), acts));

        if (this.areaCopyFailed) {
            body.appendChild(el('p', { class: 'pgie-note pgie-warn', text: t('areaCopyNote') }));
        }
    };

    Editor.panels.area = Editor.prototype.areaPanel;
    Editor.hints.area = 'hintArea';
    Editor.pointers.area = { down: Editor.prototype.areaDown, move: Editor.prototype.areaMove, up: Editor.prototype.areaUp, double: Editor.prototype.areaDouble };
    Editor.views.push(Editor.prototype.renderArea);
}(window.PgImageEditor.parts));
