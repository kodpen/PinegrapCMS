/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the shape builder: pathfinder operations on two or more
 * selected shapes or paths (unite, subtract front, intersect, exclude,
 * divide), and a drag across the regions overlapping shapes make, which
 * merges them (Alt removes them).
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor.js
 * (pg_js_bundles()); it hangs its code on the editor through
 * PgImageEditor.parts (docs/memory/ui/image-editor-parts.md).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */

(function (X) {
    'use strict';

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, find = X.find, t = X.t, el = X.el, button = X.button, section = X.section, tile = X.tile;

    X.icons.builder = 'M9 4a6 6 0 1 0 0 12 6 6 0 0 0 0-12zM15 8a6 6 0 1 1 0 12 6 6 0 0 1 0-12z';
    X.icons.unite = 'M4 4h10v6h6v10H10v-6H4z';
    X.icons.minusFront = 'M4 4h10v6h-4v4H4zM10 10h10v10H10z';
    X.icons.intersect = 'M4 4h10v10H4zM10 10h10v10H10zM10 10h4v4h-4z';
    X.icons.exclude = 'M4 4h10v6h-4v4H4zM14 10h6v10H10v-6h4z';
    X.icons.divide = 'M4 4h10v10H4zM10 10h10v10H10zM10 10v4h4';

    // The operations, as the model names them, and their labels' keys.
    var OPS = [['unite', 'pfUnite'], ['minusFront', 'pfMinusFront'], ['intersect', 'pfIntersect'], ['exclude', 'pfExclude'], ['divide', 'pfDivide']];

    // A drag over more shapes than this is slow: the builder takes the top
    // ones.
    var MAX_SHAPES = 12;

    // A shape's own fields, dropped when it becomes a path.
    var SHAPE_FIELDS = ['radius', 'sides', 'points', 'inner', 'heads', 'head', 'smooth', 'trace', 'mirror'];

    function svg(tag, attrs) {
        var n = document.createElementNS(SVGNS, tag);

        Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });

        return n;
    }

    // A layer the builder takes: visible, unlocked, a closed area.
    function areaOf(l) {
        return (!l.hidden && !l.locked) ? M.layerRings(l) : null;
    }

    // The selected areas bottom to top as [{ id, rings }].
    Editor.prototype.pathfinderItems = function () {
        return this.selected().map(function (l) { return { id: l.id, rings: areaOf(l) }; }).filter(function (it) { return it.rings; });
    };

    // The layers ids replaced by pieces ([{ id, rings }], each in the style
    // of layer id) in one undo step, where the top one of them was; the
    // pieces are selected.
    Editor.prototype.replaceWithPieces = function (ids, pieces) {
        var picked = [];

        if (!ids.length) {
            return;
        }

        this.change(function (doc) {
            var order = function (id) { return doc.layers.findIndex(function (l) { return l.id === id; }); };
            var top = Math.max.apply(null, ids.map(order)), used = {};
            var rest = doc.layers.filter(function (l) { return ids.indexOf(l.id) < 0; });
            var below = doc.layers.slice(0, top + 1).filter(function (l) { return ids.indexOf(l.id) < 0; }).length;
            var made = pieces.slice().sort(function (a, b) { return order(a.id) - order(b.id); }).map(function (p) {
                var o = M.clone(find(doc, p.id)), fit = M.ringsPath(p.rings);

                SHAPE_FIELDS.forEach(function (key) { delete o[key]; });
                Object.keys(fit).forEach(function (key) { o[key] = fit[key]; });
                o.type = 'path';
                o.id = used[p.id] ? null : p.id;
                used[p.id] = true;

                return o;
            });

            doc.layers = rest.slice(0, below).concat(made, rest.slice(below));

            // Copies of a style get ids once their layers are in place.
            made.forEach(function (o) {
                if (!o.id) {
                    o.id = M.newId(doc);
                }

                picked.push(o.id);
            });
        });
        this.history.key = null;
        this.select(picked);
    };

    // A pathfinder button: the selected areas combined.
    Editor.prototype.pathfinder = function (op) {
        var items = this.pathfinderItems();

        if (items.length < 2) {
            return;
        }

        this.replaceWithPieces(items.map(function (it) { return it.id; }), M.pathfinder(op, items));
    };

    // ---- the shape builder's drag ----

    // The regions the builder works on: the selected areas when two or
    // more are selected, else every visible area (the top MAX_SHAPES).
    // Kept while the layers stay as they are.
    Editor.prototype.builderRegions = function () {
        var items = this.pathfinderItems();

        if (items.length < 2) {
            items = this.doc.layers.map(function (l) { return { id: l.id, rings: areaOf(l) }; }).filter(function (it) { return it.rings; }).slice(-MAX_SHAPES);
        }

        var self = this, key = JSON.stringify(items.map(function (it) { return find(self.doc, it.id); }));

        if (!this.builder || (this.builder.key !== key)) {
            this.builder = { key: key, items: items, regions: (items.length > 1) ? M.builderRegions(items) : [] };
        }

        return this.builder.regions;
    };

    // Every visible, unlocked closed shape or path selected, so the
    // combine buttons work without Shift.
    Editor.prototype.selectShapes = function () {
        this.select(this.doc.layers.filter(function (l) { return !!areaOf(l); }).map(function (l) { return l.id; }));
    };

    // The regions a drag from a to b passes, every 2 screen px.
    Editor.prototype.builderTouch = function (a, b) {
        var d = this.drag, regions = this.builderRegions(), steps = Math.max(1, Math.ceil(Math.hypot(b[0] - a[0], b[1] - a[1]) * this.view.k / 2)), i;

        for (i = 0; i <= steps; i++) {
            var at = M.regionAt(regions, [a[0] + (b[0] - a[0]) * i / steps, a[1] + (b[1] - a[1]) * i / steps]);

            if ((at >= 0) && (d.touched.indexOf(at) < 0)) {
                d.touched.push(at);
            }
        }
    };

    Editor.prototype.builderDown = function (e) {
        var p = this.toDoc(e.clientX, e.clientY);

        this.drag = { kind: 'build', at: p, remove: this.builderRemove || !!e.altKey, touched: [] };
        this.builderTouch(p, p);
        e.preventDefault();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }

        this.renderView();
    };

    Editor.prototype.builderMove = function (e) {
        var d = this.drag, p;

        if (d && (d.kind === 'build')) {
            p = this.toDoc(e.clientX, e.clientY);
            d.remove = d.remove || !!e.altKey;
            this.builderTouch(d.at, p);
            d.at = p;
            this.renderView();
        }
    };

    // The touched regions merged, or removed with Alt. A click (one region
    // touched, merging) unites the shapes stacked there instead of cutting
    // them into that region and the rest.
    Editor.prototype.builderUp = function () {
        var d = this.drag;

        this.drag = null;

        if (!d || (d.kind !== 'build')) {
            return;
        }

        var regions = this.builderRegions();
        var out = ((d.touched.length === 1) && !d.remove) ? M.builderStack(this.builder.items, regions[d.touched[0]]) : M.builderMerge(regions, d.touched, d.remove);

        if (out.ids.length) {
            this.replaceWithPieces(out.ids, out.pieces);
        } else {
            this.renderView();
        }
    };

    // The regions' outlines while the builder is chosen; the ones a drag
    // touched filled.
    Editor.prototype.renderBuilder = function () {
        if ((this.tool !== 'builder') || !this.ready) {
            return;
        }

        var d = this.drag, v = this.view, vr = this.viewRect(), k = v.k, regions = this.builderRegions();
        var over = svg('svg', { class: 'pgie-vover', 'aria-hidden': 'true' });
        var ring = function (r) {
            return r.map(function (p, i) { return (i ? 'L' : 'M') + (v.x + (p[0] - vr.x) * k) + ' ' + (v.y + (p[1] - vr.y) * k); }).join('') + 'Z';
        };

        regions.forEach(function (g, i) {
            var hit = d && (d.kind === 'build') && (d.touched.indexOf(i) >= 0);

            over.appendChild(svg('path', {
                d: g.rings.map(ring).join(''), 'fill-rule': 'evenodd',
                class: 'pgie-bregion' + (hit ? (d.remove ? ' pgie-bremove' : ' pgie-bmerge') : '')
            }));
        });

        if (over.firstChild) {
            this.selEl.insertBefore(over, this.selEl.firstChild);
        }
    };

    Editor.prototype.builderPanel = function (body) {
        var self = this, count = this.pathfinderItems().length, tiles = el('div', { class: 'pgie-tiles pgie-pftiles' });

        OPS.forEach(function (op) {
            var b = tile(op[0], t(op[1]), function () { self.pathfinder(op[0]); });

            b.disabled = count < 2;
            tiles.appendChild(b);
        });

        body.appendChild(section(t('pathfinder'), tiles));

        // Fewer than two selected: select every shape from here.
        if ((count < 2) && (this.doc.layers.filter(function (l) { return !!areaOf(l); }).length > 1)) {
            body.appendChild(el('div', { class: 'pgie-btnrow' }, [
                button('pgie-btn', t('selectShapes'), 'select', function () { self.selectShapes(); }, true)
            ]));
        }

        // A drag merges or removes; Alt removes too, the switch is for touch.
        var seg = el('div', { class: 'pgie-seg' });

        [[false, 'builderMerge'], [true, 'builderRemove']].forEach(function (m) {
            var on = !!self.builderRemove === m[0];

            seg.appendChild(el('button', { type: 'button', class: on ? 'on' : '', 'aria-pressed': on ? 'true' : 'false', text: t(m[1]), onclick: function () {
                self.builderRemove = m[0];
                self.render();
            } }));
        });

        body.appendChild(section(t('builderDrag'), seg));
        body.appendChild(el('p', { class: 'pgie-note', text: t((count < 2) ? 'pathfinderNote' : 'builderNote') }));
    };

    Editor.inits.push(function () {
        this.builder = null;
        this.builderRemove = false;
    });

    Editor.panels.builder = Editor.prototype.builderPanel;
    Editor.hints.builder = 'hintBuilder';
    Editor.pointers.builder = { down: Editor.prototype.builderDown, move: Editor.prototype.builderMove, up: Editor.prototype.builderUp };
    Editor.views.push(Editor.prototype.renderBuilder);
}(window.PgImageEditor.parts));
