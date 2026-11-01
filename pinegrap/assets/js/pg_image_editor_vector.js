/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the vector pen: Bezier paths drawn point by point, their
 * points and handles edited, open or closed, and drawn shapes converted to
 * paths.
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

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, find = X.find, t = X.t, el = X.el, icon = X.icon, button = X.button;

    X.icons.smooth = 'M4 16c4-10 12-10 16 0';
    X.icons.sharp = 'M4 18l8-12 8 12';

    // Kinds a path can be made from (convert to path).
    var SHAPES = M.DRAW_KINDS;

    // The open editor, for the path type's box flag below.
    function current() {
        return window.PgImageEditor ? window.PgImageEditor.current() : null;
    }

    function svg(tag, attrs) {
        var n = document.createElementNS(SVGNS, tag);

        Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });

        return n;
    }

    // ---- the layer type ----

    // A path draws, hits and fills like a drawn shape (the model's shape
    // functions know it); while its points are edited, the points and the
    // chosen point's handles replace the box's handles.
    var pathType = {
        node: Editor.layerTypes.rect.node,
        paint: Editor.layerTypes.rect.paint,
        hit: M.hitShape,
        panel: function (body, layers) { this.drawStyle(body, layers, 'path'); },
        handles: function (l) {
            var out = [], self = this;

            if (this.nodeEdit !== l.id) {
                return out;
            }

            M.pathKnots(l).forEach(function (k, i) {
                var fx = function (x) { return l.mirror ? l.w - x : x; };

                if (i === self.knot) {
                    if ((k.ix !== k.x) || (k.iy !== k.y)) {
                        out.push({ h: 'i' + i, x: fx(k.ix), y: k.iy });
                    }

                    if ((k.ox !== k.x) || (k.oy !== k.y)) {
                        out.push({ h: 'o' + i, x: fx(k.ox), y: k.oy });
                    }
                }

                out.push({ h: 'k' + i, x: fx(k.x), y: k.y });
            });

            return out;
        },
        // A point moves with its handles; a handle of a smooth point turns
        // the other one too, unless Alt breaks them apart.
        drag: function (start, h, x, y, e) {
            var q = M.drawnLocal(start, x, y), knots = M.pathKnots(start), i = Number(h.slice(1)), k = knots[i];

            if (!k) {
                return null;
            }

            this.knot = i;

            if (h.charAt(0) === 'k') {
                knots[i] = M.moveKnot(k, q[0], q[1]);
            } else {
                knots[i] = M.moveHandle(e.altKey ? Object.assign({}, k, { s: false }) : k, h.charAt(0), q[0], q[1]);
            }

            return M.refitPath(start, knots);
        }
    };

    Object.defineProperty(pathType, 'box', {
        get: function () {
            var ed = current();

            return !(ed && ed.nodeEdit);
        }
    });

    Editor.layerTypes.path = pathType;

    // ---- drawing a path ----

    // The points of the path being drawn, without a last one a double click
    // put on top of the one before.
    function draftKnots(draft, k) {
        var ks = draft.knots.slice();

        while ((ks.length > 1) && (Math.hypot(ks[ks.length - 1].x - ks[ks.length - 2].x, ks[ks.length - 1].y - ks[ks.length - 2].y) * k < 4)) {
            ks.pop();
        }

        return ks;
    }

    // The path being drawn becomes a layer in the tool's style; fewer than
    // two points draw nothing.
    Editor.prototype.finishPath = function (closed, draft) {
        var self = this, d = draft || this.vdraft, knots = d ? draftKnots(d, this.view.k) : [], id = null;

        this.vdraft = null;

        if (knots.length < 2) {
            this.render();
            return;
        }

        this.change(function (doc) {
            var l = self.newShape('path'), geo = M.pathLayer(knots, closed && (knots.length > 2));

            Object.keys(geo).forEach(function (key) { l[key] = geo[key]; });
            l.id = M.newId(doc);
            id = l.id;
            doc.layers.push(l);
        });
        this.history.key = null;
        this.select([id]);
    };

    Editor.prototype.cancelPath = function () {
        this.vdraft = null;
        this.render();
    };

    // Editing the points of one path, or not.
    Editor.prototype.editPoints = function (id) {
        this.nodeEdit = id;
        this.knot = null;

        if (id) {
            this.sel = [id];
        }

        this.render();
    };

    Editor.prototype.vectorDown = function (e) {
        var h = e.target.getAttribute && e.target.getAttribute('data-h'), p = this.toDoc(e.clientX, e.clientY), k = this.view.k;

        if (this.nodeEdit) {
            var l = this.layer(this.nodeEdit);

            if (h && (this.sel.length === 1)) {
                if (/^[kio]\d+$/.test(h)) {
                    this.knot = Number(h.slice(1));
                }

                this.startDrag(e);
                return;
            }

            if (l && M.hitLayer({ layers: [l] }, p[0], p[1], 4 / k, function (o, q, slack) { return M.hitShape(o, q, slack); })) {
                this.startDrag(e);
                return;
            }

            this.editPoints(null);
            return;
        }

        if (!this.vdraft) {
            if (h && (this.sel.length === 1)) {
                this.startDrag(e);
                return;
            }

            this.vdraft = { knots: [] };
            this.sel = [];
        }

        var ks = this.vdraft.knots;

        // A press on the first point closes the path.
        if ((ks.length > 2) && (Math.hypot(p[0] - ks[0].x, p[1] - ks[0].y) * k < 10)) {
            this.finishPath(true);
            return;
        }

        ks.push({ x: p[0], y: p[1], ix: p[0], iy: p[1], ox: p[0], oy: p[1], s: false });
        this.drag = { kind: 'vknot' };
        e.preventDefault();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }

        this.render();
    };

    // Dragging from a new point pulls its handles: a curve through it.
    Editor.prototype.vectorMove = function (e) {
        if (this.drag.kind !== 'vknot') {
            this.moveDrag(e);
            return;
        }

        var p = this.toDoc(e.clientX, e.clientY), ks = this.vdraft ? this.vdraft.knots : [], a = ks[ks.length - 1];

        if (!a || (Math.hypot(p[0] - a.x, p[1] - a.y) * this.view.k < 3)) {
            return;
        }

        ks[ks.length - 1] = { x: a.x, y: a.y, ix: 2 * a.x - p[0], iy: 2 * a.y - p[1], ox: p[0], oy: p[1], s: true };
        this.renderView();
    };

    Editor.prototype.vectorUp = function (e) {
        if (this.drag.kind !== 'vknot') {
            this.endDrag(e);
            return;
        }

        this.drag = null;
        this.renderView();
    };

    // A double click ends the path being drawn, adds a point on the path
    // whose points are edited, or starts editing the points of a path.
    Editor.prototype.vectorDouble = function (e) {
        var self = this, p = this.toDoc(e.clientX, e.clientY), k = this.view.k, l;

        if (this.vdraft) {
            this.finishPath(false);
            return true;
        }

        if (this.nodeEdit) {
            l = this.layer(this.nodeEdit);

            var q = l ? M.drawnLocal(l, p[0], p[1]) : null, knots = l ? M.pathKnots(l) : [], near = l ? M.nearestOnPath(knots, !!l.closed, q) : null;

            if (near && (near.seg >= 0) && (near.d * k < 8)) {
                this.change(function (doc) {
                    var o = find(doc, l.id), fit = M.refitPath(o, M.splitAt(knots, !!l.closed, near.seg, near.t));

                    Object.keys(fit).forEach(function (key) { o[key] = fit[key]; });
                });
                this.knot = near.seg + 1;
                this.render();
            }

            return true;
        }

        l = M.hitLayer(this.doc, p[0], p[1], 4 / k, function (o, q, slack) { return (o.type === 'path') && M.hitShape(o, q, slack); });

        if (l) {
            self.editPoints(l.id);
            return true;
        }

        return false;
    };

    // The keys while the pen is the tool: Enter ends a path or the editing;
    // Delete removes the chosen point (a path keeps two).
    Editor.prototype.vectorKey = function (e, mod, k) {
        var self = this;

        if ((this.tool !== 'vector') || mod) {
            return false;
        }

        if (k === 'Enter' && this.vdraft) {
            this.finishPath(false);
            return true;
        }

        if (k === 'Enter' && this.nodeEdit) {
            this.editPoints(null);
            return true;
        }

        if ((k === 'Delete' || k === 'Backspace') && this.nodeEdit && (this.knot !== null)) {
            var l = this.layer(this.nodeEdit), knots = l ? M.pathKnots(l) : [];

            if (knots.length > 2) {
                knots.splice(this.knot, 1);
                this.change(function (doc) {
                    var o = find(doc, l.id), fit = M.refitPath(o, knots);

                    Object.keys(fit).forEach(function (key) { o[key] = fit[key]; });

                    if (knots.length < 3) {
                        o.closed = false;
                    }
                });
                self.knot = null;
                this.render();
            }

            return true;
        }

        return false;
    };

    // Escape drops the path being drawn, or ends the editing.
    Editor.prototype.vectorEscape = function () {
        if (this.vdraft) {
            this.cancelPath();
            return true;
        }

        if (this.nodeEdit) {
            this.editPoints(null);
            return true;
        }

        return false;
    };

    // The path being drawn, and the chosen point's handle lines, over the
    // stage; another tool keeps a path being drawn and ends the editing.
    Editor.prototype.renderVector = function () {
        var self = this, v = this.view, vr = this.viewRect(), k = v.k;
        var sx = function (x) { return v.x + (x - vr.x) * k; }, sy = function (y) { return v.y + (y - vr.y) * k; };

        if (this.tool !== 'vector') {
            if (this.vdraft) {
                var draft = this.vdraft;

                this.vdraft = null;
                setTimeout(function () { self.finishPath(false, draft); }, 0);
            }

            if (this.nodeEdit) {
                this.nodeEdit = null;
                this.knot = null;
            }

            return;
        }

        if (this.nodeEdit) {
            this.hint.textContent = t('hintNodes');
        }

        if (!this.ready) {
            return;
        }

        var over = svg('svg', { class: 'pgie-vover', 'aria-hidden': 'true' }), l = this.nodeEdit ? this.layer(this.nodeEdit) : null;

        if (this.vdraft && this.vdraft.knots.length) {
            var screen = this.vdraft.knots.map(function (o) {
                return { x: sx(o.x), y: sy(o.y), ix: sx(o.ix), iy: sy(o.iy), ox: sx(o.ox), oy: sy(o.oy), s: o.s };
            });

            over.appendChild(svg('path', { d: M.knotsD(screen, false), class: 'pgie-vpath' }));
            screen.forEach(function (o, i) {
                if (o.s && (i === screen.length - 1)) {
                    over.appendChild(svg('path', { d: 'M' + o.ix + ' ' + o.iy + 'L' + o.ox + ' ' + o.oy, class: 'pgie-vline' }));
                    over.appendChild(svg('circle', { cx: o.ox, cy: o.oy, r: 3.5, class: 'pgie-vhandle' }));
                    over.appendChild(svg('circle', { cx: o.ix, cy: o.iy, r: 3.5, class: 'pgie-vhandle' }));
                }

                over.appendChild(svg('rect', { x: o.x - 4, y: o.y - 4, width: 8, height: 8, class: 'pgie-vknot' + (i ? '' : ' pgie-vfirst') }));
            });
        }

        if (l && (this.knot !== null)) {
            var kn = M.pathKnots(l)[this.knot];

            if (kn) {
                var at = function (x, y) { var q = M.drawnPoint(l, x, y); return sx(q[0]) + ' ' + sy(q[1]); };

                over.appendChild(svg('path', { d: 'M' + at(kn.ix, kn.iy) + 'L' + at(kn.x, kn.y) + 'L' + at(kn.ox, kn.oy), class: 'pgie-vline' }));
            }
        }

        if (over.firstChild) {
            this.selEl.insertBefore(over, this.selEl.firstChild);
        }
    };

    // ---- the panel ----

    // Every point of a path smooth or a corner, or only the chosen one.
    Editor.prototype.shapeKnots = function (l, smooth) {
        var self = this, knots = M.pathKnots(l), ids = (this.nodeEdit === l.id && this.knot !== null) ? [this.knot] : knots.map(function (k, i) { return i; });

        ids.forEach(function (i) {
            knots = smooth ? M.smoothKnot(knots, i, !!l.closed) : M.cornerKnot(knots, i);
        });

        this.change(function (doc) {
            var o = find(doc, l.id), fit = M.refitPath(o, knots);

            Object.keys(fit).forEach(function (key) { o[key] = fit[key]; });
        });
        self.render();
    };

    // The selected drawn shapes become paths of the same look.
    Editor.prototype.convertToPaths = function () {
        var ids = this.selected().filter(function (l) { return !l.locked && (SHAPES.indexOf(l.type) >= 0); }).map(function (l) { return l.id; });

        this.change(function (doc) {
            ids.forEach(function (id) {
                var l = find(doc, id), sk = M.shapeKnots(l), fit = M.refitPath(l, sk.knots), closed = sk.closed;

                ['radius', 'sides', 'points', 'inner', 'heads', 'head', 'smooth', 'trace'].forEach(function (key) { delete l[key]; });

                if (!closed) {
                    delete l.fill;
                }

                Object.keys(fit).forEach(function (key) { l[key] = fit[key]; });
                l.type = 'path';
                l.closed = closed;
            });
        });
        this.render();
    };

    Editor.prototype.vectorPanel = function (body) {
        var self = this, picked = this.selected(), path = ((picked.length === 1) && (picked[0].type === 'path')) ? picked[0] : null;
        var shapes = picked.filter(function (l) { return !l.locked && (SHAPES.indexOf(l.type) >= 0); });

        if (this.vdraft) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('vectorNote') }));
            body.appendChild(el('div', { class: 'pgie-actions' }, [
                button('pgie-btn', t('cancel'), null, function () { self.cancelPath(); }, true),
                button('pgie-btn pgie-primary', t('finishPath'), null, function () { self.finishPath(false); }, true)
            ]));
            return;
        }

        if (path) {
            var editing = (this.nodeEdit === path.id), seg = el('div', { class: 'pgie-seg' });
            var toggle = button('pgie-btn pgie-wide' + (editing ? ' on' : ''), editing ? t('stopEditing') : t('editPoints'), 'vector', function () {
                self.editPoints(editing ? null : path.id);
            }, true);

            toggle.setAttribute('aria-pressed', editing ? 'true' : 'false');
            body.appendChild(toggle);

            [['smooth', t('knotSmooth'), function () { self.shapeKnots(path, true); }], ['sharp', t('knotSharp'), function () { self.shapeKnots(path, false); }]].forEach(function (b) {
                seg.appendChild(el('button', { type: 'button', onclick: b[2] }, [icon(b[0]), el('span', { text: b[1] })]));
            });
            seg.appendChild(el('button', { type: 'button', class: path.closed ? 'on' : '', 'aria-pressed': path.closed ? 'true' : 'false', text: t('pathClosed'), onclick: function () {
                if (path.closed || (path.knots.length > 2)) {
                    self.change(function (doc) {
                        var o = find(doc, path.id), knots = M.pathKnots(o);

                        o.closed = !o.closed;

                        var fit = M.refitPath(o, knots);

                        Object.keys(fit).forEach(function (key) { o[key] = fit[key]; });
                    });
                }
            } }));
            body.appendChild(seg);

            if (editing) {
                body.appendChild(el('p', { class: 'pgie-note', text: t('nodesNote') }));
            }

            this.drawStyle(body, [path], 'path');
            body.appendChild(el('div', { class: 'pgie-btnrow' }, [
                button('pgie-ibtn', t('duplicate'), 'duplicate', function () { self.addLayers(self.selected(), 10); }),
                button('pgie-ibtn', t('bringForward'), 'forward', function () { self.change(function (doc) { doc.layers = M.reorder(doc.layers, self.sel, 'up'); }); }),
                button('pgie-ibtn', t('sendBackward'), 'backward', function () { self.change(function (doc) { doc.layers = M.reorder(doc.layers, self.sel, 'down'); }); }),
                button('pgie-ibtn', t('delete'), 'trash', function () { self.editPoints(null); self.removeLayers(); })
            ]));
            return;
        }

        if (shapes.length) {
            body.appendChild(button('pgie-btn pgie-wide', t('convertToPath'), 'vector', function () { self.convertToPaths(); }, true));
        }

        body.appendChild(el('p', { class: 'pgie-note', text: t('vectorNote') }));
        this.drawStyle(body, null, 'path');
    };

    Editor.inits.push(function () {
        this.vdraft = null;
        this.nodeEdit = null;
        this.knot = null;
    });

    Editor.panels.vector = Editor.prototype.vectorPanel;
    Editor.hints.vector = 'hintVector';
    Editor.pointers.vector = { down: Editor.prototype.vectorDown, move: Editor.prototype.vectorMove, up: Editor.prototype.vectorUp, double: Editor.prototype.vectorDouble };
    Editor.selectTools.push('vector');
    // Before the selection's keys, so Delete removes a point, not the path.
    Editor.keys.unshift(Editor.prototype.vectorKey);
    Editor.escapes.push(Editor.prototype.vectorEscape);
    Editor.views.push(Editor.prototype.renderVector);
}(window.PgImageEditor.parts));
