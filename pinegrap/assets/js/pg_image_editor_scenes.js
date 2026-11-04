/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: a canvas's scenes. A strip under the Animate tool's play bar
 * (in the sheet on a phone) shows each scene's thumbnail, number and
 * duration and the transition between two; a click opens a scene, + adds
 * one. The open scene's panel sets its duration, its transition to the next
 * and that transition's time, and duplicates, deletes or moves it.
 *
 * The open scene's layers are the document's own (M.sceneOpen()), so every
 * tool edits it as before. The play bar's playhead, scrub and clock run
 * over every scene: the stage shows the scene the playhead is in (its
 * layers drawn from the closed scene's entry, never written to the
 * document) and a transition as Save animation draws it, then the open
 * scene again once the preview ends. Save animation draws the scenes one
 * after another (renderFrame() at the whole animation's time), the
 * previous scene's last moment under the next one's first while a
 * transition runs. A card dragged along the strip moves its scene.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_render.js
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

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, t = X.t, el = X.el, icon = X.icon, section = X.section, button = X.button;
    var PHONE = '(max-width: 599.98px)';
    // A transition's frame on the stage is drawn at most this many pixels wide.
    var FX_MAX = 1600;
    // A card moved this far is dragged, not clicked.
    var DRAG_PX = 6;
    var TRANSITION_ICONS = { none: 'm_none', fade: 'm_fade', slide: 'm_slide', zoom: 'm_zoom', wipe: 'm_wipe' };

    X.icons.s_earlier = 'M19 12H5M11 6l-6 6 6 6';
    X.icons.s_later = 'M5 12h14M13 6l6 6-6 6';

    function seconds(ms) {
        return X.decimal(ms / 1000) + ' ' + t('motionSecondsUnit');
    }

    // Scenes belong to a canvas: a picture's base is one server render.
    Editor.prototype.scenesOffered = function () {
        return !!this.doc.artboard && (this.tool === 'motion');
    };

    // ---- changing the scenes ----

    // Every scene change is one undo step; the selection belongs to the
    // scene that was open, and a preview stops at rest first.
    Editor.prototype.sceneChange = function (mutate) {
        if (this.time !== null) {
            this.stopMotion(true);
        }

        this.sel = [];
        this.sceneFocus = true;
        this.change(mutate);
    };

    Editor.prototype.openScene = function (id) {
        if (id && (id !== this.doc.scene)) {
            this.sceneChange(function (doc) { M.sceneOpen(doc, id); });
        } else {
            this.sel = [];
            this.sceneFocus = true;
            this.render();
        }
    };

    Editor.prototype.addScene = function (copy) {
        if (M.sceneCount(this.doc) >= M.SCENE_MAX) {
            this.showMessage(t('sceneMax').replace('{n}', M.SCENE_MAX), true);
            return;
        }

        this.sceneChange(function (doc) { M.sceneAdd(doc, copy); });
    };

    // ---- the whole timeline ----

    // With two scenes or more the play bar runs over all of them; the open
    // scene's layers move by their own time (motionNow()). sceneShift, while
    // a closed scene is drawn, is where that scene starts.
    var motionTotal = Editor.prototype.motionTotal, motionStart = Editor.prototype.motionStart;

    Editor.prototype.motionTotal = function () {
        return (M.sceneCount(this.doc) < 2) ? motionTotal.call(this) : M.sceneTotal(this.doc);
    };

    Editor.prototype.motionStart = function () {
        if (this.sceneShift !== null) {
            return this.sceneShift;
        }

        return (M.sceneCount(this.doc) < 2) ? motionStart.call(this) : M.sceneStartAt(this.doc, M.sceneIndex(this.doc));
    };

    // The stage at the playhead: the scene it is in, drawn as a document of
    // its own while the open scene's stays as it is; over a transition the
    // frame Save animation would draw.
    var applyMotion = Editor.prototype.applyMotion;

    Editor.prototype.applyMotion = function () {
        if ((this.time === null) || (M.sceneCount(this.doc) < 2)) {
            this.sceneShow(null);
            return applyMotion.call(this);
        }

        var live = this.doc, at = M.sceneAt(live, this.time);

        this.sceneShow(at.index);

        if (this.sceneShown === null) {
            applyMotion.call(this);
        } else {
            this.doc = M.sceneView(live, at.index);
            this.sceneShift = this.time - at.local;

            try {
                applyMotion.call(this);
            } finally {
                this.doc = live;
                this.sceneShift = null;
            }
        }

        this.sceneFx(at.from !== null);
    };

    // Shows scene index on the stage (null or the open one: the document's
    // own layers): its layers, its background; the selection belongs to the
    // open scene and hides meanwhile.
    Editor.prototype.sceneShow = function (index) {
        var live = this.doc, open = M.sceneIndex(live), other = (index !== null) && (index !== open) && (M.sceneCount(live) > 1);

        if (other && ((index !== this.sceneShown) || (this.layerGroups !== this.sceneGroups))) {
            this.doc = M.sceneView(live, index);
            this.layersDirty = true;

            try {
                this.renderLayers();
            } finally {
                this.doc = live;
            }

            this.sceneGroups = this.layerGroups;
        } else if (!other && (this.sceneShown !== null)) {
            this.layersDirty = true;
            this.renderLayers();
        }

        if (!other && (this.sceneShown === null)) {
            return;
        }

        this.sceneShown = other ? index : null;

        if (live.artboard) {
            var bg = other ? M.sceneList(live)[index].background : live.artboard.background;

            this.frame.style.background = (bg === 'transparent') ? '' : bg;
            this.frame.classList.toggle('pgie-checker', bg === 'transparent');
        }

        if (this.selEl) {
            this.selEl.style.visibility = other ? 'hidden' : '';
        }

        if (this.keyPathEl) {
            this.keyPathEl.style.visibility = other ? 'hidden' : '';
        }
    };

    // A transition on the stage: the whole animation's frame at the
    // playhead on a canvas over the frame (the scenes' layers hidden under
    // it once it is drawn). A frame still drawing lets the next moments
    // pass and draws the last one asked for.
    Editor.prototype.sceneFx = function (on) {
        var self = this, fx = this.sceneFxState;

        if (!on) {
            if (fx.el) {
                fx.el.remove();
                fx.el = null;
                this.layersSvg.style.visibility = '';
            }

            fx.want = false;
            return;
        }

        if (!fx.el) {
            fx.el = el('canvas', { class: 'pgie-sfx', 'aria-hidden': 'true' });
            this.frame.appendChild(fx.el);
        }

        if (fx.busy) {
            fx.want = true;
            return;
        }

        var vr = this.viewRect(), k = this.view.k, dpr = Math.min(2, window.devicePixelRatio || 1);
        var s = Math.min(dpr, FX_MAX / (Math.max(vr.w, vr.h) * k)), canvas = fx.el;
        var w = Math.max(1, Math.round(vr.w * k * s)), h = Math.max(1, Math.round(vr.h * k * s));

        fx.busy = true;
        fx.want = false;

        this.renderFrame(this.time, w, h, canvas).then(function () {
            fx.busy = false;

            if (fx.el === canvas) {
                self.layersSvg.style.visibility = 'hidden';

                if (fx.want && (self.time !== null)) {
                    self.sceneFx(true);
                }
            }
        }, function () {
            fx.busy = false;
        });
    };

    // Back to the open scene when the playhead leaves (a press on the
    // stage, another tool, a scene change).
    var stopMotion = Editor.prototype.stopMotion;

    Editor.prototype.stopMotion = function (quiet) {
        this.sceneFx(false);
        stopMotion.call(this, quiet);
        this.sceneShow(null);
    };

    // A preview that played through to the end without repeating ends on
    // the scene that was open, at rest.
    var pause = Editor.prototype.pause;

    Editor.prototype.pause = function () {
        var ended = (M.sceneCount(this.doc) > 1) && (this.time !== null) && (this.time >= this.motionTotal()) && (this.previewEnd === null);

        pause.call(this);

        if (ended && (this.sceneShown !== null)) {
            this.stopMotion();
            this.render();
        }
    };

    // Play first readies every scene's pictures, which a transition paints.
    var play = Editor.prototype.play;

    Editor.prototype.play = function () {
        if (M.sceneCount(this.doc) > 1) {
            this.layersReady();
        }

        return play.apply(this, arguments);
    };

    // Where each scene starts, under the scrub; the open one's span marked.
    var motionBar = Editor.prototype.motionBar;

    Editor.prototype.motionBar = function () {
        var bar = motionBar.call(this), list = M.sceneList(this.doc), total = this.motionTotal(), track = bar.querySelector('.pgie-mtrack');

        if ((list.length < 2) || !track || !(total > 0)) {
            return bar;
        }

        var marks = el('div', { class: 'pgie-mscenes', 'aria-hidden': 'true' }), start = 0;

        list.forEach(function (s) {
            var span = el('span', { class: s.open ? 'on' : '' });

            span.style.left = (100 * start / total) + '%';
            span.style.width = (100 * s.duration / total) + '%';
            marks.appendChild(span);
            start += s.duration;
        });

        track.insertBefore(marks, track.firstChild);

        return bar;
    };

    // ---- the strip ----

    // A scene's look, small: its background and its layers at rest. A
    // closed scene's picture is kept while its layers stay the same array.
    Editor.prototype.sceneThumb = function (s) {
        var a = this.doc.artboard, kept = !s.open && this.sceneThumbs.get(s.layers);

        if (kept && (kept.bg === s.background)) {
            return kept.svg.cloneNode(true);
        }

        var svg = document.createElementNS(SVGNS, 'svg'), self = this;

        svg.setAttribute('viewBox', '0 0 ' + a.width + ' ' + a.height);
        svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
        svg.setAttribute('class', 'pgie-sthumb');
        svg.setAttribute('aria-hidden', 'true');

        if (s.background && (s.background !== 'transparent')) {
            var bg = document.createElementNS(SVGNS, 'rect');

            bg.setAttribute('width', String(a.width));
            bg.setAttribute('height', String(a.height));
            bg.setAttribute('fill', s.background);
            svg.appendChild(bg);
        }

        s.layers.forEach(function (l) {
            var n = l.hidden ? null : self.layerNode(l);

            if (n) {
                svg.appendChild(n);
            }
        });

        if (!s.open) {
            this.sceneThumbs.set(s.layers, { bg: s.background, svg: svg.cloneNode(true) });
        }

        return svg;
    };

    Editor.prototype.sceneStrip = function () {
        var self = this, list = M.sceneList(this.doc), a = this.doc.artboard;
        var strip = el('div', { class: 'pgie-scenes', role: 'list', 'aria-label': t('scenesRegion') });

        strip.style.setProperty('--pgie-sratio', String(a.width / a.height));

        list.forEach(function (s, i) {
            var name = t('sceneName').replace('{n}', i + 1), open = s.open && self.sceneFocus;

            if (i > 0) {
                var tr = list[i - 1].transition, label = t('sceneTransitionTip').replace('{kind}', t('motion_' + tr.kind)).replace('{time}', seconds(tr.ms));

                strip.appendChild(el('div', { class: 'pgie-strans', role: 'listitem' }, [
                    button('pgie-ibtn sm' + ((tr.kind !== 'none') ? ' on' : ''), label, TRANSITION_ICONS[tr.kind], function () { self.openScene(list[i - 1].id); })
                ]));
            }

            var card = el('button', { type: 'button', class: 'pgie-scard' + (open ? ' on' : '') + (s.open ? ' pgie-sopen' : ''), 'data-scene': s.id, title: name, 'aria-label': name + ', ' + seconds(s.duration), onclick: function () {
                if (!self.sceneDragged) {
                    self.openScene(s.id);
                }

                self.sceneDragged = false;
            } }, [el('span', { class: 'pgie-snum', text: String(i + 1) }), self.sceneThumb(s)]);

            if (list.length > 1) {
                card.addEventListener('pointerdown', function (e) { self.sceneDragStart(e, card, s.id, i); });
            }

            if (s.open) {
                card.setAttribute('aria-current', 'true');
            }

            strip.appendChild(el('div', { class: 'pgie-scene', role: 'listitem' }, [
                card,
                el('span', { class: 'pgie-sdur', 'aria-hidden': 'true' }, [icon('clock'), el('span', { text: seconds(s.duration) })])
            ]));
        });

        var add = button('pgie-sadd', t('sceneAdd'), 'plus', function () { self.addScene(false); });

        add.disabled = list.length >= M.SCENE_MAX;
        strip.appendChild(el('div', { class: 'pgie-scene', role: 'listitem' }, [add]));

        return strip;
    };

    // A card dragged along the strip (a mouse or a pen: a finger scrolls the
    // strip, the panel's Move earlier and Move later do it there) follows
    // the pointer and moves its scene where it is let go: one undo step.
    Editor.prototype.sceneDragStart = function (e, card, id, at) {
        var self = this, x0 = e.clientX, moved = false, item = card.parentNode, strip = item.parentNode;

        if ((e.pointerType === 'touch') || (e.button !== 0)) {
            return;
        }

        var items = Array.prototype.filter.call(strip.querySelectorAll('.pgie-scard'), function (c) { return c !== card; });

        try {
            card.setPointerCapture(e.pointerId);
        } catch (err) {
            // A pointer the browser no longer tracks: the drag still follows moves.
        }

        var slot = function (ev) {
            return M.sceneSlot(items.map(function (c) {
                var r = c.getBoundingClientRect();

                return r.left + r.width / 2;
            }), ev.clientX);
        };

        var move = function (ev) {
            moved = moved || (Math.abs(ev.clientX - x0) > DRAG_PX);

            if (moved) {
                item.classList.add('pgie-sdrag');
                item.style.transform = 'translateX(' + (ev.clientX - x0) + 'px)';
            }
        };

        var up = function (ev) {
            card.removeEventListener('pointermove', move);
            card.removeEventListener('pointerup', up);
            card.removeEventListener('pointercancel', up);
            item.classList.remove('pgie-sdrag');
            item.style.transform = '';

            if (!moved) {
                return;
            }

            // The click that follows the release is the drag's, not a pick.
            self.sceneDragged = true;
            window.setTimeout(function () { self.sceneDragged = false; }, 0);

            var to = (ev.type === 'pointerup') ? slot(ev) : at;

            if (to !== at) {
                self.sceneChange(function (doc) { M.sceneMove(doc, id, to - at); });
            }
        };

        card.addEventListener('pointermove', move);
        card.addEventListener('pointerup', up);
        card.addEventListener('pointercancel', up);
    };

    // The strip as a row under the play bar (Editor.motionDocks).
    Editor.prototype.scenesKey = function () {
        return this.scenesOffered() ? [this.sceneStamp, this.sceneFocus ? 1 : 0, this.doc.scene || ''].join(':') : '';
    };

    Editor.prototype.scenesNode = function () {
        if (!this.scenesOffered()) {
            return null;
        }

        var self = this, focused = !!this.sceneDock && this.sceneDock.contains(document.activeElement);

        this.sceneDock = el('div', { class: 'pgie-sdock' }, [this.sceneStrip()]);

        // A click on a card draws the strip again: the focus goes to the
        // open scene's new card.
        if (focused) {
            window.setTimeout(function () {
                var card = self.sceneDock && self.sceneDock.querySelector('.pgie-sopen');

                if (card) {
                    card.focus();
                }
            }, 0);
        }

        return this.sceneDock;
    };

    // ---- the open scene's panel ----

    Editor.prototype.scenePanel = function (body) {
        var self = this, list = M.sceneList(this.doc), at = 0;

        list.forEach(function (s, i) {
            if (s.open) {
                at = i;
            }
        });

        var s = list[at], last = (at === list.length - 1), many = list.length > 1;
        var length = el('input', { type: 'range', class: 'pgie-range', min: String(M.MOTION_MIN), max: String(M.MOTION_MAX), step: '500', value: String(s.duration), 'aria-label': t('sceneDuration') });
        var shown = el('span', { class: 'pgie-val', text: seconds(s.duration) });

        body.appendChild(el('h3', { class: 'pgie-sname', text: t('sceneName').replace('{n}', at + 1) }));

        length.addEventListener('input', function () {
            shown.textContent = seconds(Number(length.value));
            self.change(function (doc) { M.sceneSet(doc, s.id, { duration: Number(length.value) }); }, 'scene-duration', true);
        });
        length.addEventListener('change', function () {
            self.history.key = null;
            self.render();
        });
        body.appendChild(section(t('sceneDuration'), length, shown));

        // The last scene has no next one: its transition would never play.
        if (many && !last) {
            var tr = s.transition, kinds = el('div', { class: 'pgie-tiles pgie-skinds', role: 'radiogroup', 'aria-label': t('sceneTransition') });

            M.SCENE_TRANSITIONS.forEach(function (kind) {
                var on = (tr.kind === kind), b = X.tile(TRANSITION_ICONS[kind], t('motion_' + kind), function () {
                    self.change(function (doc) { M.sceneSet(doc, s.id, { transition: { kind: kind } }); });
                });

                b.classList.toggle('on', on);
                b.setAttribute('role', 'radio');
                b.setAttribute('aria-checked', on ? 'true' : 'false');
                kinds.appendChild(b);
            });

            body.appendChild(section(t('sceneTransition'), kinds));

            if (tr.kind !== 'none') {
                var time = el('input', { type: 'range', class: 'pgie-range', min: String(M.SCENE_TRANSITION_MIN), max: String(M.SCENE_TRANSITION_MAX), step: '100', value: String(tr.ms), 'aria-label': t('sceneTransitionTime') });
                var timeShown = el('span', { class: 'pgie-val', text: seconds(tr.ms) });

                time.addEventListener('input', function () {
                    timeShown.textContent = seconds(Number(time.value));
                    self.change(function (doc) { M.sceneSet(doc, s.id, { transition: { ms: Number(time.value) } }); }, 'scene-transition', true);
                });
                time.addEventListener('change', function () {
                    self.history.key = null;
                    self.render();
                });
                body.appendChild(section(t('sceneTransitionTime'), time, timeShown));
            }
        }

        var acts = [
            [t('sceneDuplicate'), 'duplicate', function () { self.addScene(true); }, list.length >= M.SCENE_MAX],
            [t('sceneDelete'), 'trash', function () { self.sceneChange(function (doc) { M.sceneDelete(doc, s.id); }); }, !many],
            [t('sceneEarlier'), 's_earlier', function () { self.sceneChange(function (doc) { M.sceneMove(doc, s.id, -1); }); }, at === 0],
            [t('sceneLater'), 's_later', function () { self.sceneChange(function (doc) { M.sceneMove(doc, s.id, 1); }); }, last]
        ];

        body.appendChild(el('div', { class: 'pgie-tiles pgie-sacts' }, acts.map(function (a) {
            var b = X.tile(a[1], a[0], a[2]);

            b.disabled = a[3];

            return b;
        })));

        body.appendChild(el('p', { class: 'pgie-note', text: t('sceneNote') }));
    };

    // The Animate panel: the open scene's after a click on the strip, until
    // a layer is selected; on a phone the strip comes first in the sheet.
    var motionPanel = Editor.panels.motion;

    Editor.panels.motion = function (body) {
        if (!this.doc.artboard) {
            motionPanel.call(this, body);
            return;
        }

        if (this.selected().length) {
            this.sceneFocus = false;
        }

        if (window.matchMedia && window.matchMedia(PHONE).matches) {
            body.appendChild(this.sceneStrip());
        }

        if (this.sceneFocus) {
            this.scenePanel(body);
        } else {
            motionPanel.call(this, body);
        }
    };

    // Delete or Backspace while the open scene's panel shows (after a click
    // on its card, no layer selected) deletes that scene as the panel's
    // Delete does; the last one stays.
    Editor.keys.push(function (e, mod, k) {
        var id = this.doc.scene;

        if (mod || ((k !== 'Delete') && (k !== 'Backspace')) || !this.scenesOffered() || !this.sceneFocus || this.selected().length || (M.sceneCount(this.doc) < 2)) {
            return false;
        }

        this.sceneChange(function (doc) { M.sceneDelete(doc, id); });

        return true;
    });

    // A press on the stage goes back to the layers' motion.
    var motionDown = Editor.pointers.motion.down;

    Editor.pointers.motion.down = function (e) {
        if (this.sceneFocus) {
            this.sceneFocus = false;
            this.render();
        }

        return motionDown.call(this, e);
    };

    // ---- frames across scenes ----

    // The whole animation's moment: the scene playing at its own time, and
    // over the start of a scene the previous one's last moment while the
    // transition runs (M.sceneAt(), M.sceneTransitionAt()).
    var renderFrame = Editor.prototype.renderFrame;

    Editor.prototype.renderFrame = function (time, w, h, canvas, doc) {
        if (doc || (M.sceneCount(this.doc) < 2)) {
            return renderFrame.call(this, time, w, h, canvas, doc);
        }

        var self = this, at = M.sceneAt(this.doc, time), list = M.sceneList(this.doc), show = M.sceneView(this.doc, at.index);

        canvas = canvas || document.createElement('canvas');

        if (at.from === null) {
            return renderFrame.call(this, at.local, w, h, canvas, show);
        }

        var pair = this.scenePair || (this.scenePair = [document.createElement('canvas'), document.createElement('canvas')]);
        var fx = M.sceneTransitionAt(at.kind, at.p, w, h);

        return renderFrame.call(this, list[at.from].duration, w, h, pair[0], M.sceneView(this.doc, at.from)).then(function () {
            return renderFrame.call(self, at.local, w, h, pair[1], show);
        }).then(function () {
            var g = canvas.getContext('2d');

            if ((canvas.width !== w) || (canvas.height !== h)) {
                canvas.width = w;
                canvas.height = h;
            }

            g.setTransform(1, 0, 0, 1, 0, 0);
            g.clearRect(0, 0, w, h);
            paintScene(g, pair[0], fx.from, w, h);
            paintScene(g, pair[1], fx.to, w, h);

            return canvas;
        });
    };

    // A scene's frame onto g as a transition places it.
    function paintScene(g, frame, st, w, h) {
        if (!st || (st.alpha <= 0)) {
            return;
        }

        g.save();
        g.globalAlpha = Math.min(1, st.alpha);

        if (st.clip) {
            g.beginPath();
            g.rect(st.clip.x, st.clip.y, st.clip.w, st.clip.h);
            g.clip();
        }

        g.translate(w / 2 + st.x, h / 2 + st.y);
        g.scale(st.scale, st.scale);
        g.drawImage(frame, -w / 2, -h / 2, w, h);
        g.restore();
    }

    // Before the frames: what every scene's layers need for painting.
    var layersReady = Editor.prototype.layersReady;

    Editor.prototype.layersReady = function () {
        if (M.sceneCount(this.doc) < 2) {
            return layersReady.call(this);
        }

        var self = this, live = this.doc, waits = M.sceneList(live).map(function (s) {
            self.doc = Object.assign({}, live, { layers: s.layers });

            try {
                return layersReady.call(self);
            } finally {
                self.doc = live;
            }
        });

        return Promise.all(waits);
    };

    // Each whole render (a change made, undone or redone, a drag ended)
    // draws the strip again with the open scene's thumbnail; a drag's moves
    // only redraw the view.
    var render = Editor.prototype.render;

    Editor.prototype.render = function () {
        this.sceneStamp++;

        return render.apply(this, arguments);
    };

    Editor.inits.push(function () {
        this.sceneFocus = false;
        this.sceneStamp = 0;
        this.sceneThumbs = new WeakMap();
        this.scenePair = null;
        this.sceneDock = null;
        this.sceneShown = null;
        this.sceneShift = null;
        this.sceneGroups = null;
        this.sceneFxState = { el: null, busy: false, want: false };
        this.sceneDragged = false;
    });

    Editor.motionDocks.push({ key: Editor.prototype.scenesKey, node: Editor.prototype.scenesNode, update: function () {}, note: function () { return null; } });
}(window.PgImageEditor.parts));
