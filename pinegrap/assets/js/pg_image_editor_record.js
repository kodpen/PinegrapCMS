/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: a recorded move. In the Animate tool a Record button
 * beside Play arms the selected layer; while it is dragged the clock runs
 * from the playhead (at 0.5x, 1x or 2x) and the layer's place is read
 * every frame; letting go stops. The samples become linear keyframes
 * (M.recordApply(), the RECORD section of the model) that replace the
 * keys of the recorded span, one undo step. A corner handle records the
 * size, a second finger the turn; a recording past the end lengthens the
 * document. On a phone the sheet folds and a pill over the stage holds
 * Stop, the clock and the speed.
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

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, button = X.button, section = X.section;

    var STILL = ['redact'];
    var PHONE = '(max-width: 599.98px)';
    var CORNERS = ['nw', 'ne', 'se', 'sw'];

    X.icons.record = 'M12 6.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11z';
    X.icons.recStop = 'M7.5 7.5h9v9h-9z';

    function onPhone() {
        return !!(window.matchMedia && window.matchMedia(PHONE).matches);
    }

    function rateText(r) {
        return X.decimal(r) + '×';
    }

    // The playhead as m:ss.d, as the play bar shows it.
    function clockText(ms) {
        var tenths = Math.max(0, Math.round(ms / 100)), m = Math.floor(tenths / 600), s = (tenths % 600) / 10;
        var cfg = window.pgImageEditorConfig, sep = (cfg && cfg.decimal) || '.';

        return m + ':' + ((s < 10) ? '0' : '') + s.toFixed(1).replace('.', sep);
    }

    // The one selected layer that may move, or null.
    Editor.prototype.recLayer = function () {
        var l = (this.sel.length === 1) ? this.layer(this.sel[0]) : null;

        return (l && !l.hidden && !l.locked && (STILL.indexOf(l.type) < 0)) ? l : null;
    };

    // ---- arming ----

    Editor.prototype.recToggle = function () {
        if (this.recording) {
            this.recFinish();
        } else if (this.recArmed) {
            this.recDisarm();
        } else {
            this.recArm();
        }
    };

    Editor.prototype.recArm = function () {
        if (!this.recLayer()) {
            return;
        }

        if (this.playing) {
            this.pause();
        }

        this.recArmed = true;
        this.motionTab = 'keys';

        // The playhead stands where the clock will start.
        if (this.time === null) {
            this.time = this.motionStart();
        }

        // A phone's sheet folds so the whole stage is free.
        if (onPhone()) {
            this.recSheet = this.sheetOpen;
            this.sheetOpen = false;
        }

        this.render();
    };

    Editor.prototype.recDisarm = function () {
        this.recArmed = false;

        if (this.recSheet !== null) {
            this.sheetOpen = this.recSheet;
            this.recSheet = null;
        }

        this.render();
    };

    // ---- recording ----

    // A press on the armed layer where it shows at the playhead starts the
    // clock; true when it did.
    Editor.prototype.recDown = function (e) {
        var l = this.recLayer(), h = e.target.getAttribute && e.target.getAttribute('data-h'), p, now, hit, base, st, corner;

        if (!this.recArmed || !l || (e.button > 0) || (h === 'rot')) {
            return false;
        }

        now = (this.time === null) ? 0 : this.motionNow();

        if (!(now >= 0) || (now >= M.MOTION_MAX)) {
            return false;
        }

        p = this.toDoc(e.clientX, e.clientY);
        corner = (CORNERS.indexOf(h) >= 0) && !(e.target.closest && e.target.closest('.pgie-selmoved'));
        hit = corner ? l : this.keyHit(p, h ? 10 : 4);

        if (!hit || (hit.id !== l.id)) {
            return false;
        }

        if (this.playing) {
            this.playing = false;
            window.cancelAnimationFrame(this.raf);
        }

        base = M.keyAt(l, now) || { x: 0, y: 0, scale: 1, rot: 0 };
        st = M.motionAt(l, now, this.motionEnd());

        this.time = this.motionStart() + now;
        this.recording = {
            id: l.id,
            start: M.clone(l),
            before: M.clone(this.doc),
            from: now,
            real: window.performance.now(),
            rate: this.recRate,
            end: this.motionEnd(),
            base: { x: base.x, y: base.y, scale: base.scale, rot: base.rot },
            at: p,
            p: p,
            corner: corner ? { c: [(Number(l.x) || 0) + (Number(l.w) || 0) / 2 + st.x, (Number(l.y) || 0) + (Number(l.h) || 0) / 2 + st.y] } : null,
            twist: null,
            samples: [],
            raf: 0
        };
        this.recording.corner = corner ? Object.assign(this.recording.corner, { d: Math.max(1, Math.hypot(p[0] - this.recording.corner.c[0], p[1] - this.recording.corner.c[1])) }) : null;
        this.drag = { kind: 'rec' };
        this.root.classList.add('pgie-recording');
        e.preventDefault();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }

        this.renderMotion();
        this.recSample(this.recording.real);
        this.recTick();

        return true;
    };

    Editor.prototype.recTick = function () {
        var self = this, r = this.recording;

        r.raf = window.requestAnimationFrame(function (now) {
            if ((self.recording !== r) || !self.dialog.open) {
                return;
            }

            if (self.recSample(now)) {
                self.recTick();
            } else {
                self.recFinish();
            }
        });
    };

    // Where the layer is at real time now: one sample, the keys so far on
    // the stage. False once the clock reached the longest animation.
    Editor.prototype.recSample = function (now) {
        var r = this.recording, clock = M.recordClock(r.from, now - r.real, r.rate), more = clock < M.MOTION_MAX, s, ids;

        clock = Math.min(clock, M.MOTION_MAX);
        s = { t: clock, x: r.base.x + r.p[0] - r.at[0], y: r.base.y + r.p[1] - r.at[1] };

        if (r.corner) {
            s.x = r.base.x;
            s.y = r.base.y;
            s.scale = r.base.scale * Math.hypot(r.p[0] - r.corner.c[0], r.p[1] - r.corner.c[1]) / r.corner.d;
        }

        ids = Object.keys(this.pointers);

        if (r.twist && (ids.length === 2)) {
            var mid = this.centroid(ids), q = this.toDoc(mid.x, mid.y);

            s.x = r.twist.x + q[0] - r.twist.mid[0];
            s.y = r.twist.y + q[1] - r.twist.mid[1];
            s.rot = r.twist.rot + (this.fingerAngle(ids) - r.twist.from) * 180 / Math.PI;
            r.twist.last = s;
        } else if (r.twist && r.twist.last) {
            s = Object.assign({}, r.twist.last, { t: clock });
        }

        r.samples.push(s);
        this.recShow(clock);

        return more;
    };

    // The samples so far, unthinned, as the layer's keys between the keys
    // before and after; the document as long as the clock.
    Editor.prototype.recShow = function (clock) {
        var r = this.recording, old = M.keyList(r.start), from = r.from, end = Math.max(r.end, Math.min(M.MOTION_MAX, Math.ceil(clock / 100) * 100));
        var live = old.filter(function (k) { return (k.t < from - 5) || (k.t > clock + 5); }).concat(r.samples.map(function (s) {
            var was = M.keyAt({ anim: { keys: old } }, s.t) || { scale: 1, rot: 0, opacity: 1 };

            return { t: s.t, x: s.x, y: s.y, scale: (s.scale !== undefined) ? s.scale : was.scale, rot: (s.rot !== undefined) ? s.rot : was.rot, opacity: was.opacity, ease: 'linear' };
        }));
        var l = this.layer(r.id);

        if (!l) {
            return;
        }

        l.anim = Object.assign({}, l.anim || {}, { keys: live });

        if (end !== this.motionEnd()) {
            this.doc.motion = Object.assign(M.docMotion(this.doc), { duration: end });
        }

        this.layersDirty = true;
        this.time = this.motionStart() + clock;
        this.recBarEnd();
        this.applyMotion();
        this.updateBar();
        this.drawKeyPath();
    };

    // A lengthened document: the scrub and the clock's total follow.
    Editor.prototype.recBarEnd = function () {
        var e = this.motionEls, total = this.motionTotal(), tail;

        if (!e || (Number(e.scrub.max) === total)) {
            return;
        }

        e.scrub.max = String(total);
        tail = e.clock.nextSibling;

        if (tail) {
            tail.textContent = ' / ' + clockText(total);
        }
    };

    // Letting go: the thinned keys, one undo step; the playhead stays where
    // the recording ended.
    Editor.prototype.recFinish = function () {
        var r = this.recording, res, l;

        if (!r) {
            return;
        }

        window.cancelAnimationFrame(r.raf);
        this.recording = null;
        this.drag = null;
        this.root.classList.remove('pgie-recording');
        // About a screen pixel: at 30 % a document pixel is far finer than
        // a pointer can be put.
        res = M.recordApply(r.start, r.samples, r.end, Math.max(1, 1 / this.view.k));
        l = this.layer(r.id);

        if (l) {
            l.anim = Object.assign({}, r.start.anim || {}, { keys: res.keys });

            if (!res.keys.length) {
                delete l.anim.keys;
            }
        }

        if (res.end !== r.end) {
            this.doc.motion = Object.assign(M.docMotion(this.doc), { duration: res.end });
        } else {
            this.doc.motion = r.before.motion ? M.clone(r.before.motion) : null;

            if (!this.doc.motion) {
                delete this.doc.motion;
            }
        }

        this.history.push(r.before, null);
        this.settleDirty();
        this.layersDirty = true;
        this.renderMeta();
        this.recDisarm();
    };

    // Escape: nothing recorded, the document as it was.
    Editor.prototype.recCancel = function () {
        var r = this.recording;

        if (!r) {
            return;
        }

        window.cancelAnimationFrame(r.raf);
        this.recording = null;
        this.drag = null;
        this.root.classList.remove('pgie-recording');
        this.doc = r.before;
        this.time = this.motionStart() + r.from;
        this.layersDirty = true;
        this.recDisarm();
    };

    // Leaving the Animate tool while armed: back to rest without a redraw
    // (this runs inside one).
    Editor.prototype.recQuiet = function () {
        var r = this.recording;

        if (r) {
            window.cancelAnimationFrame(r.raf);
            this.recording = null;
            this.drag = null;
            this.root.classList.remove('pgie-recording');
            this.doc = r.before;
            this.layersDirty = true;
        }

        this.recArmed = false;

        if (this.recSheet !== null) {
            this.recSheet = null;
        }
    };

    // ---- the stage's pointers ----

    var pointers = Editor.pointers.motion;

    Editor.pointers.motion = {
        down: function (e) {
            if (!this.recDown(e)) {
                pointers.down.call(this, e);
            }
        },
        move: function (e) {
            if (this.drag && (this.drag.kind === 'rec')) {
                if (this.recording && (Object.keys(this.pointers).length < 2)) {
                    this.recording.p = this.toDoc(e.clientX, e.clientY);
                }

                return;
            }

            pointers.move.call(this, e);
        },
        up: function (e) {
            if (this.drag && (this.drag.kind === 'rec')) {
                this.recFinish();
                return;
            }

            pointers.up.call(this, e);
        },
        double: pointers.double
    };

    // A second finger while recording a move turns the layer too: its
    // angle and the fingers' middle are recorded from then on.
    var startTwist = Editor.prototype.startTwist;

    Editor.prototype.startTwist = function (ids) {
        var r = this.recording, last, mid;

        if (!r || (ids.length !== 2)) {
            return startTwist ? startTwist.call(this, ids) : false;
        }

        if (r.corner || r.twist) {
            return true;
        }

        last = r.samples[r.samples.length - 1] || { x: r.base.x, y: r.base.y };
        mid = this.centroid(ids);
        r.twist = { from: this.fingerAngle(ids), mid: this.toDoc(mid.x, mid.y), x: last.x, y: last.y, rot: (last.rot !== undefined) ? last.rot : r.base.rot, last: null };

        return true;
    };

    // ---- the play bar, the panel, the tracks ----

    Editor.prototype.recButton = function (cls) {
        var self = this, on = !!(this.recArmed || this.recording);
        var b = button(cls + (on ? ' on' : ''), on ? t('recStop') : t('recRecord'), on ? 'recStop' : 'record', function () { self.recToggle(); }, cls.indexOf('pgie-btn') >= 0);

        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        b.disabled = !on && !this.recLayer();

        if (b.disabled) {
            b.title = t('recPick');
        }

        return b;
    };

    // Record beside Play; while armed the clock says so.
    var motionBar = Editor.prototype.motionBar;

    Editor.prototype.motionBar = function () {
        var bar = motionBar.call(this), play = bar.querySelector('.pgie-mplay'), clock = bar.querySelector('.pgie-mclock');

        if (play) {
            play.parentNode.insertBefore(this.recButton('pgie-mrec'), play.nextSibling);
        }

        if (clock && (this.recArmed || this.recording)) {
            bar.classList.add('pgie-mrecbar');
            clock.insertBefore(el('span', { class: 'pgie-mrecbadge', text: t('recBadge') }), clock.firstChild);
        }

        return bar;
    };

    // The Path tab: recording first, then the keys.
    Editor.prototype.recPanel = function (body, layers) {
        var self = this, rates = el('div', { class: 'pgie-seg pgie-recrates', role: 'radiogroup', 'aria-label': t('recRate') });

        if (layers.length !== 1) {
            return;
        }

        body.appendChild(el('h3', { class: 'pgie-mwhole', text: t('recTitle') }));
        body.appendChild(el('div', { class: 'pgie-actions pgie-recact' }, [this.recButton('pgie-btn pgie-recbtn')]));

        M.RECORD_RATES.forEach(function (r) {
            var on = (self.recRate === r);

            rates.appendChild(el('button', { type: 'button', role: 'radio', class: on ? 'on' : '', 'aria-checked': on ? 'true' : 'false', text: rateText(r), disabled: self.recording ? 'disabled' : null, onclick: function () {
                self.recRate = r;
                self.renderPanel();
            } }));
        });

        body.appendChild(section(t('recRate'), rates));
        body.appendChild(el('p', { class: 'pgie-note', text: t('recNote') }));
    };

    Editor.motionPanes.forEach(function (pane) {
        if (pane.id !== 'keys') {
            return;
        }

        var own = pane.panel;

        pane.panel = function (body, layers) {
            this.recPanel(body, layers);
            own.call(this, body, layers);
        };
    });

    // The recorded span in red on the layer's lane.
    Editor.trackMarks.push(function (lane, l, end) {
        var r = this.recording;

        if (r && (r.id === l.id)) {
            this.recSpan = el('span', { class: 'pgie-trrec', 'aria-hidden': 'true' });
            lane.appendChild(this.recSpan);
            this.recSpanAt(end);
        }
    });

    Editor.prototype.recSpanAt = function (end) {
        var r = this.recording, s = this.recSpan, now = this.motionNow();

        if (!r || !s || !(end > 0)) {
            return;
        }

        s.style.left = (100 * r.from / end) + '%';
        s.style.width = (100 * Math.max(0, Math.min(end, now) - r.from) / end) + '%';
    };

    Editor.motionDocks.push({
        key: function () { return this.recording ? 'rec:' + this.recording.id : (this.recArmed ? 'armed' : ''); },
        node: function () { return null; },
        update: function () {
            this.recSpanAt(this.motionEnd());
            this.recPillUpdate();
        },
        note: function () { return null; }
    });

    // ---- the phone's pill and the hint ----

    Editor.prototype.recView = function () {
        var on = !!(this.recArmed || this.recording) && (this.tool === 'motion');

        // Another tool: the recording ends unsaved, the sheet as it was.
        if (!on && (this.recArmed || this.recording)) {
            this.recQuiet();
        }

        this.root.classList.toggle('pgie-recarmed', on);

        if (on) {
            this.hint.textContent = t('recHint');
        }

        if (!on || !onPhone()) {
            if (this.recPill) {
                this.recPill.remove();
                this.recHintEl.remove();
                this.recPill = null;
                this.recHintEl = null;
            }

            return;
        }

        if (!this.recPill) {
            var self = this, speed = el('button', { type: 'button', class: 'pgie-recspeed', title: t('recRate'), onclick: function () {
                var list = M.RECORD_RATES;

                if (!self.recording) {
                    self.recRate = list[(list.indexOf(self.recRate) + 1) % list.length];
                    self.recPillUpdate();
                }
            } });

            this.recPill = el('div', { class: 'pgie-recpill', role: 'group', 'aria-label': t('recTitle') }, [
                button('pgie-mrec on', t('recStop'), 'recStop', function () { self.recToggle(); }),
                el('span', { class: 'pgie-mrecbadge', text: t('recBadge') }),
                el('span', { class: 'pgie-mclock' }, [el('strong'), el('span')]),
                speed
            ]);
            // The pill's presses are its own: a pointerup reaching the stage
            // would count two quick taps on the speed as a double tap (zoom).
            ['pointerdown', 'pointerup', 'pointercancel', 'dblclick'].forEach(function (type) {
                self.recPill.addEventListener(type, function (e) { e.stopPropagation(); });
            });
            this.recHintEl = el('p', { class: 'pgie-rechint', 'aria-live': 'polite', text: t('recHintPhone') });
            this.stage.appendChild(this.recPill);
            this.stage.appendChild(this.recHintEl);
        }

        this.recPillUpdate();
    };

    Editor.prototype.recPillUpdate = function () {
        var p = this.recPill, now = (this.time === null) ? 0 : this.time;

        if (!p) {
            return;
        }

        p.querySelector('.pgie-mclock strong').textContent = clockText(now);
        p.querySelector('.pgie-mclock span').textContent = ' / ' + clockText(this.motionTotal());
        p.querySelector('.pgie-recspeed').textContent = rateText(this.recRate);
    };

    Editor.prototype.recEscape = function () {
        if (this.recording) {
            this.recCancel();
            return true;
        }

        if (this.recArmed) {
            this.recDisarm();
            return true;
        }

        return false;
    };

    Editor.inits.push(function () {
        this.recArmed = false;
        this.recording = null;
        this.recRate = 1;
        this.recSheet = null;
        this.recSpan = null;
        this.recPill = null;
        this.recHintEl = null;
    });

    Editor.views.push(Editor.prototype.recView);
    Editor.escapes.unshift(Editor.prototype.recEscape);
}(window.PgImageEditor.parts));
