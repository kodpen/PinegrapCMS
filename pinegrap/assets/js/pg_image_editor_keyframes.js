/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: keyframes. A layer's place, size, turn and opacity at
 * chosen times (anim.keys, the KEYS section of the model), eased between
 * and composed with its entrance, loop and exit by M.motionAt().
 *
 * In the Animate tool: a Path tab beside entrance, loop and exit with the
 * key at the playhead (its fields, its ease, remove) or a button that adds
 * one; dragging a layer on the stage while the playhead stands away from 0
 * sets its place at that moment; a diamond per key on the layer's track
 * (a press seeks to it, a drag moves it, Delete removes it); the path the
 * layer's centre takes, drawn over the stage.
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

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, t = X.t, el = X.el, button = X.button, section = X.section, pct = X.pct;

    var STILL = ['redact'];
    // A dragged diamond lands on a tenth of a second; Shift on a hundredth.
    var DIAMOND_STEP = 100, DIAMOND_FINE = 10;

    function seconds(ms) {
        return X.decimal(ms / 1000) + ' ' + t('motionSecondsUnit');
    }

    // Where ms sits on a lane of end ms, as a CSS left (X.pct, the
    // panel's percentage label, is another function).
    function lanePos(ms, end) {
        return (100 * Math.max(0, Math.min(end, ms)) / end) + '%';
    }

    Editor.prototype.keyNow = function () {
        return (this.time === null) ? 0 : Math.max(0, Math.min(this.motionEnd(), this.motionNow()));
    };

    // ---- changes ----

    // The key at ms of layer id with patch; key and live as change() takes them.
    Editor.prototype.setKey = function (id, ms, patch, key) {
        var end = this.motionEnd();

        this.change(function (doc) {
            doc.layers.forEach(function (l) {
                if (l.id === id) {
                    l.anim = Object.assign({}, l.anim || {}, { keys: M.keySet(l, ms, patch, end) });
                }
            });
        }, key || null, !!key);
    };

    // The keys of layer id through fn(keys); none left removes them.
    Editor.prototype.editKeys = function (id, fn) {
        this.change(function (doc) {
            doc.layers.forEach(function (l) {
                if ((l.id !== id) || !l.anim) {
                    return;
                }

                var keys = fn(M.keyList(l));

                if (keys.length) {
                    l.anim.keys = keys;
                } else {
                    delete l.anim.keys;

                    if (!l.anim['in'] && !l.anim.loop && !l.anim.out) {
                        delete l.anim;
                    }
                }
            });
        });
    };

    // ---- the Path tab ----

    Editor.prototype.keysPanel = function (body, layers) {
        var self = this, l = layers[0], keys, now, i, list;

        if (layers.length !== 1) {
            body.appendChild(el('p', { class: 'pgie-note', text: t('keysOne') }));
            return;
        }

        keys = M.keyList(l);
        now = this.keyNow();
        i = M.keyIndex(keys, now);

        body.appendChild(el('p', { class: 'pgie-note', text: t('keysNote') }));

        if (keys.length) {
            list = el('div', { class: 'pgie-chips pgie-keylist', role: 'group', 'aria-label': t('keysList') });

            keys.forEach(function (k, n) {
                list.appendChild(el('button', { type: 'button', class: 'pgie-chip' + ((n === i) ? ' on' : ''), 'aria-pressed': (n === i) ? 'true' : 'false', text: seconds(k.t), onclick: function () {
                    self.seek(self.motionStart() + k.t);
                    self.renderPanel();
                } }));
            });

            body.appendChild(section(t('keysList'), list));
        }

        this.keyPanelSig = this.keySig(l);

        if (i < 0) {
            body.appendChild(el('div', { class: 'pgie-actions' }, [button('pgie-btn', t('keysAdd').replace('{time}', seconds(now)), 'plus', function () {
                var at = self.keyNow();

                self.time = self.motionStart() + at;
                self.setKey(l.id, at, {});
            }, true)]));
        } else {
            this.keyFields(body, l, keys[i]);
        }

        if (keys.length) {
            body.appendChild(el('button', { type: 'button', class: 'pgie-link', text: t('keysClear'), onclick: function () {
                self.editKeys(l.id, function () { return []; });
            } }));
        }
    };

    // The key at the playhead: its place, size, turn, opacity and ease.
    Editor.prototype.keyFields = function (body, l, k) {
        var self = this, span = Math.max(M.saveSize(this.doc).w, M.saveSize(this.doc).h);

        body.appendChild(el('h3', { class: 'pgie-mwhole', text: t('keysAt').replace('{time}', seconds(k.t)) }));

        [
            ['x', t('keysX'), -span, span, 1, k.x, function (v) { return Math.round(v) + ' px'; }, 1],
            ['y', t('keysY'), -span, span, 1, k.y, function (v) { return Math.round(v) + ' px'; }, 1],
            ['scale', t('keysScale'), 5, 400, 1, Math.round(k.scale * 100), function (v) { return pct(Math.round(v)); }, 100],
            ['rot', t('keysRot'), -360, 360, 1, k.rot, function (v) { return Math.round(v) + '°'; }, 1],
            ['opacity', t('keysOpacity'), 0, 100, 1, Math.round(k.opacity * 100), function (v) { return pct(Math.round(v)); }, 100]
        ].forEach(function (f) {
            var shown = el('span', { class: 'pgie-val', text: f[6](f[5]) });
            var slider = el('input', { type: 'range', class: 'pgie-range', min: String(f[2]), max: String(f[3]), step: String(f[4]), value: String(f[5]), 'aria-label': f[1] });

            slider.addEventListener('input', function () {
                var patch = {};

                patch[f[0]] = Number(slider.value) / f[7];
                shown.textContent = f[6](Number(slider.value));
                self.setKey(l.id, k.t, patch, 'keys-' + f[0]);
                self.applyMotion();
                self.drawKeyPath();
            });
            slider.addEventListener('change', function () {
                self.history.key = null;
                self.render();
            });
            body.appendChild(section(f[1], slider, shown));
        });

        // The first key has nothing before it to ease from.
        if (k.t > M.keyList(l)[0].t) {
            var eases = el('div', { class: 'pgie-chips', role: 'radiogroup', 'aria-label': t('keysEase') });

            M.MOTION_EASES.forEach(function (ease) {
                var on = (k.ease === ease);

                eases.appendChild(el('button', { type: 'button', role: 'radio', class: 'pgie-chip' + (on ? ' on' : ''), 'aria-checked': on ? 'true' : 'false', text: t('motionEase_' + ease), onclick: function () {
                    self.setKey(l.id, k.t, { ease: ease });
                } }));
            });

            body.appendChild(section(t('keysEase'), eases));
        }

        body.appendChild(el('button', { type: 'button', class: 'pgie-link', text: t('keysRemove'), onclick: function () {
            self.editKeys(l.id, function (keys) { return M.keyDrop(keys, k.t); });
        } }));
    };

    // What the Path tab shows for l at the playhead: the key it stands on,
    // or the tenth of a second the Add button names.
    Editor.prototype.keySig = function (l) {
        var keys = M.keyList(l), now = this.keyNow(), i = M.keyIndex(keys, now);

        return l.id + ':' + ((i >= 0) ? 'k' + keys[i].t : 'n' + Math.round(now / 100));
    };

    // The playhead moved: the Path tab follows it, except while playing or
    // recording (pg_image_editor_record.js) and while the scrub in the
    // sheet (a phone) is held, which a new panel would take from under the
    // finger.
    Editor.prototype.keyFollow = function () {
        var l = (this.sel.length === 1) ? this.layer(this.sel[0]) : null, a = document.activeElement;

        this.drawKeyPath();

        if (!l || this.playing || this.recording || (this.tool !== 'motion') || (this.motionTab !== 'keys') || (a && a.classList && a.classList.contains('pgie-mscrub') && this.panel && this.panel.contains(a))) {
            return;
        }

        if (this.keySig(l) !== this.keyPanelSig) {
            this.keyPanelSig = this.keySig(l);
            this.renderPanel();
        }
    };

    // ---- a layer dragged at a moment ----

    // The movable layer drawn under p at the playhead: its keyed place, not
    // its rest, is where it shows.
    Editor.prototype.keyHit = function (p, inset) {
        var end = this.motionEnd(), now = this.motionNow(), slack = (inset || 4) / this.view.k, layers = this.doc.layers;

        for (var i = layers.length - 1; i >= 0; i--) {
            var l = layers[i];

            if (l.hidden || l.locked || (STILL.indexOf(l.type) >= 0)) {
                continue;
            }

            var st = M.motionAt(l, now, end);

            if ((st.opacity > 0) && M.insideBox(l, p[0] - st.x, p[1] - st.y, slack)) {
                return l;
            }
        }

        return null;
    };

    // With the playhead standing away from 0, a press on a layer (Shift not
    // held) moves it at that moment: a key. A handle resizes or turns at
    // rest, as before, except a box handle pressed well inside the layer:
    // on touch its 44 px target covers a small layer's middle (as
    // startDrag() does).
    Editor.prototype.keyDown = function (e) {
        var p = this.toDoc(e.clientX, e.clientY), h = e.target.getAttribute && e.target.getAttribute('data-h'), hit, k;

        if ((this.time === null) || !(this.motionNow() > 0) || (this.motionNow() > this.motionEnd()) || this.playing || (h === 'rot') || e.shiftKey || (e.button > 0)) {
            return this.motionDown(e);
        }

        hit = h ? this.keyHit(p, 10) : this.keyHit(p);

        if (!hit) {
            return this.motionDown(e);
        }

        // The Path tab shows the key the drag sets.
        if ((this.sel.length !== 1) || (this.sel[0] !== hit.id) || (this.motionTab !== 'keys')) {
            this.sel = [hit.id];
            this.motionTab = 'keys';
            this.renderPanel();
        }

        k = M.keyAt(hit, this.motionNow()) || { x: 0, y: 0 };
        this.drag = { kind: 'key', at: p, start: M.clone(hit), t: this.motionNow(), x: k.x, y: k.y };
        e.preventDefault();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }
    };

    Editor.prototype.keyMoveDrag = function (e) {
        var d = this.drag, p, end;

        if (!d || (d.kind !== 'key')) {
            return this.moveDrag(e);
        }

        p = this.toDoc(e.clientX, e.clientY);
        end = this.motionEnd();

        this.change(function (doc) {
            doc.layers.forEach(function (l) {
                if (l.id === d.start.id) {
                    l.anim = Object.assign({}, l.anim || {}, { keys: M.keySet(d.start, d.t, { x: d.x + p[0] - d.at[0], y: d.y + p[1] - d.at[1] }, end) });
                }
            });
        }, 'drag', true);
        this.applyMotion();
        this.drawKeyPath();
    };

    Editor.prototype.keyEndDrag = function () {
        if (this.drag && (this.drag.kind === 'key')) {
            this.drag = null;
            this.history.key = null;
            this.render();
            return;
        }

        this.endDrag();
    };

    // ---- diamonds on the tracks ----

    Editor.prototype.keyDiamonds = function (lane, l, end, on) {
        var self = this;

        M.keyList(l).forEach(function (k) {
            var label = t('keysDiamond').replace('{time}', seconds(k.t));
            var d = el('button', { type: 'button', class: 'pgie-trkey' + (on ? ' on' : ''), style: 'left: ' + lanePos(k.t, end), title: label, 'aria-label': label });

            d.addEventListener('pointerdown', function (e) {
                e.stopPropagation();
                self.diamondDown(e, d, l, k, end);
            });
            d.addEventListener('keydown', function (e) {
                if ((e.key === 'Delete') || (e.key === 'Backspace')) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.editKeys(l.id, function (keys) { return M.keyDrop(keys, k.t); });
                }
            });
            lane.appendChild(d);
        });
    };

    // A press seeks to the key and selects its layer; a drag moves the key
    // in time, one change on release.
    Editor.prototype.diamondDown = function (e, d, l, k, end) {
        var self = this, width = d.parentNode.getBoundingClientRect().width, x0 = e.clientX, to = k.t, moved = false;

        if ((e.button > 0) || !(width > 0)) {
            return;
        }

        e.preventDefault();

        if ((this.sel.length !== 1) || (this.sel[0] !== l.id)) {
            this.sel = [l.id];
        }

        this.motionTab = 'keys';
        this.seek(this.motionStart() + k.t);
        this.trackDragging = true;

        try {
            d.setPointerCapture(e.pointerId);
        } catch (err) {
            // A pointer the browser no longer tracks: the drag still follows moves.
        }

        var move = function (ev) {
            var step = ev.shiftKey ? DIAMOND_FINE : DIAMOND_STEP;

            moved = moved || (Math.abs(ev.clientX - x0) > 2);

            if (!moved) {
                return;
            }

            to = Math.max(0, Math.min(end, Math.round((k.t + (ev.clientX - x0) / width * end) / step) * step));
            d.style.left = lanePos(to, end);
            d.title = t('keysDiamond').replace('{time}', seconds(to));
            self.seek(self.motionStart() + to);
        };

        var up = function () {
            d.removeEventListener('pointermove', move);
            d.removeEventListener('pointerup', up);
            d.removeEventListener('pointercancel', up);
            self.trackDragging = false;

            if (moved && (to !== k.t)) {
                self.editKeys(l.id, function (keys) { return M.keyMove(keys, k.t, to, end); });
            } else {
                self.render();
            }
        };

        d.addEventListener('pointermove', move);
        d.addEventListener('pointerup', up);
        d.addEventListener('pointercancel', up);
    };

    // ---- the path on the stage ----

    // The selected layer's path in the Animate tool: a line through its
    // keyed places, a dot at each key and a ring where it is now.
    Editor.prototype.drawKeyPath = function () {
        var svg = this.keyPathEl, l = (this.sel.length === 1) ? this.layer(this.sel[0]) : null, path, v, vr, k, at, now;

        this.keySelShift();

        if (!svg && this.selEl && this.selEl.parentNode) {
            svg = this.keyPathEl = document.createElementNS(SVGNS, 'svg');
            svg.setAttribute('class', 'pgie-keypath');
            svg.setAttribute('aria-hidden', 'true');
            this.selEl.parentNode.insertBefore(svg, this.selEl);
        }

        if (!svg) {
            return;
        }

        svg.textContent = '';
        path = (this.tool === 'motion') && l && !l.hidden ? M.keyPath(l, 60) : null;

        if (!path) {
            return;
        }

        v = this.view;
        vr = this.viewRect();
        k = v.k;
        at = function (q) { return (v.x + (q[0] - vr.x) * k) + ',' + (v.y + (q[1] - vr.y) * k); };

        var line = document.createElementNS(SVGNS, 'polyline');

        line.setAttribute('points', path.line.map(at).join(' '));
        line.setAttribute('class', 'pgie-keyline');
        svg.appendChild(line);

        path.dots.forEach(function (q) {
            var c = document.createElementNS(SVGNS, 'circle'), xy = at(q).split(',');

            c.setAttribute('cx', xy[0]);
            c.setAttribute('cy', xy[1]);
            c.setAttribute('r', '4');
            c.setAttribute('class', 'pgie-keydot');
            svg.appendChild(c);
        });

        now = M.keyAt(l, this.keyNow());

        if (now) {
            var ring = document.createElementNS(SVGNS, 'circle');
            var xy = at([(Number(l.x) || 0) + (Number(l.w) || 0) / 2 + now.x, (Number(l.y) || 0) + (Number(l.h) || 0) / 2 + now.y]).split(',');

            ring.setAttribute('cx', xy[0]);
            ring.setAttribute('cy', xy[1]);
            ring.setAttribute('r', '7');
            ring.setAttribute('class', 'pgie-keynow');
            svg.appendChild(ring);
        }
    };

    // The selection boxes follow where their layers show at the playhead
    // (renderSelection() draws them at rest, one per selected layer in
    // order): a CSS translate beside the box's own rotate. A moved box
    // hides its handles: they resize and turn the layer at rest, which
    // would jump to the pointer; a press inside it sets a key.
    Editor.prototype.keySelShift = function () {
        var self = this, boxes = this.selEl ? this.selEl.querySelectorAll('.pgie-selbox') : [], picked = this.selected(), end = this.motionEnd();

        Array.prototype.forEach.call(boxes, function (b, i) {
            var l = picked[i], st = (l && (self.time !== null)) ? M.motionAt(l, self.motionNow(), end) : null;

            var moved = !!(st && (st.x || st.y));

            b.style.translate = moved ? (st.x * self.view.k) + 'px ' + (st.y * self.view.k) + 'px' : '';
            b.classList.toggle('pgie-selmoved', moved);
        });
    };

    Editor.inits.push(function () {
        this.keyPathEl = null;
        this.keyPanelSig = '';
    });

    Editor.motionPanes.push({
        id: 'keys',
        label: 'keysTab',
        has: function (a) { return !!(a && a.keys && a.keys.length); },
        panel: Editor.prototype.keysPanel
    });

    // The path's ring follows the playhead; no rows of its own.
    Editor.motionDocks.push({
        key: function () { return ''; },
        node: function () { return null; },
        update: Editor.prototype.keyFollow,
        note: function () { return null; }
    });

    Editor.trackMarks.push(Editor.prototype.keyDiamonds);
    Editor.views.push(Editor.prototype.drawKeyPath);
    Editor.pointers.motion = { down: Editor.prototype.keyDown, move: Editor.prototype.keyMoveDrag, up: Editor.prototype.keyEndDrag, double: Editor.prototype.editLayer };
}(window.PgImageEditor.parts));
