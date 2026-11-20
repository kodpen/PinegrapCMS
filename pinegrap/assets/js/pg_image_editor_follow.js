/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: Follow. In the Animate tool, with one layer selected over a
 * video, Follow (in the Path tab beside Recording a move) asks for a box
 * over the thing to follow; the clip's frames from the playhead on are read
 * from a decoder of their own, as grey at 320 px (a small thing larger:
 * only the region round its last place, at a scale where the box's shorter
 * side is 24 px, M.followSize()), and searched for that patch by the tracker (pg_image_editor_track.js, a worker, or on the page
 * in slices where a site's policy refuses it). The layer keeps its place at
 * the playhead and moves by what the patch moved (M.followSamples()); the
 * samples become keys as a recorded move's do (M.followApply()), one undo
 * step. A pill over the stage shows the share done and Cancel; the path
 * grows as samples come. Lost keeps the keys up to there and says where;
 * why and what helps the first time only (M.followLostText()).
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

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, button = X.button, pct = X.pct;

    X.icons.follow = 'M11 2h2v5h-2zM11 17h2v5h-2zM2 11h5v2H2zM17 11h5v2h-5zM12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8zm0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4z';

    // A phone held upright, as Record's: the sheet folds while Follow runs.
    var PHONE = '(max-width: 599.98px)';

    // A touch screen with no fine pointer: no key to name in a hint.
    function touchScreen() {
        return !!(window.matchMedia && window.matchMedia('(hover: none) and (pointer: coarse)').matches);
    }

    // The playhead as m:ss.d, as the play bar shows it.
    function clockText(ms) {
        var tenths = Math.max(0, Math.round(ms / 100)), m = Math.floor(tenths / 600), s = (tenths % 600) / 10;
        var cfg = window.pgImageEditorConfig, sep = (cfg && cfg.decimal) || '.';

        return m + ':' + ((s < 10) ? '0' : '') + s.toFixed(1).replace('.', sep);
    }

    function now() {
        return (window.performance && window.performance.now) ? window.performance.now() : Date.now();
    }

    // A source frame's length (ms): the commonest step between the video
    // track's sample times (µs); 0 when it cannot be told.
    function frameLength(track) {
        var ts = ((track && track.samples) || []).map(function (s) { return s.ts; }).sort(function (a, b) { return a - b; }), steps = [], i;

        for (i = 1; i < ts.length; i++) {
            if (ts[i] > ts[i - 1]) {
                steps.push(ts[i] - ts[i - 1]);
            }
        }

        steps.sort(function (a, b) { return a - b; });

        return steps.length ? steps[Math.floor(steps.length / 2)] / 1000 : 0;
    }

    // The tracker: the worker, or the same code on the page in slices.
    // Answers a promise of { post(m, transfer), stop() } whose answers go to
    // onAnswer.
    function tracker(onAnswer) {
        var url = (X.config().track || [])[0], worker = null;

        try {
            worker = url ? new Worker(url) : null;
        } catch (err) {
            worker = null;
        }

        if (worker) {
            worker.onmessage = function (e) { onAnswer(e.data || {}); };
            worker.onerror = function (e) {
                e.preventDefault();
                onAnswer({ type: 'error', code: 'worker' });
            };

            return Promise.resolve({
                post: function (m, transfer) { worker.postMessage(m, transfer || []); },
                stop: function () { worker.terminate(); }
            });
        }

        return new Promise(function (resolve, reject) {
            if (window.PgImageTrack || !url) {
                resolve(window.PgImageTrack);
                return;
            }

            var tag = document.createElement('script');

            tag.src = url;
            tag.onload = function () { resolve(window.PgImageTrack); };
            tag.onerror = reject;
            document.head.appendChild(tag);
        }).then(function (T) {
            if (!T) {
                throw new Error('track');
            }

            var session = T.pageSession(onAnswer);

            return {
                post: function (m) { session.receive(m); },
                stop: function () { session.stop(); }
            };
        });
    }

    // The one selected layer Follow may move: as Record's, and not a video.
    Editor.prototype.followLayer = function () {
        var l = this.recLayer();

        return (l && (l.type !== 'video')) ? l : null;
    };

    // Whether Follow is offered over the object: the Animate tool, a layer
    // it may move and a video showing at the playhead.
    Editor.prototype.followOffered = function () {
        var at = (this.time === null) ? 0 : this.motionNow();

        return (this.tool === 'motion') && !!this.followLayer() && this.doc.layers.some(function (l) {
            return (l.type === 'video') && !l.hidden && (M.clipAt(l, at) !== null);
        });
    };

    // ---- arming and the box ----

    Editor.prototype.followToggle = function () {
        if (this.follow) {
            this.followStop();
        } else {
            this.followArm();
        }
    };

    // Armed: the next drag draws the box. With no video showing at the
    // playhead it says so and stays at rest.
    Editor.prototype.followArm = function () {
        var at;

        if (!this.followLayer()) {
            return;
        }

        if (this.playing) {
            this.pause();
        }

        if (this.time === null) {
            this.time = this.motionStart();
        }

        at = this.motionNow();

        if (!this.doc.layers.some(function (l) { return (l.type === 'video') && !l.hidden && (M.clipAt(l, at) !== null); })) {
            this.showMessage(t('followNoVideo'), true);
            return;
        }

        if (this.recArmed || this.recording) {
            this.recEscape();
        }

        this.follow = { phase: 'armed' };
        this.motionTab = 'keys';

        // A phone's sheet folds so the whole stage is free for the box, as
        // Record's does; it comes back when Follow ends.
        if (window.matchMedia && window.matchMedia(PHONE).matches) {
            this.followSheet = this.sheetOpen;
            this.sheetOpen = false;
        }

        this.render();
    };

    // Cancel at any phase: the document as it was.
    Editor.prototype.followStop = function () {
        var f = this.follow;

        if (!f) {
            return;
        }

        this.followQuiet();

        if (f.phase === 'running') {
            this.time = this.motionStart() + f.from;
        }

        this.render();
    };

    // Back to rest without a redraw: the run ended, its decoder and tracker
    // closed, the document as it was.
    Editor.prototype.followQuiet = function () {
        var f = this.follow;

        if (!f) {
            return;
        }

        this.follow = null;
        this.followEnd(f);

        if (f.phase === 'running') {
            this.doc = f.before;
            this.layersDirty = true;
        }
    };

    Editor.prototype.followEnd = function (f) {
        f.done = true;

        if (f.tracker) {
            f.tracker.stop();
            f.tracker = null;
        }

        if (f.source) {
            f.source.then(function (s) { s.close(); }, function () {});
            f.source = null;
        }

        if (f.boxEl) {
            f.boxEl.remove();
            f.boxEl = null;
        }

        this.root.classList.remove('pgie-following');

        if (this.followSheet !== null) {
            this.sheetOpen = this.followSheet;
            this.followSheet = null;
        }

        if (this.drag && (this.drag.kind === 'follow')) {
            this.drag = null;
        }
    };

    // A press while armed starts the box; true when it was Follow's.
    Editor.prototype.followDown = function (e) {
        var f = this.follow;

        if (!f) {
            return false;
        }

        e.preventDefault();

        if ((f.phase !== 'armed') || (e.button > 0)) {
            return true;
        }

        f.phase = 'drawing';
        f.a = [e.clientX, e.clientY];
        f.b = f.a;
        f.boxEl = el('div', { class: 'pgie-followbox', 'aria-hidden': 'true' });
        // The stage hands moves and the release to a tool only while it drags.
        this.drag = { kind: 'follow' };
        this.stage.appendChild(f.boxEl);
        this.followBoxShow();

        try {
            this.stage.setPointerCapture(e.pointerId);
        } catch (err) {
            // The pointer is already gone.
        }

        return true;
    };

    Editor.prototype.followBoxShow = function () {
        var f = this.follow, r = this.stage.getBoundingClientRect();

        if (!f || !f.boxEl) {
            return;
        }

        f.boxEl.style.left = (Math.min(f.a[0], f.b[0]) - r.left) + 'px';
        f.boxEl.style.top = (Math.min(f.a[1], f.b[1]) - r.top) + 'px';
        f.boxEl.style.width = Math.abs(f.b[0] - f.a[0]) + 'px';
        f.boxEl.style.height = Math.abs(f.b[1] - f.a[1]) + 'px';
    };

    // Letting go: a box at least FOLLOW_BOX px a side starts; a smaller one
    // is dropped and Follow stays armed.
    Editor.prototype.followUp = function () {
        var f = this.follow;

        if (this.drag && (this.drag.kind === 'follow')) {
            this.drag = null;
        }

        if (!f || (f.phase !== 'drawing')) {
            return;
        }

        if ((Math.abs(f.b[0] - f.a[0]) < M.FOLLOW_BOX) || (Math.abs(f.b[1] - f.a[1]) < M.FOLLOW_BOX)) {
            f.phase = 'armed';
            f.boxEl.remove();
            f.boxEl = null;
            return;
        }

        this.followStart();
    };

    // ---- the run ----

    // The clip under the box's centre, the span, the frames' times, then
    // the frames one after another through the tracker.
    Editor.prototype.followStart = function () {
        var self = this, f = this.follow, l = this.followLayer(), at = this.motionNow(), x0, y0, x1, y1, corners, clip, span, size, src;

        x0 = Math.min(f.a[0], f.b[0]);
        y0 = Math.min(f.a[1], f.b[1]);
        x1 = Math.max(f.a[0], f.b[0]);
        y1 = Math.max(f.a[1], f.b[1]);
        corners = [[x0, y0], [x1, y0], [x1, y1], [x0, y1]].map(function (p) { return self.toDoc(p[0], p[1]); });
        clip = l ? M.followClip(this.doc, this.toDoc((x0 + x1) / 2, (y0 + y1) / 2), at) : null;

        if (!clip) {
            this.followQuiet();
            this.showMessage(t('followNoVideo'), true);
            this.render();
            return;
        }

        span = M.followSpan(this.doc, l, clip, at);

        if (!span.ok) {
            this.followQuiet();
            this.showMessage(t('followShort'), true);
            this.render();
            return;
        }

        src = corners.map(function (p) { return M.followToSource(clip, p); });
        // The box's shorter side in the source's pixels sets the scale.
        size = M.followSize(clip.width, clip.height, Math.min(
            Math.max.apply(null, src.map(function (p) { return p[0]; })) - Math.min.apply(null, src.map(function (p) { return p[0]; })),
            Math.max.apply(null, src.map(function (p) { return p[1]; })) - Math.min.apply(null, src.map(function (p) { return p[1]; }))
        ));

        Object.assign(f, {
            phase: 'running',
            id: l.id,
            clip: M.clone(clip),
            start: M.clone(l),
            before: M.clone(this.doc),
            end: this.motionEnd(),
            from: at,
            to: span.to,
            size: size,
            box: [
                Math.min.apply(null, src.map(function (p) { return p[0]; })) * size.k, Math.min.apply(null, src.map(function (p) { return p[1]; })) * size.k,
                Math.max.apply(null, src.map(function (p) { return p[0]; })) * size.k, Math.max.apply(null, src.map(function (p) { return p[1]; })) * size.k
            ],
            times: [],
            next: 0,
            points: [],
            want: null,
            waiting: null,
            canvas: document.createElement('canvas'),
            stats: { started: now(), decode: 0, grey: 0, match: 0, frames: 0 },
            px: Math.max(1, 1 / this.view.k)
        });
        f.canvas.width = size.w;
        f.canvas.height = size.h;
        f.boxEl.classList.add('on');
        this.root.classList.add('pgie-following');

        // A decoder of its own on the same bytes: the stage's, the bars' and
        // the export's requests never overtake these.
        f.source = this.clipSource(clip.src).then(function (s) { return X.video.open(s.bytes); });
        f.source.then(null, function () {});

        Promise.all([f.source, tracker(function (m) { self.followAnswer(f, m); })]).then(function (both) {
            if (f.done) {
                both[1].stop();
                return;
            }

            f.tracker = both[1];
            f.times = M.followTimes(f.clip, f.from, f.to, frameLength(both[0].track));
            self.followFrame(f, true);
        }, function (e) {
            self.followFail(f, (e && e.message && (e.message.indexOf('video-') === 0)) ? X.video.message(e) : t('followError'));
        });

        this.render();
    };

    // The next frame: decoded at its source time, drawn grey at the run's
    // size and posted to the tracker; the first starts it with the box. A
    // run read in regions draws only the region round the box (the first)
    // or the one the tracker asked for.
    Editor.prototype.followFrame = function (f, first) {
        var self = this, item = f.times[f.next], t0 = now();

        if (f.done) {
            return;
        }

        if (!item) {
            this.followFinish(f, null);
            return;
        }

        f.source.then(function (s) {
            return s.frameAt(item.src);
        }).then(function (frame) {
            var g, t1, grey, msg, cut = f.size.region ? ((first || !f.want) ? M.followRegion(f.box, f.size) : f.want) : null, ratio;

            if (f.done) {
                if (frame) {
                    frame.close();
                }

                return;
            }

            if (!frame) {
                throw new Error('frame');
            }

            t1 = now();

            if (cut) {
                if ((f.canvas.width !== cut[2] - cut[0]) || (f.canvas.height !== cut[3] - cut[1])) {
                    f.canvas.width = cut[2] - cut[0];
                    f.canvas.height = cut[3] - cut[1];
                }

                // The frame's own pixels over the run's whole frame.
                ratio = (frame.displayWidth || frame.width || (f.size.w / f.size.k)) / f.size.w;
            }

            g = f.canvas.getContext('2d', { willReadFrequently: true });

            if (cut) {
                g.drawImage(frame, cut[0] * ratio, cut[1] * ratio, f.canvas.width * ratio, f.canvas.height * ratio, 0, 0, f.canvas.width, f.canvas.height);
            } else {
                g.drawImage(frame, 0, 0, f.canvas.width, f.canvas.height);
            }

            frame.close();
            grey = greyOf(g.getImageData(0, 0, f.canvas.width, f.canvas.height).data, f.canvas.width, f.canvas.height);
            f.stats.decode += t1 - t0;
            f.stats.grey += now() - t1;
            f.stats.frames++;
            f.sent = now();
            f.waiting = item;
            msg = first ? { type: 'start', frame: grey.buffer, width: f.canvas.width, height: f.canvas.height, box: f.box, t: item.t } : { type: 'frame', frame: grey.buffer, t: item.t };

            if (cut) {
                msg.width = f.canvas.width;
                msg.height = f.canvas.height;
                msg.at = [cut[0], cut[1]];

                if (first) {
                    msg.full = [f.size.w, f.size.h];
                }
            }

            f.tracker.post(msg, [grey.buffer]);
        }).then(null, function () {
            self.followFail(f, t('followError'));
        });
    };

    // The grey of RGBA pixels (Rec. 601 weights), as the tracker's grey().
    function greyOf(rgba, w, h) {
        var g = new Uint8Array(w * h), i;

        for (i = 0; i < w * h; i++) {
            g[i] = (rgba[i * 4] * 77 + rgba[i * 4 + 1] * 150 + rgba[i * 4 + 2] * 29 + 128) >> 8;
        }

        return g;
    }

    // The tracker's answer to the frame posted last.
    Editor.prototype.followAnswer = function (f, m) {
        if (f.done || !f.waiting) {
            return;
        }

        f.stats.match += now() - f.sent;
        f.waiting = null;

        if (m.type === 'error') {
            this.followFail(f, t((m.code === 'flat') ? 'followFlat' : ((m.code === 'box') ? 'followTiny' : 'followError')));
            return;
        }

        if (m.type === 'lost') {
            this.followFinish(f, m.t, m.why);
            return;
        }

        if (m.want) {
            f.want = m.want;
        }

        if ((m.type === 'ready') || (m.type === 'point')) {
            f.points.push({ t: m.t, x: m.x, y: m.y });
            this.followShow(f);
        }

        f.next++;
        this.followFrame(f, false);
    };

    // The samples so far as the layer's keys, unthinned; the pill's share.
    Editor.prototype.followShow = function (f) {
        var l = this.layer(f.id), samples = M.followSamples(f.start, f.clip, f.from, f.points, f.size.k), old = M.keyList(f.start), last;

        if (!l || !samples.length) {
            return;
        }

        last = samples[samples.length - 1].t;
        l.anim = Object.assign({}, l.anim || {}, { keys: old.filter(function (k) { return (k.t < f.from - 5) || (k.t > last + 5); }).concat(samples.map(function (s) {
            var was = M.keyAt({ anim: { keys: old } }, s.t) || { scale: 1, rot: 0, opacity: 1 };

            return { t: s.t, x: s.x, y: s.y, scale: was.scale, rot: was.rot, opacity: was.opacity, ease: 'linear' };
        })).sort(function (a, b) { return a.t - b.t; }) });
        this.layersDirty = true;
        this.time = this.motionStart() + last;
        this.applyMotion();
        this.updateBar();
        this.drawKeyPath();
        this.followPillUpdate();
    };

    // The end of a run: the thinned keys, one undo step; the playhead back
    // at the start, the layer selected, the Path tab open. lostAt is the
    // time the patch was lost at, or null when the span was read through;
    // why the tracker's reason.
    Editor.prototype.followFinish = function (f, lostAt, why) {
        var res, l, samples = M.followSamples(f.start, f.clip, f.from, f.points, f.size.k);

        f.stats.total = now() - f.stats.started;
        this.followStats = f.stats;
        this.follow = null;
        this.followEnd(f);
        // The live keys drawn while it ran go: the document as before, with
        // the thinned keys when there is a move to keep.
        this.doc = (samples.length > 1) ? M.clone(f.before) : f.before;
        this.layersDirty = true;
        l = this.layer(f.id);

        if (l && (samples.length > 1)) {
            res = M.followApply(f.start, samples, f.end, f.px);
            l.anim = Object.assign({}, f.start.anim || {}, { keys: res.keys });
            this.history.push(f.before, null);
            this.settleDirty();
        }

        this.time = this.motionStart() + f.from;
        this.sel = [f.id];
        this.motionTab = 'keys';
        this.render();

        if (lostAt !== null) {
            // Why and what helps once in the editor's life, not on each try.
            this.followTold = this.followTold || {};
            this.showMessage(String(t(M.followLostText(why, f.size.small, this.followTold))).replace('{var:1}', clockText(this.motionStart() + lostAt)), true);
        }
    };

    Editor.prototype.followFail = function (f, message) {
        if (f.done) {
            return;
        }

        if (this.follow === f) {
            this.followStop();
        } else {
            this.followEnd(f);
        }

        this.showMessage(message, true);
    };

    // ---- the stage's pointers ----

    var pointers = Editor.pointers.motion;

    Editor.pointers.motion = {
        down: function (e) {
            if (!this.followDown(e)) {
                pointers.down.call(this, e);
            }
        },
        move: function (e) {
            var f = this.follow;

            if (f) {
                if (f.phase === 'drawing') {
                    f.b = [e.clientX, e.clientY];
                    this.followBoxShow();
                }

                return;
            }

            pointers.move.call(this, e);
        },
        up: function (e) {
            if (this.follow) {
                this.followUp();
                return;
            }

            pointers.up.call(this, e);
        },
        double: function (e) {
            if (!this.follow) {
                pointers.double.call(this, e);
            }
        }
    };

    // ---- the Path tab, the pill and the hint ----

    Editor.prototype.followButton = function () {
        var self = this, on = !!this.follow;
        var b = button('pgie-btn pgie-followbtn' + (on ? ' on' : ''), on ? t('followCancel') : t('followFollow'), 'follow', function () { self.followToggle(); }, true);

        b.setAttribute('aria-pressed', on ? 'true' : 'false');
        b.disabled = !on && !this.followLayer();

        if (b.disabled) {
            b.title = t('followPick');
        }

        return b;
    };

    // Follow beside Recording a move, and its note under Record's.
    Editor.motionPanes.forEach(function (pane) {
        if (pane.id !== 'keys') {
            return;
        }

        var own = pane.panel;

        pane.panel = function (body, layers) {
            own.call(this, body, layers);

            var act = body.querySelector('.pgie-recact'), notes = body.querySelectorAll('.pgie-note'), note;

            if (!act) {
                return;
            }

            act.appendChild(this.followButton());
            note = el('p', { class: 'pgie-note pgie-follownote', text: t('followNote') });
            note.setAttribute('data-follow', '1');
            act.parentNode.insertBefore(note, (notes.length ? notes[0] : act).nextSibling);
        };
    });

    Editor.prototype.followView = function () {
        var f = this.follow, on = !!f && (this.tool === 'motion');

        // Another tool: the run ends unsaved.
        if (!on && f) {
            this.followQuiet();
        }

        this.root.classList.toggle('pgie-followarmed', on);

        if (on) {
            this.hint.textContent = t(M.followHint(f.phase === 'running', touchScreen()));
        }

        if (!on) {
            if (this.followPill) {
                this.followPill.remove();
                this.followPill = null;
            }

            return;
        }

        // One pill over the stage: the hint while the box is set up, then
        // the share done while it runs; Cancel in both.
        if (!this.followPill) {
            var self = this;

            this.followPill = el('div', { class: 'pgie-followpill', role: 'status', 'aria-live': 'polite' }, [
                el('span', { class: 'pgie-followspin', 'aria-hidden': 'true' }),
                el('span', { class: 'pgie-followtext' }),
                el('strong', { class: 'pgie-followshare' }),
                el('button', { type: 'button', class: 'pgie-btn pgie-followcancel', text: t('followCancelRun'), onclick: function () { self.followStop(); } })
            ]);
            ['pointerdown', 'pointerup', 'pointercancel', 'dblclick'].forEach(function (type) {
                self.followPill.addEventListener(type, function (e) { e.stopPropagation(); });
            });
            this.stage.appendChild(this.followPill);
        }

        this.followPillUpdate();
    };

    Editor.prototype.followPillUpdate = function () {
        var f = this.follow, p = this.followPill, running = !!(f && (f.phase === 'running')), share;

        if (!p || !f) {
            return;
        }

        p.classList.toggle('pgie-followsetup', !running);
        p.querySelector('.pgie-followtext').textContent = t(running ? 'followBusy' : M.followHint(false, touchScreen()));
        share = (running && f.times && f.times.length) ? Math.min(100, Math.round(100 * f.next / f.times.length)) : 0;
        p.querySelector('.pgie-followshare').textContent = running ? pct(share) : '';
    };

    Editor.prototype.followEscape = function () {
        if (this.follow) {
            this.followStop();
            return true;
        }

        return false;
    };

    Editor.inits.push(function () {
        this.follow = null;
        this.followPill = null;
        this.followStats = null;
        this.followSheet = null;
    });

    Editor.views.push(Editor.prototype.followView);
    Editor.escapes.unshift(Editor.prototype.followEscape);
}(window.PgImageEditor.parts));
