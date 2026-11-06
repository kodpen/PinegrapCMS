/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: area selection on the picture: rectangle, ellipse, lasso,
 * polygon and magic wand, added (Shift) or taken away (Alt), inverted and
 * feathered, shown as marching ants; and what it does to pixels: Erase,
 * Keep only, Copy to a layer, Adjust inside and Redact. Select subject
 * cuts the subject from its background (pg_image_editor_subject.js, in a
 * worker) into the selection; the Keep and Remove brushes mark what the
 * cut got wrong and it runs again, and the subject's soft edge follows the
 * picture at full size (a guided filter, M.matteMask()) in the masks and
 * cut-outs of the layers made from it. Remove background erases what lies
 * outside the subject; the erased part stays see-through or takes a
 * colour, a blur of the picture or a picture from Files.
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
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
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

    // Select subject cuts the picture at most this long a side.
    var SUBJECT_SIDE = 800;

    // A new subject's soft edge, in the turned space's units.
    var SUBJECT_EDGE = 4;

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
        this.areaDefaults = { kind: 'rect', mode: 'new', tolerance: 20, contiguous: true, brush: 24 };
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

    // The picture's pixels at most side long a side, as areaPixels()
    // answers; null when it cannot be read back.
    Editor.prototype.areaPixelsAt = function (side) {
        var px = this.areaPixels();

        if (!px || (Math.max(px.w, px.h) <= side)) {
            return px;
        }

        var k = side / Math.max(px.w, px.h), w = Math.max(1, Math.round(px.w * k)), h = Math.max(1, Math.round(px.h * k));
        var canvas = document.createElement('canvas'), g;

        canvas.width = w;
        canvas.height = h;
        g = canvas.getContext('2d');
        g.drawImage(this.img, 0, 0, w, h);

        return { w: w, h: h, data: g.getImageData(0, 0, w, h).data, m: M.mul(px.m, M.scale(px.w / w, px.h / h)) };
    };

    // ---- select subject (pg_image_editor_subject.js) ----

    // The cutter: the worker, or the same code on the page where a site's
    // policy refuses the worker. Answers run(message) -> a promise of the
    // answer.
    function subjectCutter() {
        var url = (X.config().subject || [])[0], worker = null;

        try {
            worker = url ? new Worker(url) : null;
        } catch (err) {
            worker = null;
        }

        if (worker) {
            return Promise.resolve(function (m, transfer) {
                return new Promise(function (resolve, reject) {
                    worker.onmessage = function (e) { worker.terminate(); resolve(e.data); };
                    worker.onerror = function (e) { e.preventDefault(); worker.terminate(); reject(new Error('worker')); };
                    worker.postMessage(m, transfer);
                });
            });
        }

        return new Promise(function (resolve, reject) {
            if (window.PgImageSubject || !url) {
                resolve(window.PgImageSubject);
                return;
            }

            var tag = document.createElement('script');

            tag.src = url;
            tag.onload = function () { resolve(window.PgImageSubject); };
            tag.onerror = reject;
            document.head.appendChild(tag);
        }).then(function (S) {
            if (!S) {
                throw new Error('subject');
            }

            return function (m) {
                return new Promise(function (resolve) {
                    setTimeout(function () { S.session(resolve).receive(m); }, 0);
                });
            };
        });
    }

    // The box a lone rectangle selection draws round the subject, in the
    // pixels px; null for any other selection.
    Editor.prototype.subjectBox = function (px) {
        var sel = this.doc.selection, ops = sel ? (sel.ops || []) : [];

        if ((ops.length !== 1) || (ops[0].kind !== 'rect') || (ops[0].op !== 'add') || sel.invert) {
            return null;
        }

        var back = M.inv(px.m), pts = ops[0].ring.map(function (p) { return M.apply(back, p[0], p[1]); });
        var xs = pts.map(function (p) { return p[0]; }), ys = pts.map(function (p) { return p[1]; });

        return [Math.min.apply(null, xs), Math.min.apply(null, ys), Math.max.apply(null, xs), Math.max.apply(null, ys)];
    };

    // Select subject: the cut in the cutter, its rings into the selection.
    // Inside a lone rectangle it replaces it; else it goes in as the
    // panel's choice (New, Add, Subtract) says.
    Editor.prototype.areaSubject = function () {
        var self = this, px = this.areaPixelsAt(SUBJECT_SIDE);

        this.subjectNote = null;

        if (!px) {
            this.subjectNote = 'subjectRead';
            this.render();
            return;
        }

        var box = this.subjectBox(px), op = box ? 'new' : this.areaDefaults.mode;

        this.subjectCut(px, box, null, function (out, rings) {
            if (!rings.length) {
                self.subjectNote = 'subjectNone';
                self.render();
                return;
            }

            self.areaAdd(op, { kind: 'subject', rings: rings, box: out.box, edge: SUBJECT_EDGE });
        });
    };

    // A cut of the pixels px from box and strokes (in px's pixels) in the
    // cutter; done(answer, rings) unless another picture came in meanwhile.
    Editor.prototype.subjectCut = function (px, box, strokes, done) {
        var self = this, data = new Uint8ClampedArray(px.data).buffer, src = this.img.src;

        this.subjectBusy = true;
        this.render();

        subjectCutter().then(function (run) {
            return run({ type: 'cut', data: data, width: px.w, height: px.h, box: box, strokes: strokes }, [data]);
        }).then(function (out) {
            self.subjectBusy = false;
            self.subjectPending = null;

            if (!out || (out.type !== 'mask')) {
                throw new Error('subject');
            }

            // Another picture came in while it cut.
            if (!self.img || (self.img.src !== src) || !self.doc.source) {
                self.render();
                return;
            }

            done(out, M.subjectRings(new Uint8Array(out.mask), px.w, px.h, px.m));
        }).then(null, function () {
            self.subjectBusy = false;
            self.subjectPending = null;
            self.subjectNote = 'subjectError';
            self.render();
        });
    };

    // The subject op the brushes change, null for none.
    Editor.prototype.subjectOp = function () {
        var i = M.subjectIndex(this.doc.selection);

        return (i >= 0) ? this.doc.selection.ops[i] : null;
    };

    // A Keep or Remove stroke: the cut runs again from the subject's box
    // and all its strokes, and its rings and the stroke go in as one undo
    // step. The stroke shows on the picture while it cuts.
    Editor.prototype.subjectStroke = function (keep, r, pts) {
        var self = this, op = this.subjectOp(), stroke = M.subjectStroke(keep, r, pts), px = this.areaPixelsAt(SUBJECT_SIDE);

        if (!op || !stroke || this.subjectBusy) {
            this.renderView();
            return;
        }

        if (!px || !op.box) {
            this.subjectNote = 'subjectRead';
            this.render();
            return;
        }

        var index = M.subjectIndex(this.doc.selection), strokes = (op.strokes || []).concat([stroke]).slice(-M.SUBJECT_MAX_STROKES);
        var back = M.inv(px.m), unit = Math.sqrt(Math.abs(back[0] * back[3] - back[1] * back[2])) || 1, key = JSON.stringify(op.box);

        this.subjectNote = null;
        this.subjectPending = stroke;
        this.subjectCut(px, op.box, strokes.map(function (s) {
            return { keep: s.keep, r: s.r * unit, pts: s.pts.map(function (p) { return M.apply(back, p[0], p[1]); }) };
        }), function (out, rings) {
            var now = self.doc.selection ? self.doc.selection.ops[index] : null;

            // The selection changed while it cut.
            if (!now || (now.kind !== 'subject') || (JSON.stringify(now.box) !== key)) {
                self.render();
                return;
            }

            self.change(function (doc) {
                var o = doc.selection.ops[index];

                o.rings = rings;
                o.strokes = strokes;
            });
            self.history.key = null;
        });
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

            if (poly && (poly.pts.length > 2) && (Math.hypot(p[0] - poly.pts[0][0], p[1] - poly.pts[0][1]) * k < M.touchReach(e.pointerType, CLOSE_PX))) {
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

        if ((kind === 'keep') || (kind === 'remove')) {
            if (!this.subjectOp() || this.subjectBusy) {
                return;
            }

            this.drag = { kind: 'area', shape: kind, at: p, to: p, pts: [p], r: this.areaDefaults.brush / 2 / k };
        } else {
            this.drag = { kind: 'area', shape: kind, op: op, at: p, to: p, pts: [p] };
        }

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

        if ((d.shape === 'lasso') || (d.shape === 'keep') || (d.shape === 'remove')) {
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

        if ((d.shape === 'keep') || (d.shape === 'remove')) {
            this.subjectStroke(d.shape === 'keep', d.r, d.pts);
            return;
        }

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

        // The subject's brush strokes while a brush is picked, the one
        // being cut and the one being drawn.
        var brushing = (this.tool === 'area') && ((this.areaDefaults.kind === 'keep') || (this.areaDefaults.kind === 'remove'));
        var subjectOp = brushing ? this.subjectOp() : null;
        var stroke = function (s, now) {
            over.appendChild(svg('path', {
                d: s.pts.map(function (p, i) { return (i ? 'L' : 'M') + at(p); }).join('') + ((s.pts.length === 1) ? 'l0.01 0' : ''),
                class: 'pgie-sstroke ' + (s.keep ? 'pgie-skeep' : 'pgie-sremove') + (now ? ' pgie-snow' : ''), 'stroke-width': Math.max(1, s.r * 2 * k)
            }));
        };

        if (subjectOp) {
            (subjectOp.strokes || []).forEach(function (s) { stroke(s, false); });
        }

        if (this.subjectPending) {
            stroke(this.subjectPending, true);
        }

        if (brushing && d && (d.kind === 'area') && ((d.shape === 'keep') || (d.shape === 'remove'))) {
            stroke({ keep: d.shape === 'keep', r: d.r, pts: d.pts }, true);
        } else if ((this.tool === 'area') && d && (d.kind === 'area') && (d.shape !== 'wand')) {
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

    // ---- what the selection does to pixels ----
    // Erase and Adjust inside are layers of the selection's outline, as a
    // redaction of it is: they move, stretch, hide and undo as layers. They
    // paint nothing into the overlay; a save sends a mask of each and the
    // server erases or colours under it (the engine's mask step, before the
    // redaction).

    var serial = 0;

    // Whether an erased area is see-through: no colour, blur or picture
    // fills it.
    function eraseClear(l) {
        return (l.type === 'erase') && !l.fill && !l.blur && !l.backdrop;
    }

    // The box a Files picture covers (the picture's frame, as CSS cover
    // does) for a picture iw x ih.
    function backdropBox(box, iw, ih) {
        var c = Math.max(box.w / (iw || 1), box.h / (ih || 1));

        return { x: box.x + (box.w - iw * c) / 2, y: box.y + (box.h - ih * c) / 2, w: iw * c, h: ih * c };
    }

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

        if ((l.type === 'erase') && l.backdrop && l.backdrop.src) {
            // A picture from Files over the picture's frame, cut to the area.
            var frame = M.pictureBox(this.doc);

            this.picture(l.backdrop.src).then(null, function () {});
            content = svg('image', {
                href: l.backdrop.src, x: frame.x, y: frame.y, width: frame.w, height: frame.h, preserveAspectRatio: 'xMidYMid slice'
            });
        } else if ((l.type === 'erase') && l.blur && this.img && this.doc.source) {
            // The picture under it, with the document's colour step, blurred.
            var blur = svg('filter', { id: id + '-b', filterUnits: 'userSpaceOnUse', x: b.x - 1, y: b.y - 1, width: b.w + 2, height: b.h + 2 });
            var pm = M.placedMatrix(this.doc), under = svg('g', { filter: 'url(#' + this.fxId + ')' });

            blur.appendChild(svg('feGaussianBlur', { stdDeviation: l.blur, edgeMode: 'duplicate' }));
            defs.appendChild(blur);
            under.appendChild(svg('image', {
                href: this.img.src, x: 0, y: 0, width: this.doc.source.width, height: this.doc.source.height, preserveAspectRatio: 'none',
                transform: 'matrix(' + pm.map(function (n) { return Math.round(n * 1e6) / 1e6; }).join(' ') + ')'
            }));
            content = svg('g', { filter: 'url(#' + id + '-b)' });
            content.appendChild(under);
        } else if ((l.type === 'erase') && l.fill) {
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

    // What fills an erased area: see-through, a colour, a blur of the
    // picture or a picture from Files; one of fill, blur, backdrop.
    Editor.prototype.eraseSet = function (id, fields, key) {
        this.change(function (doc) {
            var it = find(doc, id);

            delete it.fill;
            delete it.blur;
            delete it.backdrop;
            Object.assign(it, fields);
        }, key || null, !!key);

        if (!key) {
            this.history.key = null;
        }
    };

    // An erased area's panel: see-through, a colour, a blur or a picture.
    Editor.prototype.erasePanel = function (body, layers) {
        var self = this, l = layers[0], kind = l.backdrop ? 'picture' : (l.blur ? 'blur' : (l.fill ? 'colour' : 'none'));
        var pick = function () {
            self.openImportFiles(function (f) {
                self.picture(f.url).then(function () {
                    self.eraseSet(l.id, { backdrop: { src: f.url, fileId: f.id, name: f.name } });
                }, function () {
                    self.showMessage(t('stickerFilesError'), true);
                });
            });
        };

        var kinds = [['none', t('eraseNone')], ['colour', t('eraseColourKind')], ['blur', t('eraseBlur')]];

        // A picture from Files needs the editor's API; one kept stays shown.
        if (this.importApi() || (kind === 'picture')) {
            kinds.push(['picture', t('erasePicture')]);
        }

        body.appendChild(section(t('eraseWith'), this.drawSegment(kinds,
            kind, function (v) {
                if (v === 'colour') {
                    self.eraseSet(l.id, { fill: '#ffffff' });
                } else if (v === 'blur') {
                    self.eraseSet(l.id, { blur: Math.max(1, Math.round(Math.max(M.pictureBox(self.doc).w, M.pictureBox(self.doc).h) / 100)) });
                } else if (v === 'picture') {
                    if (self.importApi()) {
                        pick();
                    }
                } else {
                    self.eraseSet(l.id, {});
                }
            })));

        if (kind === 'colour') {
            var colour = el('input', { type: 'color', class: 'pgie-color', value: l.fill, 'aria-label': t('eraseColour') });

            colour.addEventListener('input', function () { self.eraseSet(l.id, { fill: colour.value }, 'erase-fill'); });
            colour.addEventListener('change', function () { self.history.key = null; });
            body.appendChild(section(t('eraseColour'), colour));
        } else if (kind === 'blur') {
            body.appendChild(this.drawSlider(t('eraseBlurAmount'), 1, 100, Math.round(l.blur), function (v) { return v + ' px'; }, function (v, live) {
                self.eraseSet(l.id, { blur: v }, 'erase-blur');

                if (!live) {
                    self.history.key = null;
                }
            }));
        } else if (kind === 'picture') {
            body.appendChild(el('p', { class: 'pgie-note', text: l.backdrop.name || '' }));
            if (this.importApi()) {
                body.appendChild(el('div', { class: 'pgie-arow' }, [button('pgie-btn', t('erasePick'), null, pick, true)]));
            }
        } else if (this.areaJpeg()) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('eraseJpegNote') }));
        }

    };

    // A press takes an area layer only inside its outline (M.areaHit()).
    function hitArea(l, p) {
        return M.areaHit(l, p);
    }

    Editor.layerTypes.erase = {
        node: maskNode,
        hit: hitArea,
        name: function () { return t('layer_erase'); },
        panel: function (body, layers) {
            if (layers.length === 1) {
                this.erasePanel(body, layers);
            }
        }
    };

    Editor.layerTypes.adjust = {
        node: maskNode,
        hit: hitArea,
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
    function pushArea(doc, type, extra, rings, soft) {
        var geo = M.areaLayer(rings), feather = doc.selection ? (doc.selection.feather || 0) : 0, matte = (soft === undefined) ? M.selectionMatte(doc.selection) : soft;

        if (!geo) {
            return null;
        }

        var l = Object.assign({ type: type }, geo, extra || {});

        if (feather) {
            l.feather = feather;
        }

        // A subject's soft edge: the mask follows the picture under it.
        if (matte) {
            l.matte = matte;
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

    // The picture inside the selection, with its colour step, as a picture
    // layer in place (a PNG the save keeps as a file); redacted and erased
    // areas stay out of it. Given rings (a subject found without a
    // selection) it cuts those, with soft as their soft edge. Null without
    // an area or when the picture cannot be read back (the panel then says
    // so).
    Editor.prototype.areaCutout = function (given, soft) {
        var sel = given ? null : this.doc.selection, rings = given || this.areaRings(), geo = M.areaLayer(rings), img = this.img, src = this.doc.source;

        if (!geo || !img || !src) {
            return null;
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

            var shape = Object.assign({ feather: sel ? (sel.feather || 0) : 0 }, geo), matte = given ? (soft || 0) : M.selectionMatte(sel);

            g.globalCompositeOperation = 'destination-in';

            if (matte) {
                // The soft edge follows the picture just drawn.
                g.drawImage(matteCanvas(canvas.width, canvas.height, function (mg) {
                    mg.setTransform(k, 0, 0, k, -geo.x * k, -geo.y * k);
                    X.areaShapes.paint(mg, shape, k);
                }, g.getImageData(0, 0, canvas.width, canvas.height).data, matte * k), 0, 0);
            } else {
                g.save();
                at();
                X.areaShapes.paint(g, shape, k);
                g.restore();
            }

            g.globalCompositeOperation = 'destination-out';
            this.doc.layers.forEach(function (l) {
                if (!l.hidden && ((l.type === 'redact') || eraseClear(l))) {
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
            return null;
        }

        this.areaCopyFailed = false;

        return { type: 'image', src: url, fileName: t('areaCutName') + '.png', x: geo.x, y: geo.y, w: geo.w, h: geo.h, rot: 0, opacity: 1 };
    };

    // Copy to a layer: the cut-out on top, the picture as it was.
    Editor.prototype.areaCopy = function () {
        var piece = this.areaCutout();

        if (!piece) {
            return;
        }

        this.addLayers([piece], 0);
        this.areaShow(this.sel[0]);
        this.history.key = null;
    };

    // Cut to a layer: the cut-out on top and the same outline erased from
    // the picture under it, in one undo step, so the piece moves on its own
    // and leaves a see-through hole. Undo, or deleting the erased area,
    // brings the uncut picture back; the saved versions keep it too.
    Editor.prototype.areaCut = function () {
        var rings = this.areaRings(), piece = this.areaCutout(), id = null;

        if (!piece) {
            return;
        }

        this.change(function (doc) {
            pushArea(doc, 'erase', null, rings);
            piece.id = M.newId(doc);
            doc.layers.push(piece);
            id = piece.id;
            delete doc.selection;
        });
        this.history.key = null;
        this.areaShow(id);
    };

    // A w x h canvas whose alpha is the shape draw(g) paints, its edge
    // softened along the picture guide (RGBA bytes, w x h) by the guided
    // filter with radius r pixels.
    function matteCanvas(w, h, draw, guide, r) {
        var canvas = document.createElement('canvas'), g;

        canvas.width = w;
        canvas.height = h;
        g = canvas.getContext('2d');
        draw(g);
        g.setTransform(1, 0, 0, 1, 0, 0);

        var data = g.getImageData(0, 0, w, h);

        M.matteMask(data.data, guide, w, h, r);
        g.putImageData(data, 0, 0);

        return canvas;
    }

    // The picture as it lies under a w x h canvas that place(g) sets up
    // over the turned space, as RGBA bytes; null when it cannot be read
    // back (the mask then keeps its hard edge).
    Editor.prototype.areaGuide = function (w, h, place) {
        var img = this.img, src = this.doc.source, m = M.placedMatrix(this.doc), canvas = document.createElement('canvas'), g;

        if (!img || !src) {
            return null;
        }

        canvas.width = w;
        canvas.height = h;
        g = canvas.getContext('2d');
        place(g);
        g.transform(m[0], m[1], m[2], m[3], m[4], m[5]);
        g.drawImage(img, 0, 0, src.width, src.height);

        try {
            return g.getImageData(0, 0, w, h).data;
        } catch (err) {
            // A picture from another site cannot be read back.
            return null;
        }
    };

    // A Files picture as the save's fill of an erased area: drawn over the
    // picture's frame as the stage shows it, the region r of the output
    // (place(g) sets it up), a JPEG; null when it is not loaded or cannot
    // be read back (the area is then see-through).
    Editor.prototype.areaBackdrop = function (l, r, place) {
        var img = this.pictures[l.backdrop.src], canvas = document.createElement('canvas'), g, at;

        if (!img || !img.naturalWidth) {
            return null;
        }

        at = backdropBox(M.pictureBox(this.doc), img.naturalWidth, img.naturalHeight);
        canvas.width = r.w;
        canvas.height = r.h;
        g = canvas.getContext('2d');
        g.fillStyle = '#ffffff';
        g.fillRect(0, 0, r.w, r.h);
        place(g);
        g.drawImage(img, at.x, at.y, at.w, at.h);

        try {
            return canvas.toDataURL('image/jpeg', 0.92);
        } catch (err) {
            return null;
        }
    };

    // Remove background: the subject (cut first when the selection holds
    // none) is kept, the rest of the picture's frame erased, in one undo
    // step; the erased area's panel then offers its fills.
    Editor.prototype.areaRemoveBackground = function () {
        var self = this, px;

        if (this.subjectOp()) {
            this.areaEraseRest(this.areaRings(), M.selectionMatte(this.doc.selection));
            return;
        }

        px = this.areaPixelsAt(SUBJECT_SIDE);
        this.subjectNote = null;

        if (!px) {
            this.subjectNote = 'subjectRead';
            this.render();
            return;
        }

        this.subjectCut(px, this.subjectBox(px), null, function (out, rings) {
            if (!rings.length) {
                self.subjectNote = 'subjectNone';
                self.render();
                return;
            }

            self.areaEraseRest(rings, SUBJECT_EDGE);
        });
    };

    // The subject (rings, with soft edge matte) taken out of the picture in
    // one undo step: it becomes a picture layer of its own (the cut-out Cut
    // to a layer makes) over the picture erased whole, so dragging it moves
    // the subject alone and leaves nothing of the picture behind. The erased
    // area, locked so a drag on the background cannot move it, opens with
    // its fills (see-through, a colour, a blur, a picture), which now lie
    // under the whole subject; a press on the subject takes the piece.
    Editor.prototype.areaEraseRest = function (rings, matte) {
        var frame = M.areaRest([], M.pictureBox(this.doc)), piece = rings.length ? this.areaCutout(rings, matte) : null, id = null;

        if (!piece || (M.ringsArea(frame) < 1)) {
            return;
        }

        this.change(function (doc) {
            delete doc.selection;
            id = pushArea(doc, 'erase', { locked: true }, frame, 0);
            piece.id = M.newId(doc);
            doc.layers.push(piece);
        });
        this.history.key = null;
        this.areaShow(id);
    };

    // One mask per visible Erase or Adjust inside layer, drawn at the output
    // size over its own box, in the layers' order.
    Editor.prototype.areaMasks = function () {
        var self = this, box = M.pictureBox(this.doc), out = M.outputSize(this.doc), list = [];

        this.doc.layers.forEach(function (l) {
            var r = (isMaskLayer(l) && !l.hidden && ((l.type === 'erase') || areaColor(l))) ? M.redactRegion(l, box, out.w, out.h) : null;

            if (!r) {
                return;
            }

            var canvas = document.createElement('canvas'), g, entry, sx = out.w / box.w, sy = out.h / box.h;
            var place = function (c) { c.setTransform(sx, 0, 0, sy, -box.x * sx - r.x, -box.y * sy - r.y); };
            var guide = l.matte ? self.areaGuide(r.w, r.h, place) : null;

            if (guide) {
                canvas = matteCanvas(r.w, r.h, function (mg) {
                    place(mg);
                    mg.globalAlpha = (l.opacity === undefined) ? 1 : l.opacity;
                    X.areaShapes.paint(mg, l, sx);
                }, guide, l.matte * sx);
            } else {
                canvas.width = r.w;
                canvas.height = r.h;
                g = canvas.getContext('2d');
                place(g);
                // The layer's opacity weakens the area's effect.
                g.globalAlpha = (l.opacity === undefined) ? 1 : l.opacity;
                X.areaShapes.paint(g, l, sx);
            }

            entry = { op: (l.type === 'erase') ? 'erase' : 'color', x: r.x, y: r.y, mask: canvas.toDataURL('image/png') };

            if (l.type === 'erase') {
                var drawn = l.backdrop ? self.areaBackdrop(l, r, place) : null;

                if (drawn) {
                    entry.picture = drawn;
                } else if (l.blur) {
                    entry.blur = Math.min(250, Math.max(0.5, Math.round(l.blur * sx * 100) / 100));
                } else if (l.fill) {
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
        return this.doc.layers.some(function (l) { return eraseClear(l) && !l.hidden; });
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

        var subject = button('pgie-btn', t(this.subjectBusy ? 'subjectBusy' : 'subjectSelect'), null, function () { self.areaSubject(); }, true);

        subject.disabled = !!this.subjectBusy;
        subject.setAttribute('aria-busy', this.subjectBusy ? 'true' : 'false');
        var removeBg = button('pgie-btn', t('removeBg'), null, function () { self.areaRemoveBackground(); }, true);

        removeBg.disabled = !!this.subjectBusy;
        body.appendChild(el('div', { class: 'pgie-arow' }, [subject, removeBg]));
        body.appendChild(el('p', { class: 'pgie-note' + (this.subjectNote ? ' pgie-warn' : ''), text: t(this.subjectNote || 'subjectNote') }));

        var subjectOp = this.subjectOp();

        if (subjectOp) {
            this.subjectPanel(body, subjectOp);
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
        act(t('areaCut'), function () { self.areaCut(); });
        act(t('areaAdjust'), function () { self.areaAdjust(); });
        act(t('areaRedact'), function () { self.areaRedact(); }, !this.newRedaction);
        body.appendChild(section(t('areaWith'), acts));

        if (this.areaCopyFailed) {
            body.appendChild(el('p', { class: 'pgie-note pgie-warn', text: t('areaCopyNote') }));
        }
    };

    // The subject's brushes and soft edge, while the selection holds one.
    Editor.prototype.subjectPanel = function (body, op) {
        var self = this, d = this.areaDefaults, brush = (d.kind === 'keep') || (d.kind === 'remove');

        body.appendChild(section(t('subjectRefine'), this.drawSegment([['keep', t('subjectKeep')], ['remove', t('subjectRemove')]],
            brush ? d.kind : null, function (v) { d.kind = v; self.areaPoly = null; self.render(); })));

        if (brush) {
            body.appendChild(this.drawSlider(t('subjectBrush'), 4, 120, d.brush, function (v) { return v + ' px'; }, function (v) { d.brush = v; }));
            body.appendChild(el('p', { class: 'pgie-note', text: t(this.subjectBusy ? 'subjectRefining' : 'subjectBrushNote') }));
        }

        body.appendChild(this.drawSlider(t('subjectEdge'), 0, M.SUBJECT_EDGE_MAX, Math.round(Number(op.edge) || 0), function (v) { return v + ' px'; }, function (v, live) {
            self.change(function (doc) {
                var i = M.subjectIndex(doc.selection);

                if (i >= 0) {
                    doc.selection.ops[i].edge = v;
                }
            }, 'subject-edge', live);
        }));
    };

    Editor.panels.area = Editor.prototype.areaPanel;
    Editor.hints.area = 'hintArea';
    Editor.pointers.area = { down: Editor.prototype.areaDown, move: Editor.prototype.areaMove, up: Editor.prototype.areaUp, double: Editor.prototype.areaDouble };
    Editor.views.push(Editor.prototype.renderArea);
}(window.PgImageEditor.parts));
