/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: drawing: the pen and eraser, lines and arrows, and the ready-made
 * shapes (box, ellipse, triangle, polygon, star, speech bubble).
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

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, find = X.find, t = X.t, el = X.el, icon = X.icon, section = X.section;
    var button = X.button, decimal = X.decimal, swatches = X.swatches;

    X.icons.triangle = 'M12 4.5l8.5 15h-17z';
    X.icons.polygon = 'M12 3.5l7.4 4.25v8.5L12 20.5l-7.4-4.25v-8.5z';
    X.icons.star = 'M12 3.5l2.6 5.5 6 .7-4.4 4.1 1.1 5.9L12 16.8l-5.3 2.9 1.1-5.9-4.4-4.1 6-.7z';
    X.icons.bubble = 'M4.5 5.5h15v10h-9l-4 3.5v-3.5h-2z';
    X.icons.eraser = 'M9 19.5h11M4.6 14.6l8.8-8.8a2 2 0 0 1 2.8 0l2.5 2.5a2 2 0 0 1 0 2.8l-7.3 7.3a2 2 0 0 1-1.4.6H8.3a2 2 0 0 1-1.4-.6l-2.3-2.3a1 1 0 0 1 0-1.5zM9 10l5.5 5.5';

    // The kinds of the Draw tool's grid, and the marking tools a caller's mini
    // bar names, each drawing one kind.
    var KINDS = M.DRAW_KINDS, MINI = ['arrow', 'line', 'rect', 'ellipse', 'pen'];

    // Which kinds a style field applies to (all when missing).
    var FIELDS = {
        fill: M.CLOSED_KINDS.concat(['path']),
        radius: ['rect', 'bubble'],
        sides: ['polygon'],
        points: ['star'],
        inner: ['star'],
        heads: ['arrow'],
        head: ['arrow'],
        smooth: ['pen']
    };

    function applies(type, field) {
        return !FIELDS[field] || (FIELDS[field].indexOf(type) >= 0);
    }

    function isDrawn(l) {
        return KINDS.indexOf(l.type) >= 0;
    }

    function isLine(type) {
        return M.LINE_KINDS.indexOf(type) >= 0;
    }

    // A press hits a layer unless its type's own test says no.
    function hitTest(l, p, slack) {
        var type = Editor.layerTypes[l.type];

        return !type || !type.hit || type.hit(l, p, slack);
    }

    // A line's box again from its ends, after its stroke or head changed:
    // the box is as thick as they are.
    function refitLine(l) {
        var ends = M.lineEnds(l), b = M.lineBox(ends[0], ends[1], false, { kind: l.type, stroke: l.stroke, head: l.head });

        l.x = b.x;
        l.y = b.y;
        l.w = b.w;
        l.h = b.h;
        l.rot = b.rot;
        delete l.mirror;
    }

    // p moved around o to the nearest step of 45 degrees.
    function snap45(o, p) {
        var a = Math.round(Math.atan2(p[1] - o[1], p[0] - o[0]) / (Math.PI / 4)) * (Math.PI / 4), len = Math.hypot(p[0] - o[0], p[1] - o[1]);

        return [o[0] + Math.cos(a) * len, o[1] + Math.sin(a) * len];
    }

    // ---- the layer types ----

    // A drawn layer as SVG: its path in its box's frame, turned around the
    // box's centre and mirrored inside it.
    function drawnNode(l) {
        var g = document.createElementNS(SVGNS, 'g'), shape = M.shapePath(l), path = document.createElementNS(SVGNS, 'path');
        var dash = M.dashFor(l), tf = 'translate(' + l.x + ' ' + l.y + ')';

        if (l.rot) {
            tf += ' rotate(' + l.rot + ' ' + (l.w / 2) + ' ' + (l.h / 2) + ')';
        }

        if (l.mirror) {
            tf += ' translate(' + l.w + ' 0) scale(-1 1)';
        }

        g.setAttribute('transform', tf);

        if ((l.opacity !== undefined) && (l.opacity < 1)) {
            g.setAttribute('opacity', l.opacity);
        }

        path.setAttribute('d', shape.d);
        path.setAttribute('fill', M.fillFor(l));
        path.setAttribute('stroke', l.color || '#000000');
        path.setAttribute('stroke-width', l.stroke || 1);
        path.setAttribute('stroke-linecap', 'round');
        path.setAttribute('stroke-linejoin', 'round');

        if (dash) {
            path.setAttribute('stroke-dasharray', dash.join(' '));
        }

        g.appendChild(path);

        if (shape.head) {
            var head = document.createElementNS(SVGNS, 'path');

            head.setAttribute('d', shape.head.d);
            head.setAttribute('fill', shape.head.fill ? (l.color || '#000000') : 'none');
            head.setAttribute('stroke', shape.head.stroke ? (l.color || '#000000') : 'none');
            head.setAttribute('stroke-width', shape.head.stroke || 0);
            head.setAttribute('stroke-linecap', 'round');
            head.setAttribute('stroke-linejoin', 'round');
            g.appendChild(head);
        }

        return g;
    }

    // The same drawing onto the save's canvas.
    function drawnPaint(g, l) {
        var shape = M.shapePath(l), fill = M.fillFor(l), color = l.color || '#000000', path = new Path2D(shape.d);

        g.translate(l.x + l.w / 2, l.y + l.h / 2);
        g.rotate((l.rot || 0) * Math.PI / 180);

        if (l.mirror) {
            g.scale(-1, 1);
        }

        g.translate(-l.w / 2, -l.h / 2);
        g.lineCap = 'round';
        g.lineJoin = 'round';

        if (fill !== 'none') {
            g.fillStyle = fill;
            g.fill(path);
        }

        g.strokeStyle = color;
        g.lineWidth = l.stroke || 1;
        g.setLineDash(M.dashFor(l) || []);
        g.stroke(path);
        g.setLineDash([]);

        if (shape.head) {
            var head = new Path2D(shape.head.d);

            if (shape.head.fill) {
                g.fillStyle = color;
                g.fill(head);
            }

            if (shape.head.stroke) {
                g.lineWidth = shape.head.stroke;
                g.stroke(head);
            }
        }
    }

    // A line's handles are its two ends (no box, no turning handle: the ends
    // turn it); a box or a bubble has a dot inside its corner for the radius.
    function drawnHandles(l) {
        var k = this.view.k;

        if (isLine(l.type)) {
            return [{ h: 'a', x: 0, y: l.h / 2 }, { h: 'b', x: l.w, y: l.h / 2 }];
        }

        if (!applies(l.type, 'radius')) {
            return [];
        }

        var r = Math.min(Math.max(Number(l.radius) || 0, 12 / k), l.w / 2, l.h / 2);

        return [{ h: 'radius', x: l.mirror ? l.w - r : r, y: r, title: t('cornerRadius') }];
    }

    // The fields a drag of such a handle changes.
    function drawnDrag(start, h, x, y, e) {
        if (h === 'radius') {
            var q = M.drawnLocal(start, x, y);

            return { radius: Math.round(M.clamp((q[0] + q[1]) / 2, 0, M.maxRadius(start))) };
        }

        // Frame left is the line's start unless it is mirrored.
        var ends = M.lineEnds(start), i = ((h === 'a') === !start.mirror) ? 0 : 1, other = ends[1 - i], p = [x, y];

        if (e.shiftKey) {
            p = snap45(other, p);
        }

        var b = M.lineBox(i ? other : p, i ? p : other, false, { kind: start.type, stroke: start.stroke, head: start.head });

        return { x: b.x, y: b.y, w: b.w, h: b.h, rot: b.rot, mirror: false };
    }

    KINDS.forEach(function (kind) {
        Editor.layerTypes[kind] = {
            node: drawnNode,
            paint: drawnPaint,
            hit: M.hitShape,
            handles: drawnHandles,
            drag: drawnDrag,
            box: isLine(kind) ? false : true,
            panel: function (body, layers) { this.drawStyle(body, layers, layers[0].type); }
        };
    });

    // ---- drawing on the stage ----

    // The kind the active tool draws: the grid's pick, or a mini bar tool's.
    Editor.prototype.drawKind = function () {
        return (this.tool === 'draw') ? this.drawPick : this.tool;
    };

    // A new layer of kind in the tool's style, its stroke a share of the
    // picture's size.
    Editor.prototype.newShape = function (kind) {
        var s = this.drawDefaults, size = M.docSize(this.doc), l = { type: kind, color: s.color, stroke: M.strokeFor(s.width, size.w, size.h), opacity: s.opacity };

        Object.keys(FIELDS).forEach(function (field) {
            if (applies(kind, field)) {
                l[field] = s[field];
            }
        });

        if (s.dash !== 'solid') {
            l.dash = s.dash;
        }

        return l;
    };

    Editor.prototype.drawDown = function (e) {
        var h = e.target.getAttribute && e.target.getAttribute('data-h'), p = this.toDoc(e.clientX, e.clientY), kind = this.drawKind();

        if (h && (this.sel.length === 1)) {
            this.startDrag(e);
            return;
        }

        if (kind === 'eraser') {
            this.drag = { kind: 'erase' };
            this.eraseAt(p);
        } else {
            // A press on a selected layer moves it; anywhere else draws.
            var hit = M.hitLayer(this.doc, p[0], p[1], 4 / this.view.k, hitTest);

            if (hit && (this.sel.indexOf(hit.id) >= 0)) {
                this.startDrag(e);
                return;
            }

            this.drag = { kind: 'draw', shape: kind, at: p, pts: [p], id: null, hit: hit ? hit.id : null };
        }

        e.preventDefault();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }
    };

    Editor.prototype.drawMove = function (e) {
        var self = this, d = this.drag, p = this.toDoc(e.clientX, e.clientY), k = this.view.k, geo;

        if (d.kind === 'erase') {
            this.eraseAt(p);
            return;
        }

        if (d.kind !== 'draw') {
            this.moveDrag(e);
            return;
        }

        // A few screen pixels before anything is drawn: a click selects.
        if (!d.id && (Math.hypot(p[0] - d.at[0], p[1] - d.at[1]) * k < 3)) {
            return;
        }

        if (d.shape === 'pen') {
            var last = d.pts[d.pts.length - 1];

            if (Math.hypot(p[0] - last[0], p[1] - last[1]) * k < 2) {
                return;
            }

            d.pts.push(p);
            geo = M.penLayer(d.pts);
        } else if (isLine(d.shape)) {
            geo = M.lineBox(d.at, p, e.shiftKey, { kind: d.shape, stroke: M.strokeFor(this.drawDefaults.width, M.docSize(this.doc).w, M.docSize(this.doc).h), head: this.drawDefaults.head });
        } else {
            geo = M.shapeBox(d.at[0], d.at[1], p[0], p[1], e.shiftKey);
        }

        this.change(function (doc) {
            var l = d.id ? find(doc, d.id) : null;

            if (!l) {
                l = self.newShape(d.shape);
                l.id = M.newId(doc);
                d.id = l.id;
                doc.layers.push(l);
                self.sel = [l.id];
            }

            Object.keys(geo).forEach(function (key) { l[key] = geo[key]; });

            // A bubble drawn with square corners gets its rounded ones.
            if ((d.shape === 'bubble') && !self.drawDefaults.radius) {
                l.radius = Math.round(M.maxRadius(l));
            }
        }, 'draw', true);
    };

    Editor.prototype.drawUp = function (e) {
        var self = this, d = this.drag;

        if (d.kind !== 'draw' && d.kind !== 'erase') {
            this.endDrag(e);
            return;
        }

        this.drag = null;
        this.eraser = null;
        this.history.key = null;

        if (d.kind === 'erase') {
            this.keepSelection();
            this.render();
            return;
        }

        if (!d.id && (d.shape === 'pen')) {
            // A click with the pen leaves a dot.
            this.change(function (doc) {
                var l = self.newShape('pen'), geo = M.penLayer([d.at]);

                Object.keys(geo).forEach(function (key) { l[key] = geo[key]; });
                l.id = M.newId(doc);
                d.id = l.id;
                doc.layers.push(l);
            });
        }

        if (!d.id) {
            this.select(d.hit ? [d.hit] : []);
            return;
        }

        this.select([d.id]);
    };

    // The eraser's radius in picture pixels: a share of the picture, at
    // least 8 screen pixels.
    Editor.prototype.eraserRadius = function () {
        var size = M.docSize(this.doc);

        return Math.max(M.strokeFor(this.drawDefaults.eraser, size.w, size.h) * 2.5, 8 / this.view.k);
    };

    // The eraser at p cuts the visible, unlocked pen strokes it touches.
    Editor.prototype.eraseAt = function (p) {
        var r = this.eraserRadius(), cuts = {}, any = false;

        this.eraser = { x: p[0], y: p[1], r: r };

        this.doc.layers.forEach(function (l) {
            var b = (l.type === 'pen') && !l.hidden && !l.locked ? M.bounds([l]) : null, reach = r + (l.stroke || 1), pieces;

            if (!b || (p[0] < b.x - reach) || (p[0] > b.x + b.w + reach) || (p[1] < b.y - reach) || (p[1] > b.y + b.h + reach)) {
                return;
            }

            pieces = M.eraseTrace(l, p[0], p[1], r);

            if (pieces) {
                cuts[l.id] = pieces;
                any = true;
            }
        });

        if (!any) {
            this.renderView();
            return;
        }

        this.change(function (doc) {
            var out = [], taken = doc.layers.map(function (l) { return l.id; });
            var fresh = function () {
                var n = doc.layers.length;

                while (taken.indexOf('l' + n) >= 0) {
                    n++;
                }

                taken.push('l' + n);

                return 'l' + n;
            };

            doc.layers.forEach(function (l) {
                if (!cuts[l.id]) {
                    out.push(l);
                    return;
                }

                cuts[l.id].forEach(function (geo, i) {
                    var piece = M.clone(l);

                    Object.keys(geo).forEach(function (key) { piece[key] = geo[key]; });

                    if (!piece.mirror) {
                        delete piece.mirror;
                    }

                    piece.id = i ? fresh() : l.id;
                    out.push(piece);
                });
            });

            doc.layers = out;
        }, 'erase', true);
        this.keepSelection();
    };

    // The eraser's ring while it erases; the eraser's hint.
    Editor.prototype.renderEraser = function () {
        var v = this.view, vr = this.viewRect(), e = this.eraser;

        if ((this.tool === 'draw') && (this.drawPick === 'eraser')) {
            this.hint.textContent = t('hintEraser');
        }

        if (!e || !this.ready || (this.tool !== 'draw')) {
            return;
        }

        var ring = el('div', { class: 'pgie-eraser' }), d = 2 * e.r * v.k;

        ring.style.left = (v.x + (e.x - e.r - vr.x) * v.k) + 'px';
        ring.style.top = (v.y + (e.y - e.r - vr.y) * v.k) + 'px';
        ring.style.width = d + 'px';
        ring.style.height = d + 'px';
        this.selEl.appendChild(ring);
    };

    // ---- the panel ----

    Editor.prototype.drawPanel = function (body) {
        var self = this, kind = this.drawKind(), picked = this.selected().filter(isDrawn);

        if (this.tool === 'draw') {
            var grid = el('div', { class: 'pgie-kinds', role: 'group', 'aria-label': t('drawKinds') });

            KINDS.concat(['eraser']).forEach(function (k) {
                var on = (self.drawPick === k), name = (k === 'eraser') ? t('eraser') : t('layer_' + k);
                var b = el('button', { type: 'button', class: 'pgie-kind' + (on ? ' on' : ''), 'aria-pressed': on ? 'true' : 'false', title: name, onclick: function () {
                    self.drawPick = k;
                    self.render();
                } }, [icon(k), el('span', { text: name })]);

                grid.appendChild(b);
            });

            body.appendChild(grid);
        }

        if (kind === 'eraser') {
            body.appendChild(section(t('eraserSize'), this.drawSegment([['thin', t('strokeThin')], ['medium', t('strokeMedium')], ['thick', t('strokeThick')]],
                this.drawDefaults.eraser, function (v) {
                    self.drawDefaults.eraser = v;
                    self.render();
                })));
            body.appendChild(el('p', { class: 'pgie-note', text: t('eraserNote') }));
            return;
        }

        this.drawStyle(body, picked.length ? picked : null, picked.length ? picked[0].type : kind);

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

    // A row of choices; the chosen one pressed.
    Editor.prototype.drawSegment = function (choices, value, pick) {
        var row = el('div', { class: 'pgie-seg' });

        choices.forEach(function (c) {
            var on = (c[0] === value);

            row.appendChild(el('button', { type: 'button', class: on ? 'on' : '', 'aria-pressed': on ? 'true' : 'false', text: c[1], onclick: function () { pick(c[0]); } }));
        });

        return row;
    };

    // A slider; its label shows the value as shown(v).
    Editor.prototype.drawSlider = function (label, min, max, value, shown, set) {
        var self = this, out = el('span', { class: 'pgie-val', text: shown(value) });
        var slider = el('input', { type: 'range', class: 'pgie-range', min: String(min), max: String(max), step: '1', value: String(value), 'aria-label': label });

        slider.addEventListener('input', function () {
            out.textContent = shown(Number(slider.value));
            set(Number(slider.value), true);
        });
        // Letting go ends the undo step: the next drag is one of its own.
        slider.addEventListener('change', function () {
            self.history.key = null;
            self.render();
        });

        return section(label, slider, out);
    };

    // The style controls for the selected drawn layers, or for what the tool
    // draws next (layers null): a change goes to both.
    Editor.prototype.drawStyle = function (body, layers, kind) {
        var self = this, s = this.drawDefaults, size = M.docSize(this.doc), shown = layers ? layers[0] : this.newShape(kind);
        var types = layers ? layers.map(function (l) { return l.type; }) : [kind];
        var has = function (field) { return types.some(function (type) { return applies(type, field); }); };

        // set(field, value, live): live keeps the panel while a slider moves.
        var set = function (field, value, live) {
            if (field === 'stroke') {
                s.width = M.strokePreset(value, size.w, size.h);
            } else if (s.hasOwnProperty(field)) {
                s[field] = value;
            }

            if (!layers) {
                if (!live) {
                    self.render();
                }

                return;
            }

            self.change(function (doc) {
                layers.forEach(function (o) {
                    var l = find(doc, o.id);

                    if (!l || !applies(l.type, field)) {
                        return;
                    }

                    if ((field === 'dash') && (value === 'solid')) {
                        delete l.dash;
                    } else {
                        l[field] = value;
                    }

                    if (isLine(l.type) && ((field === 'stroke') || (field === 'head'))) {
                        refitLine(l);
                    }
                });
            }, live ? 'style-' + field : null, live);

            if (!live) {
                self.history.key = null;
            }
        };

        // Colour: the swatches and a picker of any colour; the picker's
        // drag is one undo step, its closing change adds none.
        var moved = false;

        body.appendChild(swatches(t('drawColor'), shown.color || s.color, function (c, live) {
            if (live) {
                moved = true;
                set('color', c, true);
                return;
            }

            self.history.key = null;

            if (moved) {
                moved = false;
                self.render();
                return;
            }

            set('color', c, false);
        }));

        body.appendChild(section(t('strokeWidth'), this.drawSegment([['thin', t('strokeThin')], ['medium', t('strokeMedium')], ['thick', t('strokeThick')]],
            M.strokePreset(shown.stroke, size.w, size.h), function (v) { set('stroke', M.strokeFor(v, size.w, size.h), false); })));

        if (has('fill')) {
            body.appendChild(section(t('drawFill'), this.drawSegment([['none', t('fillNone')], ['solid', t('fillSolid')], ['soft', t('fillSoft')]],
                shown.fill || 'none', function (v) { set('fill', v, false); })));
        }

        if (has('radius')) {
            var most = layers ? Math.round(M.maxRadius(shown)) : 200;

            body.appendChild(this.drawSlider(t('cornerRadius'), 0, Math.max(1, most), Math.min(most, Math.round(shown.radius || 0)),
                function (v) { return decimal(v) + ' px'; }, function (v, live) { set('radius', v, live); }));
        }

        if (has('sides')) {
            body.appendChild(this.drawSlider(t('sides'), 3, 12, shown.sides || 6, String, function (v, live) { set('sides', v, live); }));
        }

        if (has('points')) {
            body.appendChild(this.drawSlider(t('starPoints'), 4, 12, shown.points || 5, String, function (v, live) { set('points', v, live); }));
            body.appendChild(this.drawSlider(t('innerRatio'), 20, 80, Math.round((shown.inner || 0.48) * 100), function (v) { return v + ' %'; },
                function (v, live) { set('inner', v / 100, live); }));
        }

        if (has('heads')) {
            body.appendChild(section(t('arrowHeads'), this.drawSegment([['end', t('headsEnd')], ['both', t('headsBoth')]],
                shown.heads || 'end', function (v) { set('heads', v, false); })));
            body.appendChild(section(t('headStyle'), this.drawSegment([['fill', t('headFill')], ['open', t('headOpen')], ['dot', t('headDot')]],
                shown.head || 'fill', function (v) { set('head', v, false); })));
        }

        if (has('smooth')) {
            body.appendChild(this.drawSlider(t('smoothing'), 0, 10, (shown.smooth === undefined) ? 4 : shown.smooth, String,
                function (v, live) { set('smooth', v, live); }));
        }

        body.appendChild(section(t('lineStyle'), this.drawSegment([['solid', t('dashSolid')], ['dashed', t('dashDashed')], ['dotted', t('dashDotted')]],
            shown.dash || 'solid', function (v) { set('dash', v, false); })));

        body.appendChild(this.drawSlider(t('drawOpacity'), 10, 100, Math.round(100 * ((shown.opacity === undefined) ? 1 : shown.opacity)),
            function (v) { return v + ' %'; }, function (v, live) { set('opacity', v / 100, live); }));
    };

    // The tool's pick and the style it draws with next.
    Editor.inits.push(function () {
        this.drawPick = 'arrow';
        this.eraser = null;
        this.drawDefaults = {
            color: '#e53935', width: 'medium', fill: 'none', dash: 'solid', radius: 0, sides: 6, points: 5, inner: 0.48,
            heads: 'end', head: 'fill', smooth: 4, opacity: 1, eraser: 'medium'
        };
    });

    ['draw'].concat(MINI).forEach(function (tool) {
        Editor.panels[tool] = Editor.prototype.drawPanel;
        Editor.hints[tool] = 'hintDraw';
        Editor.pointers[tool] = { down: Editor.prototype.drawDown, move: Editor.prototype.drawMove, up: Editor.prototype.drawUp };
        Editor.selectTools.push(tool);
    });

    Editor.views.push(Editor.prototype.renderEraser);
}(window.PgImageEditor.parts));
