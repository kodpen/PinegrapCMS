/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: free deformation: pins on a path, a drawn shape or outlined
 * text; a pin dragged bends the shape around the others (rigid moving least
 * squares, the model's PUPPET section). Kept as the layer's puppet and baked
 * into the path by Expand, as a warp is.
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

    var Editor = X.Editor, M = X.M, find = X.find, t = X.t, el = X.el, button = X.button, section = X.section;

    X.icons.puppet = 'M5 17c3-6 5-10 7-10s4 4 7 10M12 7v0M5 17v0M19 17v0M12 4.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM5 14.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM19 14.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5z';

    // A press this many screen pixels from the drawn outline still adds a pin.
    var EDGE = 6;

    // Whether the point p lies inside the closed contours (even-odd) or
    // within slack of any contour, all in the box's frame.
    function onShape(contours, p, slack) {
        var inside = false;

        contours.forEach(function (pts) {
            var i, j, a, b, dx, dy, k, cx, cy;

            for (i = 0, j = pts.length - 1; i < pts.length; j = i++) {
                a = pts[j];
                b = pts[i];

                if (((b[1] > p[1]) !== (a[1] > p[1])) && (p[0] < (a[0] - b[0]) * (p[1] - b[1]) / (a[1] - b[1]) + b[0])) {
                    inside = !inside;
                }

                dx = b[0] - a[0];
                dy = b[1] - a[1];
                k = (dx || dy) ? Math.max(0, Math.min(1, ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / (dx * dx + dy * dy))) : 0;
                cx = a[0] + k * dx;
                cy = a[1] + k * dy;

                if (Math.hypot(p[0] - cx, p[1] - cy) <= slack) {
                    inside = true;
                    return;
                }
            }
        });

        return inside;
    }

    // The one selected layer that takes pins, or null.
    Editor.prototype.puppetTarget = function () {
        var picked = this.selected();

        return ((picked.length === 1) && !picked[0].locked && M.warpable(picked[0])) ? picked[0] : null;
    };

    // The pins of layer id changed by edit(pins): an empty list drops them.
    Editor.prototype.setPins = function (id, edit, key, live) {
        this.change(function (doc) {
            var l = find(doc, id), pins;

            if (!l) {
                return;
            }

            pins = edit((l.puppet && Array.isArray(l.puppet.pins)) ? M.clone(l.puppet.pins) : []);

            if (pins.length) {
                l.puppet = { pins: pins };
            } else {
                delete l.puppet;
            }
        }, key || null, !!live);
    };

    Editor.prototype.removePin = function (l, i) {
        this.puppetPin = -1;
        this.setPins(l.id, function (pins) { return pins.filter(function (p, n) { return n !== i; }); });
    };

    // ---- the panel ----

    Editor.prototype.puppetPanel = function (body) {
        var self = this, picked = this.selected(), l = this.puppetTarget(), n = (l && l.puppet && Array.isArray(l.puppet.pins)) ? l.puppet.pins.length : 0;

        if (!l) {
            if ((picked.length === 1) && (picked[0].type === 'text') && !picked[0].locked) {
                body.appendChild(el('p', { class: 'pgie-note', text: t('puppetTextNote') }));

                // Text to outlines lives in its own part; the button shows
                // where that part is bundled.
                if (typeof this.convertToOutlines === 'function') {
                    var conv = button('pgie-btn pgie-wide', t('warpToOutlines'), 'vector', function () { self.convertToOutlines(conv); }, true);

                    body.appendChild(conv);
                }

                return;
            }

            body.appendChild(el('p', { class: 'pgie-note', text: t('puppetNote') }));
            return;
        }

        body.appendChild(el('p', { class: 'pgie-note', text: t('puppetHowTo') }));
        body.appendChild(el('p', { class: 'pgie-note', 'aria-live': 'polite', text: t('puppetCount').replace('{n}', String(n)) }));

        if ((this.puppetPin >= 0) && (this.puppetPin < n)) {
            body.appendChild(button('pgie-btn pgie-wide', t('puppetRemove'), 'puppet', function () { self.removePin(l, self.puppetPin); }, true));
        }

        if (n) {
            body.appendChild(button('pgie-btn pgie-wide', t('puppetClear'), 'puppet', function () {
                self.puppetPin = -1;
                self.setPins(l.id, function () { return []; });
            }, true));
        }

        if (M.puppetOn(l)) {
            body.appendChild(section(t('warpExpand'), el('div', {}, [
                el('p', { class: 'pgie-note', text: t('puppetExpandNote') }),
                button('pgie-btn pgie-wide', t('warpExpand'), 'vector', function () {
                    self.puppetPin = -1;
                    self.expandWarp(l);
                }, true)
            ])));
        }
    };

    // ---- the pins on the stage ----

    // The pins over the selected layer, turned with it like the selection's
    // box; the mirror applied as the stage draws the layer.
    Editor.prototype.renderPuppet = function () {
        var l = (this.tool === 'puppet') && this.ready ? this.puppetTarget() : null, self = this;

        // While pinning, the selection's resize, rotate and type handles
        // are hidden: they would sit on the shape looking like pins, and a
        // press there adds a pin anyway. Select keeps them.
        if (this.tool === 'puppet') {
            Array.prototype.forEach.call(this.selEl.querySelectorAll('.pgie-selbox'), function (d) { d.classList.add('pgie-pinning'); });
        }

        if (!l || !l.puppet || !Array.isArray(l.puppet.pins)) {
            return;
        }

        var v = this.view, vr = this.viewRect(), k = v.k, box = el('div', { class: 'pgie-wbox' });

        box.style.left = (v.x + (l.x - vr.x) * k) + 'px';
        box.style.top = (v.y + (l.y - vr.y) * k) + 'px';
        box.style.width = (l.w * k) + 'px';
        box.style.height = (l.h * k) + 'px';
        box.style.transform = l.rot ? 'rotate(' + l.rot + 'deg)' : '';

        l.puppet.pins.forEach(function (pin, i) {
            var s = el('span', { class: 'pgie-sh pgie-shx pgie-wpt pgie-pin' + ((i === self.puppetPin) ? ' on' : ''), 'data-pp': String(i), title: t('puppetPin') });

            s.style.left = ((l.mirror ? 1 - pin.at[0] : pin.at[0]) * l.w * k) + 'px';
            s.style.top = (pin.at[1] * l.h * k) + 'px';
            box.appendChild(s);
        });

        this.selEl.appendChild(box);
    };

    // ---- pointers ----

    // A press on a pin drags it (with Alt removes it); a press on the
    // selected shape adds a pin there and drags it; anything else selects
    // and moves as the Select tool does.
    Editor.prototype.puppetDown = function (e) {
        var pp = e.target.getAttribute && e.target.getAttribute('data-pp'), l = this.puppetTarget(), i, p, q;

        if (!l) {
            this.startDrag(e);
            return;
        }

        if ((pp !== null) && (pp !== undefined)) {
            i = Number(pp);

            if (e.altKey) {
                this.removePin(l, i);
                e.preventDefault();
                return;
            }
        } else {
            p = this.toDoc(e.clientX, e.clientY);
            q = M.drawnLocal(l, p[0], p[1]);

            if (!onShape(M.outline(l), q, M.touchReach(e.pointerType, EDGE) / this.view.k) || ((l.puppet && l.puppet.pins.length) >= M.PUPPET_MAX)) {
                this.puppetPin = -1;
                this.startDrag(e);
                return;
            }

            i = (l.puppet && Array.isArray(l.puppet.pins)) ? l.puppet.pins.length : 0;
            this.change(function (doc) {
                var o = find(doc, l.id);

                if (o) {
                    o.puppet = M.puppetAdd(o, q[0] / (o.w || 1), q[1] / (o.h || 1));
                }
            }, 'drag', true);
            l = this.layer(l.id);
        }

        this.puppetPin = i;
        this.drag = { kind: 'puppetpin', i: i, start: M.clone(l) };
        e.preventDefault();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }

        this.render();
    };

    Editor.prototype.puppetMove = function (e) {
        var d = this.drag;

        if (d.kind !== 'puppetpin') {
            this.moveDrag(e);
            return;
        }

        var p = this.toDoc(e.clientX, e.clientY), q = M.drawnLocal(d.start, p[0], p[1]), s = d.start;
        var at = [Math.round(q[0] / s.w * 1e4) / 1e4, Math.round(q[1] / s.h * 1e4) / 1e4];

        this.setPins(s.id, function (pins) {
            if (pins[d.i]) {
                pins[d.i].at = at;
            }

            return pins;
        }, 'drag', true);
    };

    // Delete or Backspace removes the chosen pin before the layer keys
    // would delete the layer.
    Editor.prototype.puppetKey = function (e, mod, k) {
        var l = (this.tool === 'puppet') ? this.puppetTarget() : null;

        if (!l || mod || ((k !== 'Delete') && (k !== 'Backspace')) || !(this.puppetPin >= 0) || !l.puppet || !(this.puppetPin < l.puppet.pins.length)) {
            return false;
        }

        this.removePin(l, this.puppetPin);

        return true;
    };

    Editor.inits.push(function () {
        this.puppetPin = -1;
    });
    Editor.panels.puppet = Editor.prototype.puppetPanel;
    Editor.hints.puppet = 'hintPuppet';
    Editor.pointers.puppet = { down: Editor.prototype.puppetDown, move: Editor.prototype.puppetMove, up: Editor.prototype.endDrag };
    Editor.selectTools.push('puppet');
    Editor.views.push(Editor.prototype.renderPuppet);
    Editor.keys.unshift(Editor.prototype.puppetKey);
}(window.PgImageEditor.parts));
