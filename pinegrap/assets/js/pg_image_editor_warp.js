/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the warp: a path, a drawn shape or outlined text bent as a
 * whole by a preset (arc, arch, bulge, flag, wave, fish, rise, squeeze,
 * twist with bend and distortion) or a free envelope mesh; kept as the
 * layer's warp and baked into the path by Expand.
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

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, find = X.find, t = X.t, el = X.el, icon = X.icon, button = X.button, section = X.section;

    X.icons.warp = 'M4 8c5-4 11-4 16 0M4 16c5-4 11-4 16 0M4 8v8M20 8v8';
    X.icons.warp_none = 'M5 7h14v10H5z';
    X.icons.warp_arc = 'M4 13a8 8 0 0 1 16 0M7.5 17.5a4.5 4.5 0 0 1 9 0M4 13l3.5 4.5M20 13l-3.5 4.5';
    X.icons.warp_arch = 'M4 9c5-5 11-5 16 0M4 19c5-5 11-5 16 0M4 9v10M20 9v10';
    X.icons.warp_bulge = 'M4 7c5-3 11-3 16 0M4 17c5 3 11 3 16 0M4 7v10M20 7v10';
    X.icons.warp_flag = 'M4 7c3-3 5 3 8 0s5-3 8 0M4 17c3-3 5 3 8 0s5-3 8 0M4 7v10M20 7v10';
    X.icons.warp_wave = 'M4 8c3-4 5 4 8 0s5-4 8 0M4 16c3 4 5-4 8 0s5 4 8 0M4 8v8M20 8v8';
    X.icons.warp_fish = 'M4 6c4-1 8 2 16 5M4 18c4 1 8-2 16-5M4 6v12M20 11v2';
    X.icons.warp_rise = 'M4 11c6 0 10-3 16-7M4 20c6 0 10-3 16-7M4 11v9M20 4v9';
    X.icons.warp_squeeze = 'M4 6c5 4 11 4 16 0M4 18c5-4 11-4 16 0M4 6v12M20 6v12';
    X.icons.warp_twist = 'M5 6c8 0 6 12 14 12M5 18c8 0 6-12 14-12';
    X.icons.warp_mesh = 'M4 5h16v14H4zM4 12c5-2 11 2 16 0M12 5c-2 5 2 9 0 14';

    // A shape's own fields, dropped when Expand makes it a path.
    var SHAPE_FIELDS = ['radius', 'sides', 'points', 'inner', 'heads', 'head', 'smooth', 'trace'];

    // A new preset's bend; a new mesh's size.
    var BEND = 0.5;
    var MESH = 4;

    function svg(tag, attrs) {
        var n = document.createElementNS(SVGNS, tag);

        Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });

        return n;
    }

    function pct(v) {
        return Math.round((Number(v) || 0) * 100);
    }

    function shownPct(n) {
        return ((n > 0) ? '+' : '') + n + ' %';
    }

    // The one selected layer the warp works on, or null.
    Editor.prototype.warpTarget = function () {
        var picked = this.selected();

        return ((picked.length === 1) && !picked[0].locked && M.warpable(picked[0])) ? picked[0] : null;
    };

    // The warp of layer id changed by edit(warp): the new warp, or null to
    // drop it. key joins a slider's moves into one undo step.
    Editor.prototype.setWarp = function (id, edit, key, live) {
        this.change(function (doc) {
            var l = find(doc, id), next = l ? edit(l.warp ? M.clone(l.warp) : null) : null;

            if (!l) {
                return;
            }

            if (next) {
                l.warp = next;
            } else {
                delete l.warp;
            }
        }, key || null, !!live);
    };

    // A style picked from the grid: a preset keeps the bend it had, a mesh
    // starts even.
    Editor.prototype.pickWarp = function (l, style) {
        this.setWarp(l.id, function (wp) {
            if (style === 'none') {
                return null;
            }

            if (style === 'mesh') {
                return (wp && (wp.style === 'mesh')) ? wp : M.meshGrid(MESH, MESH);
            }

            var keep = (wp && (wp.style !== 'mesh')) ? wp : { bend: BEND, h: 0, v: 0 };

            return { style: style, bend: keep.bend, h: keep.h || 0, v: keep.v || 0 };
        });
    };

    // Expand: the selected layer's drawn outline becomes its path, the
    // warp and the free deformation's pins gone; one undo step.
    Editor.prototype.expandWarp = function (l) {
        if (!M.warpOn(l) && !M.puppetOn(l)) {
            return;
        }

        this.change(function (doc) {
            var o = find(doc, l.id), fit = o ? M.warpExpand(o) : null;

            if (!fit) {
                return;
            }

            SHAPE_FIELDS.forEach(function (key) { delete o[key]; });
            delete o.warp;
            delete o.puppet;
            delete o.subs;

            if (!fit.closed) {
                delete o.fill;
            }

            Object.keys(fit).forEach(function (key) { o[key] = fit[key]; });
        });
    };

    // ---- the panel ----

    Editor.prototype.warpPanel = function (body) {
        var self = this, picked = this.selected(), l = this.warpTarget(), wp = l ? l.warp : null, on = l ? M.warpOn(l) : false;
        var style = on ? wp.style : 'none';

        if (!l) {
            if ((picked.length === 1) && (picked[0].type === 'text') && !picked[0].locked) {
                body.appendChild(el('p', { class: 'pgie-note', text: t('warpTextNote') }));

                // Text to outlines lives in its own part; the button shows
                // where that part is bundled.
                if (typeof this.convertToOutlines === 'function') {
                    var conv = button('pgie-btn pgie-wide', t('warpToOutlines'), 'vector', function () { self.convertToOutlines(conv); }, true);

                    body.appendChild(conv);
                }

                return;
            }

            body.appendChild(el('p', { class: 'pgie-note', text: t('warpNote') }));
            return;
        }

        var grid = el('div', { class: 'pgie-kinds', role: 'group', 'aria-label': t('warpStyles') });

        ['none'].concat(M.WARP_STYLES, ['mesh']).forEach(function (s) {
            var now = (s === style), name = t('warp_' + s);

            grid.appendChild(el('button', { type: 'button', class: 'pgie-kind' + (now ? ' on' : ''), 'aria-pressed': now ? 'true' : 'false', title: name, onclick: function () {
                if (!now) {
                    self.pickWarp(l, s);
                }
            } }, [icon('warp_' + s), el('span', { text: name })]));
        });
        body.appendChild(grid);

        if (on && (style !== 'mesh')) {
            [['bend', 'warpBend'], ['h', 'warpHorizontal'], ['v', 'warpVertical']].forEach(function (f) {
                body.appendChild(self.drawSlider(t(f[1]), -100, 100, pct(wp[f[0]]), shownPct, function (n, live) {
                    self.setWarp(l.id, function (w) {
                        w[f[0]] = n / 100;

                        return w;
                    }, 'warp-' + f[0], live);
                }));
            });
        }

        if (on && (style === 'mesh')) {
            [['cols', 'warpCols'], ['rows', 'warpRows']].forEach(function (f) {
                body.appendChild(self.drawSlider(t(f[1]), M.MESH_MIN, M.MESH_MAX, wp[f[0]], String, function (n, live) {
                    self.setWarp(l.id, function (w) {
                        return M.meshResize(w, (f[0] === 'cols') ? n : w.cols, (f[0] === 'rows') ? n : w.rows);
                    }, 'warp-' + f[0], live);
                }));
            });
            body.appendChild(el('p', { class: 'pgie-note', text: t('warpMeshNote') }));
            body.appendChild(button('pgie-btn pgie-wide', t('warpReset'), 'warp_mesh', function () {
                self.setWarp(l.id, function (w) { return M.meshGrid(w.cols, w.rows); });
            }, true));
        }

        if (on) {
            body.appendChild(section(t('warpExpand'), el('div', {}, [
                el('p', { class: 'pgie-note', text: t('warpExpandNote') }),
                button('pgie-btn pgie-wide', t('warpExpand'), 'vector', function () { self.expandWarp(l); }, true)
            ])));
        }
    };

    // ---- the mesh on the stage ----

    // The mesh's points in the layer's frame as the stage shows it (the
    // mirror applied), in pixels.
    function meshHandles(l) {
        var wp = l.warp;

        return wp.pts.map(function (p, i) {
            return { i: i, x: (l.mirror ? 1 - p[0] : p[0]) * l.w, y: p[1] * l.h };
        });
    }

    // A mesh row or column as the curve the mesh draws, in the frame above.
    function meshLine(l, fixed, along) {
        var pts = [], n = 24, i, q;

        for (i = 0; i <= n; i++) {
            q = along ? M.meshAt(l.warp, fixed, i / n) : M.meshAt(l.warp, i / n, fixed);
            pts.push([(l.mirror ? 1 - q[0] : q[0]) * l.w, q[1] * l.h]);
        }

        return pts;
    }

    // The mesh over the selected layer: its lines and its points, turned
    // with the layer like the selection's box.
    Editor.prototype.renderWarp = function () {
        var l = (this.tool === 'warp') && this.ready ? this.warpTarget() : null;

        if (!l || !M.warpOn(l) || (l.warp.style !== 'mesh')) {
            return;
        }

        var v = this.view, vr = this.viewRect(), k = v.k, wp = l.warp, i, d = '';
        var box = el('div', { class: 'pgie-wbox' });
        var over = svg('svg', { class: 'pgie-vover', 'aria-hidden': 'true' });
        var line = function (pts) {
            return pts.map(function (p, j) { return (j ? 'L' : 'M') + Math.round(p[0] * k * 10) / 10 + ' ' + Math.round(p[1] * k * 10) / 10; }).join('');
        };

        box.style.left = (v.x + (l.x - vr.x) * k) + 'px';
        box.style.top = (v.y + (l.y - vr.y) * k) + 'px';
        box.style.width = (l.w * k) + 'px';
        box.style.height = (l.h * k) + 'px';
        box.style.transform = l.rot ? 'rotate(' + l.rot + 'deg)' : '';

        for (i = 0; i < wp.rows; i++) {
            d += line(meshLine(l, i / (wp.rows - 1), false));
        }

        for (i = 0; i < wp.cols; i++) {
            d += line(meshLine(l, i / (wp.cols - 1), true));
        }

        over.appendChild(svg('path', { d: d, class: 'pgie-vline' }));
        box.appendChild(over);

        meshHandles(l).forEach(function (m) {
            var s = el('span', { class: 'pgie-sh pgie-shx pgie-wpt', 'data-wm': String(m.i), title: t('warpMeshPoint') });

            s.style.left = (m.x * k) + 'px';
            s.style.top = (m.y * k) + 'px';
            box.appendChild(s);
        });

        this.selEl.appendChild(box);
    };

    // ---- pointers ----

    // A press on a mesh point drags it; anything else selects and moves as
    // the Select tool does.
    Editor.prototype.warpDown = function (e) {
        var wm = e.target.getAttribute && e.target.getAttribute('data-wm'), l = this.warpTarget();

        if ((wm === null) || (wm === undefined) || !l || !M.warpOn(l) || (l.warp.style !== 'mesh')) {
            this.startDrag(e);
            return;
        }

        this.drag = { kind: 'warpmesh', i: Number(wm), start: M.clone(l) };
        e.preventDefault();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }
    };

    Editor.prototype.warpMove = function (e) {
        var d = this.drag;

        if (d.kind !== 'warpmesh') {
            this.moveDrag(e);
            return;
        }

        var p = this.toDoc(e.clientX, e.clientY), q = M.drawnLocal(d.start, p[0], p[1]), s = d.start;
        var at = [Math.round(q[0] / s.w * 1e4) / 1e4, Math.round(q[1] / s.h * 1e4) / 1e4];

        this.setWarp(s.id, function (w) {
            if (w && w.pts && w.pts[d.i]) {
                w.pts[d.i] = at;
            }

            return w;
        }, 'drag', true);
    };

    Editor.panels.warp = Editor.prototype.warpPanel;
    Editor.hints.warp = 'hintWarp';
    Editor.pointers.warp = { down: Editor.prototype.warpDown, move: Editor.prototype.warpMove, up: Editor.prototype.endDrag };
    Editor.selectTools.push('warp');
    Editor.views.push(Editor.prototype.renderWarp);
}(window.PgImageEditor.parts));
