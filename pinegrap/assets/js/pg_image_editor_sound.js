/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: music and sounds on an animation (doc.sounds, the model's
 * SOUND section). Sounds come from Files, lie on the whole animation's
 * timeline and play through Web Audio in step with the play bar. Under the
 * tracks a row per sound shows its waveform and its fades; dragging the bar
 * moves it, dragging an end trims it. With a sound picked, the Animate
 * panel shows its start, cut, volume, fades, Split, Duplicate and Delete.
 *
 * Sounds draw nothing: a still save and the animation's frames leave them
 * out; the animation's video takes them in a later step.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_tracks.js
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

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, icon = X.icon, button = X.button, section = X.section, tile = X.tile, SVGNS = X.SVGNS;

    // As the tracks: a phone (upright or sideways) gets the panel only.
    var NO_ROWS = '(max-width: 599.98px), (max-height: 499.98px)';
    var PHONE = '(max-width: 599.98px)';
    // The waveform's bars on a row, and the lag before a sound starts.
    var WAVE_BARS = 160;
    var LEAD_S = 0.05;
    // Sound and playhead apart by more than this: play again from the playhead.
    var DRIFT_MS = 150;

    X.icons.music = 'M9 18V5.5l11-2V16M9 18a2.75 2.75 0 1 1-5.5 0A2.75 2.75 0 0 1 9 18zM20 16a2.75 2.75 0 1 1-5.5 0 2.75 2.75 0 0 1 5.5 0zM9 9.5l11-2';
    X.icons.volume = 'M4 9.5h3.5L12 5.5v13l-4.5-4H4zM15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11';
    X.icons.mute = 'M4 9.5h3.5L12 5.5v13l-4.5-4H4zM16 9.5l5 5M21 9.5l-5 5';

    function origin() {
        return (window.location && window.location.origin) || '';
    }

    function seconds(ms) {
        return X.decimal(Math.round(ms / 100) / 10) + ' ' + t('motionSecondsUnit');
    }

    // m:ss.d, as the play bar's clock.
    function clock(ms) {
        var tenths = Math.max(0, Math.round(ms / 100)), m = Math.floor(tenths / 600), s = (tenths % 600) / 10;
        var cfg = window.pgImageEditorConfig, sep = (cfg && cfg.decimal) || '.';

        return m + ':' + ((s < 10) ? '0' : '') + s.toFixed(1).replace('.', sep);
    }

    function pct(ms, total) {
        return (100 * Math.max(0, Math.min(total, ms)) / total) + '%';
    }

    function svg(tag, attrs) {
        var n = document.createElementNS(SVGNS, tag);

        Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });

        return n;
    }

    Editor.prototype.sounds = function () {
        return M.soundList(this.doc, origin());
    };

    Editor.prototype.soundPicked = function () {
        var id = this.soundSel;

        return id ? (this.sounds().filter(function (s) { return s.id === id; })[0] || null) : null;
    };

    // ---- files and their sound ----

    Editor.prototype.soundContext = function () {
        var Ctx = window.AudioContext || window.webkitAudioContext;

        if (!this.soundCtx && Ctx) {
            try {
                this.soundCtx = new Ctx();
            } catch (err) {
                this.soundCtx = null;
            }
        }

        return this.soundCtx;
    };

    // The file at src decoded once: { state, buffer, samples }; a promise
    // while it loads. A decoded file draws the rows again.
    Editor.prototype.soundLoad = function (src) {
        var self = this, entry = this.soundBufs[src], ctx = this.soundContext();

        if (entry) {
            return entry.promise;
        }

        entry = this.soundBufs[src] = { state: 'loading', buffer: null, samples: null, promise: null };

        if (!ctx || !window.fetch) {
            entry.state = 'error';
            entry.promise = Promise.resolve(entry);
            return entry.promise;
        }

        entry.promise = fetch(src, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                throw new Error('status ' + r.status);
            }

            return r.arrayBuffer();
        }).then(function (data) {
            return new Promise(function (resolve, reject) {
                var p = ctx.decodeAudioData(data, resolve, reject);

                if (p && p.then) {
                    p.then(resolve, reject);
                }
            });
        }).then(function (buffer) {
            entry.state = 'ready';
            entry.buffer = buffer;
            entry.samples = buffer.getChannelData(0);

            return entry;
        }, function () {
            entry.state = 'error';

            return entry;
        }).then(function (done) {
            // Playing: start again from the playhead with this sound in.
            self.soundStop();
            self.soundStamp++;

            if (self.dialog && self.dialog.open) {
                self.render();
            }

            return done;
        });

        return entry.promise;
    };

    // ---- playing ----

    Editor.prototype.soundStop = function () {
        this.soundNodes.forEach(function (n) {
            try {
                n.stop();
            } catch (err) {
                // Already ended.
            }
        });
        this.soundNodes = [];
        this.soundRun = null;
    };

    // Called with the playhead: plays from it while the play bar plays (not
    // a phase's preview), again when the playhead jumped (a loop, a scrub).
    Editor.prototype.soundSync = function () {
        var ctx = this.soundCtx, run = this.soundRun;

        if (!this.playing || (this.previewEnd !== null) || (this.time === null) || !ctx) {
            if (run) {
                this.soundStop();
            }
            return;
        }

        if (run && (Math.abs(run.time + (ctx.currentTime - run.at) * 1000 - this.time) < DRIFT_MS)) {
            return;
        }

        this.soundStop();
        this.soundStart(ctx);
    };

    Editor.prototype.soundStart = function (ctx) {
        var self = this, at = ctx.currentTime + LEAD_S, time = this.time;

        if (ctx.state === 'suspended') {
            ctx.resume();
        }

        this.sounds().forEach(function (s) {
            var entry = self.soundBufs[s.src], plan = M.soundPlan(s, time);

            if (!entry) {
                self.soundLoad(s.src);
                return;
            }

            if ((entry.state !== 'ready') || !plan) {
                return;
            }

            var node = ctx.createBufferSource(), gain = ctx.createGain(), sp = M.soundSpan(s), begin = time + plan.wait;

            node.buffer = entry.buffer;
            // The volume and the fades as ramps between their corners.
            gain.gain.setValueAtTime(M.soundGain(s, begin), at + plan.wait / 1000);
            [sp.from + s.fadeIn, sp.to - s.fadeOut, sp.to].forEach(function (k) {
                if (k > begin) {
                    gain.gain.linearRampToValueAtTime(M.soundGain(s, k), at + (k - time) / 1000);
                }
            });
            node.connect(gain);
            gain.connect(ctx.destination);
            node.start(at + plan.wait / 1000, plan.offset / 1000, plan.dur / 1000);
            self.soundNodes.push(node);
        });

        this.soundRun = { time: time, at: at };
    };

    // ---- the rows under the tracks ----

    Editor.prototype.soundsKey = function () {
        if (this.soundDragging) {
            return this.soundKeyNow;
        }

        this.soundKeyNow = this.soundsShown() ? JSON.stringify([this.soundSel, this.sounds(), this.soundStamp, this.motionTotal()]) : '';

        return this.soundKeyNow;
    };

    Editor.prototype.soundsShown = function () {
        return !(window.matchMedia && window.matchMedia(NO_ROWS).matches);
    };

    Editor.prototype.soundsNode = function () {
        var self = this, total = this.motionTotal(), rows = el('div', { class: 'pgie-trrows' }), parts = [];

        if (!this.soundsShown()) {
            this.soundEls = null;
            return null;
        }

        // The tracks' ruler counts one scene, or there are no tracks: the
        // sounds get the whole length's own.
        if ((total !== this.motionEnd()) || !this.doc.layers.length) {
            var ruler = el('div', { class: 'pgie-trruler', 'aria-hidden': 'true' }), step = Math.max(1, Math.ceil(total / 10000)), s;

            for (s = 0; s * 1000 <= total; s += step) {
                ruler.appendChild(el('span', { class: 'pgie-trtick pgie-trlabel', style: 'left: ' + pct(s * 1000, total), text: X.decimal(s) + ' ' + t('motionSecondsUnit') }));
            }

            parts.push(el('div', { class: 'pgie-trhead' }, [el('span', { class: 'pgie-trname', text: t('soundRows') }), el('div', { class: 'pgie-trlane' }, [ruler])]));
        }

        this.sounds().forEach(function (snd) {
            rows.appendChild(self.soundRow(snd, total));
        });

        rows.appendChild(el('div', { class: 'pgie-trrow pgie-sndadd' }, [
            el('button', { type: 'button', class: 'pgie-trname', onclick: function () { self.openSoundFiles(); } }, [icon('plus'), el('span', { text: t('soundAdd') })]),
            el('div', { class: 'pgie-trlane' })
        ]));
        parts.push(rows);

        var playhead = el('div', { class: 'pgie-trplay', 'aria-hidden': 'true' });

        parts.push(playhead);
        this.soundEls = { playhead: playhead };
        this.soundsUpdate();

        return el('div', { class: 'pgie-tracks pgie-sounds', role: 'group', 'aria-label': t('soundRegion') }, parts);
    };

    function barTip(snd) {
        var sp = M.soundSpan(snd);

        return snd.name + ' · ' + seconds(sp.from) + ' → ' + seconds(sp.to);
    }

    // The waveform of the cut part, and the volume's line with its fades.
    Editor.prototype.soundWave = function (snd) {
        var entry = this.soundBufs[snd.src], w = WAVE_BARS * 2, d = '', i, h, sr, peaks;
        var box = svg('svg', { class: 'pgie-sndwave', viewBox: '0 0 ' + w + ' 100', preserveAspectRatio: 'none', 'aria-hidden': 'true' });

        if (entry && (entry.state === 'ready')) {
            sr = entry.buffer.sampleRate;
            peaks = M.soundPeaks(entry.samples, WAVE_BARS, snd.from * sr / 1000, snd.to * sr / 1000);

            for (i = 0; i < peaks.length; i++) {
                h = Math.max(2, peaks[i] * 46);
                d += 'M' + (i * 2 + 1) + ' ' + (50 - h).toFixed(1) + 'V' + (50 + h).toFixed(1);
            }

            box.appendChild(svg('path', { d: d, class: 'pgie-sndpeaks' }));
        }

        var len = Math.max(1, snd.to - snd.from), y = 100 - 92 * (snd.muted ? 0 : snd.volume);
        var a = w * Math.min(1, snd.fadeIn / len), b = w * (1 - Math.min(1, snd.fadeOut / len));

        box.appendChild(svg('path', { d: 'M0 ' + (snd.fadeIn ? 100 : y) + 'L' + a + ' ' + y + 'L' + b + ' ' + y + 'L' + w + ' ' + (snd.fadeOut ? 100 : y), class: 'pgie-sndenv', 'vector-effect': 'non-scaling-stroke' }));

        return box;
    };

    Editor.prototype.soundRow = function (snd, total) {
        var self = this, on = (this.soundSel === snd.id), sp = M.soundSpan(snd), entry = this.soundBufs[snd.src];
        var lane = el('div', { class: 'pgie-trlane' });
        var bar = el('span', { class: 'pgie-trbar pgie-sndbar' + (snd.muted ? ' pgie-sndmuted' : ''), style: 'left: ' + pct(sp.from, total) + '; width: ' + pct(sp.to - sp.from, total), title: barTip(snd), role: 'img', 'aria-label': barTip(snd) }, [
            this.soundWave(snd),
            el('span', { class: 'pgie-sndname', text: snd.name + (((snd.volume < 1) && !snd.muted) ? ' · ' + Math.round(snd.volume * 100) + '%' : '') })
        ]);

        if (!entry && (this.tool === 'motion')) {
            this.soundLoad(snd.src);
        }

        bar.addEventListener('pointerdown', function (e) { self.soundDown(e, snd, 'move', bar, total); });

        if (on) {
            ['start', 'end'].forEach(function (grip) {
                var g = el('span', { class: 'pgie-trgrip pgie-trgrip-' + grip, 'aria-hidden': 'true' });

                g.addEventListener('pointerdown', function (e) {
                    e.stopPropagation();
                    self.soundDown(e, snd, grip, bar, total);
                });
                bar.appendChild(g);
            });
        }

        lane.appendChild(bar);
        lane.addEventListener('pointerdown', function (e) {
            if (e.target === lane) {
                self.soundPick(snd.id);
            }
        });

        return el('div', { class: 'pgie-trrow pgie-sndrow' + (on ? ' on' : ''), role: 'group', 'aria-label': snd.name }, [
            el('button', { type: 'button', class: 'pgie-trname', title: snd.name, 'aria-pressed': on ? 'true' : 'false', onclick: function () { self.soundPick(snd.id); } }, [
                icon(snd.muted ? 'mute' : 'music'), el('span', { text: snd.name })
            ]),
            lane
        ]);
    };

    Editor.prototype.soundsUpdate = function () {
        var e = this.soundEls, total = this.motionTotal(), now = this.time;

        this.soundSync();

        if (!e) {
            return;
        }

        e.playhead.style.setProperty('--pgie-trat', String(Math.max(0, Math.min(1, (now || 0) / total))));
        e.playhead.classList.toggle('pgie-tridle', now === null);
    };

    // A press on a sound's bar or grip: picked, the drag follows the pointer
    // on the bar and is one change on release (M.soundEdit()).
    Editor.prototype.soundDown = function (e, snd, grip, bar, total) {
        var self = this, width = bar.parentNode.getBoundingClientRect().width, x0 = e.clientX, patch = null, moved = false;

        if ((e.button !== 0) || !(width > 0)) {
            return;
        }

        e.preventDefault();

        if (this.playing) {
            this.pause();
            return;
        }

        if (this.soundSel !== snd.id) {
            this.soundSel = snd.id;
            this.sel = [];
            this.renderPanel();
        }

        this.soundDragging = true;

        try {
            bar.setPointerCapture(e.pointerId);
        } catch (err) {
            // A pointer the browser no longer tracks: the drag still follows moves.
        }

        var move = function (ev) {
            var sp;

            moved = moved || (Math.abs(ev.clientX - x0) > 2);
            patch = M.soundEdit(snd, grip, (ev.clientX - x0) / width * total, ev.shiftKey);
            sp = M.soundSpan(Object.assign({}, snd, patch));
            bar.style.left = pct(sp.from, total);
            bar.style.width = pct(sp.to - sp.from, total);
        };

        var up = function () {
            bar.removeEventListener('pointermove', move);
            bar.removeEventListener('pointerup', up);
            bar.removeEventListener('pointercancel', up);
            self.soundDragging = false;

            if (moved && patch) {
                self.soundSet(snd.id, patch);
            } else {
                self.render();
            }
        };

        bar.addEventListener('pointermove', move);
        bar.addEventListener('pointerup', up);
        bar.addEventListener('pointercancel', up);
    };

    // ---- changes ----

    // The document's sounds as the model reads them, changed by fn(list).
    Editor.prototype.soundChange = function (fn, key, viewOnly) {
        var o = origin();

        this.change(function (doc) {
            var list = M.soundList(doc, o);

            fn(list);

            if (list.length) {
                doc.sounds = list;
            } else {
                delete doc.sounds;
            }
        }, key, viewOnly);
    };

    Editor.prototype.soundSet = function (id, patch, key, viewOnly) {
        this.soundChange(function (list) {
            list.forEach(function (s) {
                if (s.id === id) {
                    Object.assign(s, patch);
                }
            });
        }, key, viewOnly);
    };

    Editor.prototype.soundPick = function (id) {
        this.select([]);
        this.soundSel = id;
        this.sceneFocus = false;
        this.render();
    };

    Editor.prototype.soundDelete = function (id) {
        this.soundSel = null;
        this.soundChange(function (list) {
            var i = list.map(function (s) { return s.id; }).indexOf(id);

            if (i >= 0) {
                list.splice(i, 1);
            }
        });
    };

    Editor.prototype.soundDuplicate = function (snd) {
        var copy = Object.assign({}, snd, { id: M.soundId(this.doc), start: M.soundSpan(snd).to });

        this.soundSel = copy.id;
        this.soundChange(function (list) { list.push(copy); });
    };

    Editor.prototype.soundSplit = function (snd) {
        var parts = M.soundSplit(snd, (this.time === null) ? -1 : this.time, M.soundId(this.doc));

        if (!parts) {
            return;
        }

        this.soundChange(function (list) {
            var i = list.map(function (s) { return s.id; }).indexOf(snd.id);

            if (i >= 0) {
                list.splice(i, 1, parts[0], parts[1]);
            }
        });
    };

    // ---- adding a sound from Files ----

    Editor.prototype.openSoundFiles = function () {
        var self = this, timer = null, st = this.soundFiles;
        var find = el('input', { type: 'search', class: 'pgie-input pgie-stkfind', value: st.search, placeholder: t('soundFind'), 'aria-label': t('soundFind') });

        st.list = el('div', { class: 'pgie-sndfiles' });
        find.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () { self.loadSoundFiles(find.value); }, 350);
        });

        this.confirmBox = el('div', { class: 'pgie-confirm', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'pgie-sound-title' }, [
            el('div', { class: 'pgie-confirm-box pgie-importbox' }, [
                el('div', { class: 'pgie-importhead' }, [el('h2', { id: 'pgie-sound-title', text: t('soundFiles') }), button('pgie-ibtn', t('close'), 'close', function () { self.closeConfirm(); })]),
                find,
                st.list
            ])
        ]);
        this.confirmReturn = document.activeElement;
        this.root.appendChild(this.confirmBox);
        this.loadSoundFiles(st.search);
        find.focus();
    };

    Editor.prototype.loadSoundFiles = function (search) {
        var self = this, st = this.soundFiles, ask = ++st.serial;

        st.search = search || '';
        this.request({ action: 'sound_files', search: st.search }).then(function (r) {
            if (ask === st.serial) {
                self.showSoundFiles((r && (r.status === 'success')) ? (r.files || []) : null);
            }
        }, function () {
            if (ask === st.serial) {
                self.showSoundFiles(null);
            }
        });
    };

    Editor.prototype.showSoundFiles = function (files) {
        var self = this, list = this.soundFiles.list;

        if (!list || !this.confirmBox || !this.confirmBox.contains(list)) {
            return;
        }

        list.textContent = '';

        if (!files || !files.length) {
            list.appendChild(el('p', { class: 'pgie-note', text: t(files ? 'soundFilesNone' : 'soundFilesError') }));
            return;
        }

        files.forEach(function (f) {
            list.appendChild(el('button', { type: 'button', class: 'pgie-sndfile', onclick: function () {
                self.closeConfirm();
                self.addSound(f);
            } }, [icon('music'), el('span', { text: f.name }), el('em', { text: f.size || '' })]));
        });
    };

    // The file decoded first (its length cuts it), then one change at the
    // playhead, picked.
    Editor.prototype.addSound = function (f) {
        var self = this;

        if (!M.soundSrcOk(f.url, origin()) || (this.sounds().length >= M.SOUND_MAX)) {
            return;
        }

        this.soundNotice = t('soundLoading');
        this.render();
        this.soundLoad(f.url).then(function (entry) {
            self.soundNotice = '';

            if (entry.state !== 'ready') {
                self.soundNotice = t('soundError');
                self.render();
                return;
            }

            var snd = M.soundNew(self.doc, f, Math.round(entry.buffer.duration * 1000), (self.time === null) ? 0 : self.time, self.motionTotal());

            self.select([]);
            self.soundSel = snd.id;
            self.soundChange(function (list) { list.push(snd); });
        });
    };

    // ---- the panel ----

    Editor.prototype.soundSlider = function (snd, field, label, min, max, value, show) {
        var self = this, shown = el('span', { class: 'pgie-val', text: show(value) });
        var slider = el('input', { type: 'range', class: 'pgie-range', min: String(min), max: String(Math.max(min, max)), step: (field === 'volume') ? '1' : '100', value: String(value), 'aria-label': label });

        slider.addEventListener('input', function () {
            var n = Number(slider.value), patch = {};

            shown.textContent = show(n);
            patch[field] = (field === 'volume') ? n / 100 : n;

            // The cut keeps its fades inside it.
            if ((field === 'from') || (field === 'to')) {
                var from = (field === 'from') ? Math.min(n, snd.to - M.SOUND_MIN) : snd.from, to = (field === 'to') ? Math.max(n, snd.from + M.SOUND_MIN) : snd.to;

                patch = { from: from, to: to, fadeIn: Math.min(snd.fadeIn, to - from), fadeOut: Math.min(snd.fadeOut, to - from) };
            }

            self.soundSet(snd.id, patch, 'sound-' + snd.id + '-' + field, true);
        });
        slider.addEventListener('change', function () {
            self.history.key = null;
            self.render();
        });

        return section(label, slider, shown);
    };

    Editor.prototype.soundPanel = function (body, snd) {
        var self = this, entry = this.soundBufs[snd.src], len = snd.to - snd.from, total = this.motionTotal();
        var type = (M.soundType(snd.name) || '').toUpperCase();
        var pct100 = function (n) { return Math.round(n) + '%'; };

        // On a phone held upright the play bar comes first, as in the
        // layers' motion panel.
        if (window.matchMedia && window.matchMedia(PHONE).matches) {
            body.appendChild(this.motionBar());
        }

        body.appendChild(el('div', { class: 'pgie-sndfile pgie-sndhead' }, [icon('music'), el('span', { text: snd.name }), el('em', { text: [type, clock(snd.length)].filter(Boolean).join(' · ') })]));

        if (entry && (entry.state === 'error')) {
            body.appendChild(el('p', { class: 'pgie-note pgie-warn', text: t('soundError') }));
        }

        body.appendChild(this.soundSlider(snd, 'start', t('soundStart'), 0, Math.max(total, snd.start), snd.start, seconds));
        body.appendChild(this.soundSlider(snd, 'from', t('soundFrom'), 0, snd.length, snd.from, clock));
        body.appendChild(this.soundSlider(snd, 'to', t('soundTo'), 0, snd.length, snd.to, clock));

        var mute = button('pgie-ibtn pgie-sndmute' + (snd.muted ? ' on' : ''), t(snd.muted ? 'soundUnmute' : 'soundMute'), snd.muted ? 'mute' : 'volume', function () {
            self.soundSet(snd.id, { muted: !snd.muted });
        });
        var vol = this.soundSlider(snd, 'volume', t('soundVolume'), 0, 100, Math.round(snd.volume * 100), pct100);

        mute.setAttribute('aria-pressed', snd.muted ? 'true' : 'false');
        vol.classList.add('pgie-sndvol');
        vol.appendChild(el('div', { class: 'pgie-sndvolrow' }, [mute, vol.lastChild]));
        body.appendChild(vol);

        body.appendChild(el('div', { class: 'pgie-sndfades' }, [
            this.soundSlider(snd, 'fadeIn', t('soundFadeIn'), 0, Math.min(10000, len), snd.fadeIn, seconds),
            this.soundSlider(snd, 'fadeOut', t('soundFadeOut'), 0, Math.min(10000, len), snd.fadeOut, seconds)
        ]));

        var split = tile('scissors', t('soundSplit'), function () { self.soundSplit(snd); });
        var sp = M.soundSpan(snd), at = this.time;

        split.disabled = (at === null) || (at < sp.from + M.SOUND_MIN) || (at > sp.to - M.SOUND_MIN);
        split.title = t('soundSplitNote');
        body.appendChild(el('div', { class: 'pgie-tiles pgie-sndtiles' }, [
            split,
            tile('duplicate', t('duplicate'), function () { self.soundDuplicate(snd); }),
            tile('trash', t('delete'), function () { self.soundDelete(snd.id); })
        ]));
        body.appendChild(el('p', { class: 'pgie-note', text: t('soundNote') }));
    };

    // The whole animation's fields end with the sounds: each to pick, and
    // Add a sound (the only way on a phone, where the rows do not show).
    Editor.prototype.soundList = function (body) {
        var self = this, list = el('div', { class: 'pgie-sndlist' });

        this.sounds().forEach(function (snd) {
            list.appendChild(el('button', { type: 'button', class: 'pgie-sndfile', onclick: function () { self.soundPick(snd.id); } }, [
                icon(snd.muted ? 'mute' : 'music'), el('span', { text: snd.name }), el('em', { text: seconds(M.soundSpan(snd).from) })
            ]));
        });

        list.appendChild(el('button', { type: 'button', class: 'pgie-btn pgie-sndaddbtn', onclick: function () { self.openSoundFiles(); } }, [icon('plus'), el('span', { text: t('soundAdd') })]));

        if (this.soundNotice) {
            list.appendChild(el('p', { class: 'pgie-note', role: 'status', text: this.soundNotice }));
        }

        body.appendChild(el('h3', { class: 'pgie-mwhole', text: t('soundRows') }));
        body.appendChild(list);
    };

    var motionPanel = Editor.panels.motion;

    Editor.panels.motion = function (body) {
        var snd = this.soundPicked();

        if (snd && !this.selected().length) {
            this.soundPanel(body, snd);
            return;
        }

        this.soundSel = null;
        motionPanel.call(this, body);
    };

    var motionWhole = Editor.prototype.motionWhole;

    Editor.prototype.motionWhole = function (body) {
        motionWhole.call(this, body);
        this.soundList(body);
    };

    // Remove deletes the picked sound; else the layers' motion as before.
    var motionHeader = Editor.headers.motion;

    Editor.headers.motion = function () {
        var self = this, snd = this.soundPicked();

        if (snd && !this.selected().length) {
            return el('button', { type: 'button', class: 'pgie-link', text: t('motionRemove'), onclick: function () { self.soundDelete(snd.id); } });
        }

        return motionHeader.call(this);
    };

    // The panel's title names the sound.
    var renderPanel = Editor.prototype.renderPanel;

    Editor.prototype.renderPanel = function () {
        renderPanel.apply(this, arguments);

        var h = this.panel && this.panel.querySelector('.pgie-ph h2');

        if (h && (this.tool === 'motion') && this.soundPicked() && !this.selected().length) {
            h.textContent = t('soundTitle');
            this.panel.setAttribute('aria-label', t('soundTitle'));
        }
    };

    // A layer picked, or a press on the stage, leaves the sound.
    var select = Editor.prototype.select;

    Editor.prototype.select = function (ids) {
        if (ids && ids.length) {
            this.soundSel = null;
        }

        return select.apply(this, arguments);
    };

    var motionDown = Editor.pointers.motion.down;

    Editor.pointers.motion.down = function (e) {
        if (this.soundSel) {
            this.soundSel = null;
            this.render();
        }

        return motionDown.call(this, e);
    };

    // The sound stops with the play bar and with the editor.
    ['pause', 'stopMotion', 'seek', 'finish'].forEach(function (name) {
        var fn = Editor.prototype[name];

        Editor.prototype[name] = function () {
            this.soundStop();

            return fn.apply(this, arguments);
        };
    });

    // Delete or Backspace with a sound picked deletes it; Escape leaves it.
    Editor.keys.push(function (e, mod, k) {
        if (mod || (this.tool !== 'motion') || !this.soundPicked() || ((k !== 'Delete') && (k !== 'Backspace'))) {
            return false;
        }

        this.soundDelete(this.soundSel);

        return true;
    });

    Editor.escapes.unshift(function () {
        if ((this.tool !== 'motion') || !this.soundSel) {
            return false;
        }

        this.soundSel = null;
        this.render();

        return true;
    });

    Editor.inits.push(function () {
        this.soundSel = null;
        this.soundCtx = null;
        this.soundBufs = {};
        this.soundNodes = [];
        this.soundRun = null;
        this.soundEls = null;
        this.soundStamp = 0;
        this.soundDragging = false;
        this.soundKeyNow = '';
        this.soundNotice = '';
        this.soundFiles = { search: '', serial: 0, list: null };
    });

    Editor.motionDocks.push({ key: Editor.prototype.soundsKey, node: Editor.prototype.soundsNode, update: Editor.prototype.soundsUpdate, note: function () { return null; } });
}(window.PgImageEditor.parts));
