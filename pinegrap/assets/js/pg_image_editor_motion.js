/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the Animate tool: a layer's entrance, loop and exit, the
 * whole animation's length, repeat and picture move, and the play bar that
 * previews them on the stage.
 *
 * The playhead (this.time) is null except while playing or scrubbing in this
 * tool, so editing and a still save always see every layer at rest; the
 * editor never plays by itself. Each frame sets the transform, opacity and
 * clip of the layer's <g> (renderLayers()) from M.motionAt(); only a layer
 * whose drawing is cut (draw on, typewriter) is drawn again.
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

    var Editor = X.Editor, M = X.M, SVGNS = X.SVGNS, t = X.t, el = X.el, icon = X.icon, section = X.section, button = X.button;

    X.icons.motion = 'M4 6.5h6M3 12h7M4 17.5h6M13 6l7 6-7 6z';
    X.icons.m_none = 'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17zM6 18L18 6';
    X.icons.m_fade = 'M4 12h.01M8 12h.01M12 8v8M16 6v12M20 4v16';
    X.icons.m_slide = 'M3 12h11M10 8l4 4-4 4M17 5h3v14h-3z';
    X.icons.m_rise = 'M12 20V9M8 13l4-4 4 4M5 4.5h14';
    X.icons.m_pop = 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4';
    X.icons.m_zoom = 'M10.5 4a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13zM15.5 15.5L20 20M10.5 8v5M8 10.5h5';
    X.icons.m_wipe = 'M4 5h16v14H4zM12 5v14M4 9h8M4 13h8';
    X.icons.m_spin = 'M19.5 12a7.5 7.5 0 1 1-2.2-5.3M19.5 4.5v4h-4';
    X.icons.m_type = 'M5 6V4.5h10V6M10 4.5v15M8 19.5h4M18 9v10';
    X.icons.m_draw = 'M3.5 16c2.5-6 5-6 6.5-2s3.5 4 5.5-1 3-5 5-3';
    X.icons.m_pulse = 'M3 12h4l2-5 4 10 2-5h6';
    X.icons.m_float = 'M12 3.5v4M9.5 6l2.5-2.5L14.5 6M6 13a6 4 0 0 0 12 0M6 13h12M12 20.5v-3';
    X.icons.m_blink = 'M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6zM12 9.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z';
    X.icons.m_wiggle = 'M3 12c1.5-3 3-3 4.5 0s3 3 4.5 0 3-3 4.5 0 3 3 4.5 0';
    X.icons.m_swing = 'M12 3.5v8M12 11.5l-5 7M12 11.5l5 7M9 3.5h6';
    X.icons.m_left = 'M19 12H5M11 6l-6 6 6 6';
    X.icons.m_right = 'M5 12h14M13 6l6 6-6 6';
    X.icons.m_up = 'M12 19V5M6 11l6-6 6 6';
    X.icons.m_down = 'M12 5v14M6 13l6 6 6-6';
    X.icons.play = 'M8 5.5v13l10.5-6.5z';
    X.icons.pause = 'M8 5.5v13M16 5.5v13';
    X.icons.repeat = 'M4 11V9a3 3 0 0 1 3-3h12M16 3l3 3-3 3M20 13v2a3 3 0 0 1-3 3H5M8 21l-3-3 3-3';
    X.icons.clock = 'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17zM12 7.5V12l3 2';

    // Kinds that move along a direction, and the two that turn one way or
    // the other.
    var DIRECTED = ['slide', 'rise', 'wipe', 'float'];
    var TURNING = ['spin'];
    // Layers that do not move: a redaction moving away would show what it hides.
    var STILL = ['redact'];
    // Layers whose stroke can be drawn on.
    var UNDRAWABLE = ['text', 'image', 'sticker', 'redact'];
    var PHONE = '(max-width: 599.98px)';

    function seconds(ms) {
        return X.decimal(ms / 1000) + ' ' + t('motionSecondsUnit');
    }

    // The playhead as m:ss.d with the panel's decimal separator.
    function clockText(ms) {
        var tenths = Math.max(0, Math.round(ms / 100)), m = Math.floor(tenths / 600), s = (tenths % 600) / 10;
        var cfg = window.pgImageEditorConfig, sep = (cfg && cfg.decimal) || '.';

        return m + ':' + ((s < 10) ? '0' : '') + s.toFixed(1).replace('.', sep);
    }

    Editor.prototype.motionEnd = function () {
        return M.docMotion(this.doc).duration;
    };

    // The selected layers that may move.
    Editor.prototype.motionLayers = function () {
        return this.selected().filter(function (l) { return STILL.indexOf(l.type) < 0; });
    };

    // Change one phase of the selected layers: null removes it.
    Editor.prototype.setMotion = function (phase, patch, key) {
        var ids = this.motionLayers().map(function (l) { return l.id; });

        this.change(function (doc) {
            doc.layers.forEach(function (l) {
                if (ids.indexOf(l.id) < 0) {
                    return;
                }

                var a = l.anim || {};

                if (patch === null) {
                    delete a[phase];
                } else {
                    a[phase] = Object.assign({}, a[phase] || {}, patch);
                }

                if (a['in'] || a.loop || a.out) {
                    l.anim = a;
                } else {
                    delete l.anim;
                }
            });
        }, key || null, !!key);
    };

    Editor.prototype.setDocMotion = function (patch, key) {
        var self = this;

        this.change(function (doc) {
            doc.motion = Object.assign(M.docMotion(doc), patch);
        }, key || null, !!key);

        if (this.time !== null) {
            this.time = Math.min(this.time, self.motionEnd());
        }
    };

    // ---- the panel ----

    Editor.prototype.motionPanel = function (body) {
        var self = this, layers = this.motionLayers();

        if (window.matchMedia && window.matchMedia(PHONE).matches) {
            body.appendChild(this.motionBar());
        }

        if (!layers.length) {
            body.appendChild(el('p', { class: 'pgie-note', text: this.sel.length ? t('motionStill') : t('motionPick') }));
            this.motionWhole(body);
            return;
        }

        var l = layers[0], a = l.anim || {}, phase = this.motionTab, cur = a[phase] || null;
        var tabs = el('div', { class: 'pgie-seg', role: 'tablist', 'aria-label': t('tool_motion') });

        body.appendChild(el('div', { class: 'pgie-mlayer' }, [
            el('span', { class: 'pgie-mname', text: (layers.length > 1) ? t('motionLayers').replace('{n}', layers.length) : this.layerName(l) })
        ]));

        [['in', t('motionIn')], ['loop', t('motionLoop')], ['out', t('motionOut')]].forEach(function (tab) {
            var on = (phase === tab[0]), has = !!a[tab[0]];

            tabs.appendChild(el('button', { type: 'button', role: 'tab', class: (on ? 'on' : '') + (has ? ' pgie-mhas' : ''), 'aria-selected': on ? 'true' : 'false', text: tab[1], onclick: function () {
                self.motionTab = tab[0];
                self.render();
            } }));
        });

        body.appendChild(tabs);

        var kinds = (phase === 'loop') ? M.MOTION_LOOP : M.MOTION_IN;
        var grid = el('div', { class: 'pgie-tiles pgie-mkinds', role: 'group', 'aria-label': t('motion_' + phase + 'Kinds') });

        ['none'].concat(kinds).forEach(function (kind) {
            var on = cur ? (cur.kind === kind) : (kind === 'none');
            var off = ((kind === 'type') && (l.type !== 'text')) || ((kind === 'draw') && (UNDRAWABLE.indexOf(l.type) >= 0));
            var b = X.tile('m_' + kind, t('motion_' + kind), function () {
                if (kind === 'none') {
                    self.setMotion(phase, null);
                    return;
                }

                var fresh = M.newMotion(phase, kind);

                if (cur) {
                    fresh.ms = cur.ms;
                    fresh.delay = cur.delay;
                }

                if ((phase === 'out') && !cur) {
                    fresh.dir = 'down';
                }

                self.setMotion(phase, fresh);
                self.motionPreview(phase);
            });

            b.classList.toggle('on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            b.disabled = off;

            if (off) {
                b.title = (kind === 'type') ? t('motionTextOnly') : t('motionShapeOnly');
            }

            grid.appendChild(b);
        });

        body.appendChild(grid);

        if (cur) {
            this.motionFields(body, l, phase, cur);
        }

        this.motionWhole(body);
    };

    Editor.prototype.motionFields = function (body, l, phase, cur) {
        var self = this, end = this.motionEnd();

        if ((DIRECTED.indexOf(cur.kind) >= 0) || (TURNING.indexOf(cur.kind) >= 0)) {
            var dirs = el('div', { class: 'pgie-seg', role: 'radiogroup', 'aria-label': t('motionDirection') });
            var list = (TURNING.indexOf(cur.kind) >= 0) ? ['left', 'right'] : M.MOTION_DIRS;
            var now = (list.indexOf(cur.dir) >= 0) ? cur.dir : list[1];

            list.forEach(function (dir) {
                var on = (now === dir), b = el('button', { type: 'button', role: 'radio', class: on ? 'on' : '', 'aria-checked': on ? 'true' : 'false', title: t('motionDir_' + dir), 'aria-label': t('motionDir_' + dir), onclick: function () {
                    self.setMotion(phase, { dir: dir });
                    self.motionPreview(phase);
                } });

                b.appendChild(icon('m_' + dir));
                dirs.appendChild(b);
            });

            body.appendChild(section(t('motionDirection'), dirs));
        }

        body.appendChild(this.motionSlider(phase, 'delay', t('motionDelay'), 0, Math.max(0, end - 100), Number(cur.delay) || 0));

        body.appendChild(this.motionSlider(phase, 'ms', (phase === 'loop') ? t('motionPeriod') : t('motionDuration'), 100, Math.min(10000, end), Number(cur.ms) || 800));

        if (phase !== 'loop') {
            var eases = el('div', { class: 'pgie-chips', role: 'radiogroup', 'aria-label': t('motionEase') });

            M.MOTION_EASES.forEach(function (ease) {
                var on = ((cur.ease || 'linear') === ease);

                eases.appendChild(el('button', { type: 'button', role: 'radio', class: 'pgie-chip' + (on ? ' on' : ''), 'aria-checked': on ? 'true' : 'false', text: t('motionEase_' + ease), onclick: function () {
                    self.setMotion(phase, { ease: ease });
                    self.motionPreview(phase);
                } }));
            });

            body.appendChild(section(t('motionEase'), eases));
        }

        body.appendChild(el('p', { class: 'pgie-note pgie-msays', text: this.motionSays(l) }));
        body.appendChild(el('p', { class: 'pgie-note pgie-mtracknote', text: t('motionTracksNote') }));
    };

    // A time slider in steps of a tenth of a second.
    Editor.prototype.motionSlider = function (phase, field, label, min, max, value) {
        var self = this, shown = el('span', { class: 'pgie-val', text: seconds(value) });
        var slider = el('input', { type: 'range', class: 'pgie-range', min: String(min), max: String(Math.max(min, max)), step: '100', value: String(value), 'aria-label': label });

        slider.addEventListener('input', function () {
            var n = Number(slider.value), patch = {};

            shown.textContent = seconds(n);
            patch[field] = n;
            self.setMotion(phase, patch, 'motion-' + phase + '-' + field);
        });
        slider.addEventListener('change', function () {
            self.history.key = null;
            self.render();
        });

        return section(label, slider, shown);
    };

    // The timing in a sentence: when each phase runs.
    Editor.prototype.motionSays = function (l) {
        var sp = M.motionSpans(l, this.motionEnd()), parts = [];
        var span = function (key, from, to) {
            return t(key).replace('{from}', seconds(from)).replace('{to}', seconds(to));
        };

        if (sp.inStart !== null) {
            parts.push(span('motionSaysIn', sp.inStart, sp.inEnd));
        }

        if (sp.loopStart !== null) {
            parts.push(span('motionSaysLoop', sp.loopStart, sp.loopEnd));
        }

        if (sp.outStart !== null) {
            parts.push(span('motionSaysOut', sp.outStart, sp.outEnd));
        }

        return parts.join(' · ');
    };

    // The whole animation: its length, its repeat and the picture's move.
    Editor.prototype.motionWhole = function (body) {
        var self = this, m = M.docMotion(this.doc);
        var length = el('input', { type: 'range', class: 'pgie-range', min: String(M.MOTION_MIN), max: String(M.MOTION_MAX), step: '500', value: String(m.duration), 'aria-label': t('motionLength') });
        var shown = el('span', { class: 'pgie-val', text: seconds(m.duration) });
        var repeat = el('select', { class: 'pgie-select', 'aria-label': t('motionRepeatCount') });

        body.appendChild(el('h3', { class: 'pgie-mwhole', text: t('motionWhole') }));

        length.addEventListener('input', function () {
            shown.textContent = seconds(Number(length.value));
            self.setDocMotion({ duration: Number(length.value) }, 'motion-length');
        });
        length.addEventListener('change', function () {
            self.history.key = null;
            self.render();
        });
        body.appendChild(section(t('motionLength'), length, shown));

        [0, 1, 2, 3, 5, 10].forEach(function (n) {
            var o = el('option', { value: String(n), text: n ? t('motionTimes').replace('{n}', n) : t('motionForever') });

            o.selected = (m.loop === n);
            repeat.appendChild(o);
        });
        repeat.addEventListener('change', function () { self.setDocMotion({ loop: Number(repeat.value) }); });
        body.appendChild(section(t('motionRepeatCount'), repeat));

        if (this.img) {
            var bases = el('div', { class: 'pgie-chips', role: 'radiogroup', 'aria-label': t('motionBase') });

            M.MOTION_BASES.forEach(function (base) {
                var on = (m.base === base);

                bases.appendChild(el('button', { type: 'button', role: 'radio', class: 'pgie-chip' + (on ? ' on' : ''), 'aria-checked': on ? 'true' : 'false', text: t('motionBase_' + base), onclick: function () {
                    self.setDocMotion({ base: base });
                } }));
            });

            body.appendChild(section(t('motionBase'), bases));
        }
    };

    Editor.prototype.motionHeader = function () {
        var self = this, moving = this.motionLayers().some(function (l) { return !!l.anim; });

        if (!moving) {
            return null;
        }

        return el('button', { type: 'button', class: 'pgie-link', text: t('motionRemove'), onclick: function () {
            var ids = self.motionLayers().map(function (l) { return l.id; });

            self.change(function (doc) {
                doc.layers.forEach(function (l) {
                    if (ids.indexOf(l.id) >= 0) {
                        delete l.anim;
                    }
                });
            });
        } });
    };

    // ---- the play bar ----

    Editor.prototype.motionBar = function () {
        var self = this, end = this.motionEnd(), now = (this.time === null) ? 0 : this.time;
        var playBtn = button('pgie-mplay', this.playing ? t('motionPause') : t('motionPlay'), this.playing ? 'pause' : 'play', function () { self.togglePlay(); });
        var scrub = el('input', { type: 'range', class: 'pgie-range pgie-mscrub', min: '0', max: String(end), step: '10', value: String(now), 'aria-label': t('motionScrub'), 'aria-valuetext': clockText(now) });
        var ticks = el('div', { class: 'pgie-mticks', 'aria-hidden': 'true' });
        var clock = el('span', { class: 'pgie-mclock' }, [el('strong', { text: clockText(now) }), ' / ' + clockText(end)]);
        var repeat = button('pgie-ibtn sm pgie-mrepeat' + (this.motionRepeat ? ' on' : ''), t('motionRepeat'), 'repeat', function () {
            self.motionRepeat = !self.motionRepeat;
            repeat.classList.toggle('on', self.motionRepeat);
            repeat.setAttribute('aria-pressed', self.motionRepeat ? 'true' : 'false');
        });
        var length = el('span', { class: 'pgie-mlength', title: t('motionLength') }, [icon('clock'), el('span', { text: seconds(end) })]);

        repeat.setAttribute('aria-pressed', this.motionRepeat ? 'true' : 'false');

        // A tick where each moving layer starts.
        this.doc.layers.forEach(function (l) {
            var sp = M.motionSpans(l, end), at = [sp.inStart, sp.loopStart, sp.outStart].filter(function (n) { return n !== null; })[0];

            if (at !== undefined) {
                ticks.appendChild(el('span', { style: 'left: ' + (100 * at / end) + '%' }));
            }
        });

        scrub.addEventListener('input', function () { self.seek(Number(scrub.value)); });

        this.motionEls = { play: playBtn, scrub: scrub, clock: clock.firstChild };

        return el('div', { class: 'pgie-mbar', role: 'group', 'aria-label': t('motionBar') }, [
            playBtn, clock, el('div', { class: 'pgie-mtrack' }, [scrub, ticks]), repeat, length
        ]);
    };

    // ---- tracks ----

    var TRACK_ICONS = { image: 'image', text: 'text', sticker: 'sticker', path: 'vector', svg: 'vector' };
    // How near (px) an end snaps to another.
    var SNAP_PX = 6;

    // A row per layer, the top one first, under the play bar: the entrance
    // and exit as bars to drag (the bar moves, an end changes the length),
    // the loop hatched with a grip at its start; a ruler of seconds and the
    // playhead. Not on a phone: the panel's fields set the same numbers.
    Editor.prototype.motionTracks = function () {
        var self = this, end = this.motionEnd();
        var ruler = el('div', { class: 'pgie-mruler' });
        var lanes = el('div', { class: 'pgie-mlanes' });
        var head = el('span', { class: 'pgie-mhead', 'aria-hidden': 'true', style: 'left: ' + (100 * ((this.time === null) ? 0 : this.time) / end) + '%' });
        var rows = el('div', { class: 'pgie-mrows', role: 'list', 'aria-label': t('motionTracks') });
        var step = (end > 20000) ? 5000 : ((end > 8000) ? 2000 : 1000);

        for (var at = 0; at <= end; at += step) {
            ruler.appendChild(el('span', { style: 'left: ' + (100 * at / end) + '%', text: seconds(at) }));
        }

        // A press on the ruler moves the playhead there.
        ruler.addEventListener('pointerdown', function (e) {
            var r = ruler.getBoundingClientRect();

            self.seek(end * Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)));
        });

        this.doc.layers.slice().reverse().forEach(function (l) {
            if (l.hidden || (STILL.indexOf(l.type) >= 0)) {
                return;
            }

            var on = (self.sel.indexOf(l.id) >= 0);
            var lane = el('div', { class: 'pgie-mlane' });
            var row = el('div', { class: 'pgie-mrow' + (on ? ' on' : ''), role: 'listitem' }, [
                el('button', { type: 'button', class: 'pgie-mrname', 'aria-pressed': on ? 'true' : 'false', onclick: function () { self.select([l.id]); } }, [
                    icon(TRACK_ICONS[l.type] || 'draw'), el('span', { text: self.layerName(l) })
                ]),
                lane
            ]);

            self.motionLane(lane, l, end, on);
            // A press beside the bars selects the layer.
            lane.addEventListener('pointerdown', function (e) {
                if (e.target === lane) {
                    self.select([l.id]);
                }
            });
            rows.appendChild(row);
        });

        lanes.appendChild(head);
        this.motionEls = this.motionEls || {};
        this.motionEls.head = head;

        return el('div', { class: 'pgie-mtracks' }, [
            el('div', { class: 'pgie-mrhead' }, [el('span', { class: 'pgie-mrlabel', text: t('layers') }), ruler]),
            rows,
            lanes
        ]);
    };

    // One layer's bars on its lane, from its spans.
    Editor.prototype.motionLane = function (lane, l, end, on) {
        var self = this, a = l.anim || {}, sp = M.motionSpans(l, end);
        var place = function (n, from, to) {
            n.style.left = (100 * from / end) + '%';
            n.style.width = Math.max(0, 100 * (to - from) / end) + '%';
        };

        lane.textContent = '';

        [['in', sp.inStart, sp.inEnd], ['loop', sp.loopStart, sp.loopEnd], ['out', sp.outStart, sp.outEnd]].forEach(function (seg) {
            var phase = seg[0];

            if ((seg[1] === null) || (seg[2] <= seg[1])) {
                return;
            }

            var label = self.motionSegText(l, phase, seg[1], seg[2]);
            var bar = el('div', { class: 'pgie-mseg pgie-mseg-' + phase, title: label, 'aria-label': label, role: 'img' });

            place(bar, seg[1], seg[2]);

            if (phase !== 'loop') {
                bar.appendChild(el('span', { class: 'pgie-mgrip', 'data-part': 'start' }));
                bar.appendChild(el('span', { class: 'pgie-mgrip', 'data-part': 'end' }));
                bar.addEventListener('pointerdown', function (e) { self.trackDown(e, l, phase, bar); });
            } else {
                bar.appendChild(el('span', { class: 'pgie-mgrip', 'data-part': 'start' }));
                bar.addEventListener('pointerdown', function (e) {
                    if (e.target.getAttribute('data-part') === 'start') {
                        self.trackDown(e, l, phase, bar);
                    }
                });
            }

            lane.appendChild(bar);
        });

        lane.classList.toggle('pgie-mempty', !(a['in'] || a.loop || a.out));
    };

    // "Typewriter · 1.6 → 2.4 s"
    Editor.prototype.motionSegText = function (l, phase, from, to) {
        var cur = (l.anim || {})[phase] || {};

        return t('motion_' + cur.kind) + ' · ' + X.decimal(from / 1000) + ' → ' + seconds(to);
    };

    // A press on a bar: an end changes the length, the bar moves.
    Editor.prototype.trackDown = function (e, l, phase, bar) {
        var self = this, end = this.motionEnd(), lane = bar.parentNode, r = lane.getBoundingClientRect();
        var part = e.target.getAttribute('data-part') || 'move', sp = M.motionSpans(l, end);
        var from = { 'in': [sp.inStart, sp.inEnd], loop: [sp.loopStart, sp.loopEnd], out: [sp.outStart, sp.outEnd] }[phase];
        var edge = (part === 'end') ? from[1] : from[0], targets = M.trackTargets(this.doc.layers, end, l.id);
        var draft = null, tip = el('span', { class: 'pgie-mtip', role: 'status' });

        if (e.button !== 0) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        if (this.playing) {
            this.pause();
            return;
        }

        if (this.sel.indexOf(l.id) < 0) {
            this.sel = [l.id];
        }

        bar.setPointerCapture(e.pointerId);
        bar.classList.add('pgie-mdrag');
        lane.appendChild(tip);

        var move = function (ev) {
            var at = edge + (ev.clientX - e.clientX) * end / r.width;

            at = M.snapTime(at, targets, SNAP_PX * end / r.width, ev.shiftKey ? 0 : 100);

            // A moved bar snaps by whichever of its ends lands nearer a target.
            if ((part === 'move') && !ev.shiftKey) {
                var len = from[1] - from[0], byEnd = M.snapTime(at + len, targets, SNAP_PX * end / r.width, 0);

                if (targets.indexOf(byEnd) >= 0) {
                    at = byEnd - len;
                }
            }

            var copy = Object.assign({}, l, { anim: Object.assign({}, l.anim) });

            copy.anim[phase] = M.trackSet(l, phase, part, at, end);
            draft = copy.anim[phase];

            // The bars move in place: drawing them again would drop the
            // pointer this one holds.
            var nsp = M.motionSpans(copy, end), spans = { 'in': [nsp.inStart, nsp.inEnd], loop: [nsp.loopStart, nsp.loopEnd], out: [nsp.outStart, nsp.outEnd] }, span = spans[phase];

            Object.keys(spans).forEach(function (k) {
                var seg = lane.querySelector('.pgie-mseg-' + k);

                if (seg && (spans[k][0] !== null)) {
                    seg.style.left = (100 * spans[k][0] / end) + '%';
                    seg.style.width = Math.max(0, 100 * (spans[k][1] - spans[k][0]) / end) + '%';
                }
            });

            tip.textContent = self.motionSegText(copy, phase, span[0], span[1]);
            tip.style.left = (100 * span[1] / end) + '%';
        };

        var up = function () {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            window.removeEventListener('pointercancel', up);

            if (!draft || (JSON.stringify(draft) === JSON.stringify((l.anim || {})[phase]))) {
                self.render();
                return;
            }

            var id = l.id, value = draft;

            self.change(function (doc) {
                doc.layers.forEach(function (x) {
                    if (x.id === id) {
                        x.anim = Object.assign({}, x.anim);
                        x.anim[phase] = value;
                    }
                });
            });
        };

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', up);
    };

    // The bar under the stage, or first in the sheet on a phone.
    Editor.prototype.renderMotion = function () {
        var onPhone = window.matchMedia && window.matchMedia(PHONE).matches;
        var wanted = (this.tool === 'motion') && !onPhone, had = !!this.stageBar;

        if (this.tool !== 'motion') {
            if (this.time !== null) {
                this.stopMotion(true);
            }
        }

        // Drawn again only when what it shows changed: a zoom redraws the
        // view, and a scrub in progress must keep its slider.
        var key = wanted ? [this.motionEnd(), this.playing, this.motionRepeat, this.sel.join(','),
            JSON.stringify(this.doc.layers.map(function (l) { return [l.id, l.hidden ? 1 : 0, l.anim || 0, l.text || '']; }))].join('|') : '';

        if (this.stageBar && (key !== this.stageBarKey)) {
            this.stageBar.remove();
            this.stageBar = null;
        }

        if (wanted && !this.stageBar) {
            this.stageBar = el('div', { class: 'pgie-mdock' }, [this.motionBar(), this.motionTracks()]);
            // The dock sits on the stage: its presses are its own, not a
            // press on the picture that would select or pan.
            this.stageBar.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
            this.stageBarKey = key;
            this.stage.appendChild(this.stageBar);
        }

        // A sheet open over the stage's right (a phone held sideways, a
        // window 600-767 px wide) would hide the bar's end: it stops there.
        // A short bar leaves out the length, which the panel shows too.
        if (this.stageBar) {
            var cover = this.panelCover();

            this.stageBar.style.right = cover + 'px';
            this.stageBar.classList.toggle('pgie-mshort', (this.stage.clientWidth - cover) < 480);
        }

        // The bar takes the stage's bottom: a fitted picture moves up.
        var inset = this.stageBar ? this.stageBar.offsetHeight : 0;

        if ((inset !== (this.stageInset || 0)) || (had !== !!this.stageBar)) {
            this.stageInset = inset;

            if (this.fitMode) {
                this.fit();
                return;
            }
        }

        if (this.time !== null) {
            this.applyMotion();
        }
    };

    Editor.prototype.updateBar = function () {
        var e = this.motionEls, now = (this.time === null) ? 0 : this.time;

        if (!e) {
            return;
        }

        if (e.head) {
            e.head.style.left = (100 * now / this.motionEnd()) + '%';
        }

        e.scrub.value = String(now);
        e.scrub.setAttribute('aria-valuetext', clockText(now));
        e.clock.textContent = clockText(now);
    };

    Editor.prototype.togglePlay = function () {
        if (this.playing) {
            this.pause();
        } else {
            this.play();
        }
    };

    Editor.prototype.play = function () {
        var self = this, end = this.motionEnd(), last = null;

        if ((this.time === null) || (this.time >= end)) {
            this.time = 0;
        }

        this.playing = true;
        this.render();

        var tick = function (now) {
            if (!self.playing || !self.dialog.open) {
                self.playing = false;
                return;
            }

            if (last !== null) {
                self.time += now - last;
            }

            last = now;
            end = self.motionEnd();

            if (self.time >= end) {
                if (self.motionRepeat) {
                    self.time = self.time % end;
                } else {
                    self.time = end;
                    self.applyMotion();
                    self.pause();
                    return;
                }
            }

            self.applyMotion();
            self.updateBar();
            self.raf = window.requestAnimationFrame(tick);
        };

        this.raf = window.requestAnimationFrame(tick);
    };

    Editor.prototype.pause = function () {
        this.playing = false;
        window.cancelAnimationFrame(this.raf);
        this.render();
    };

    Editor.prototype.seek = function (ms) {
        if (this.playing) {
            this.playing = false;
            window.cancelAnimationFrame(this.raf);

            if (this.motionEls) {
                this.motionEls.play.replaceWith(this.motionEls.play = button('pgie-mplay', t('motionPlay'), 'play', this.togglePlay.bind(this)));
            }
        }

        this.time = Math.max(0, Math.min(this.motionEnd(), ms));
        this.applyMotion();
        this.updateBar();
    };

    // Back to rest: every layer as it is edited.
    Editor.prototype.stopMotion = function (quiet) {
        this.playing = false;
        window.cancelAnimationFrame(this.raf);
        this.time = null;
        this.layersDirty = true;

        if (!quiet) {
            this.renderView();
        } else {
            this.renderLayers();
        }
    };

    // A changed phase shows at once: the playhead moves to where it runs.
    Editor.prototype.motionPreview = function (phase) {
        var l = this.motionLayers()[0];

        if (!l || this.playing) {
            return;
        }

        var sp = M.motionSpans(l, this.motionEnd());
        var at = (phase === 'out') ? sp.outStart : ((phase === 'loop') ? sp.loopStart : sp.inStart);

        if ((at !== null) && (at !== undefined)) {
            this.seek(at);
        }
    };

    // ---- the stage at the playhead ----

    Editor.prototype.applyMotion = function () {
        var self = this, end = this.motionEnd(), groups = this.layerGroups || {};

        this.doc.layers.forEach(function (l) {
            var g = groups[l.id];

            if (g) {
                self.motionGroup(g, l, M.motionAt(l, self.time, end));
            }
        });

        this.motionBase(end);
    };

    Editor.prototype.motionGroup = function (g, l, st) {
        var cx = (Number(l.x) || 0) + (Number(l.w) || 0) / 2, cy = (Number(l.y) || 0) + (Number(l.h) || 0) / 2;
        var moved = st.x || st.y || st.rot || (st.scale !== 1);
        var cut = ((st.chars !== null) ? 'c' + st.chars : '') + ((st.draw < 1) ? 'd' : '');

        if (moved) {
            g.setAttribute('transform', 'translate(' + (cx + st.x) + ' ' + (cy + st.y) + ') rotate(' + st.rot + ') scale(' + st.scale + ') translate(' + (-cx) + ' ' + (-cy) + ')');
        } else {
            g.removeAttribute('transform');
        }

        if (st.opacity < 1) {
            g.setAttribute('opacity', String(Math.max(0, st.opacity)));
        } else {
            g.removeAttribute('opacity');
        }

        this.motionClip(g, l, st.clip);

        // A typed text or a drawn stroke: the node again, cut.
        if ((g.pgCut || '') !== cut) {
            var copy = (st.chars !== null) ? Object.assign({}, l, { text: Array.from(String(l.text || '')).slice(0, st.chars).join('') }) : l;
            var n = this.layerNode(copy);

            g.textContent = '';

            if (n) {
                g.appendChild(n);
            }

            g.pgCut = cut;
        }

        if (st.draw < 1) {
            Array.prototype.forEach.call(g.querySelectorAll('path, line, polyline, polygon, rect, ellipse, circle'), function (s) {
                s.setAttribute('pathLength', '1');
                s.setAttribute('stroke-dasharray', '1 1');
                s.setAttribute('stroke-dashoffset', String(1 - st.draw));
                s.setAttribute('fill-opacity', String(st.draw));
            });
        }
    };

    // A wipe: the layer's box cut from one side.
    Editor.prototype.motionClip = function (g, l, clip) {
        var svg = this.layersSvg, id = this.fxId + '-m-' + l.id, cp = svg.querySelector('[id="' + id + '"]');

        if (!clip) {
            g.removeAttribute('clip-path');
            return;
        }

        if (!cp) {
            var defs = svg.querySelector('defs') || svg.insertBefore(document.createElementNS(SVGNS, 'defs'), svg.firstChild);

            cp = document.createElementNS(SVGNS, 'clipPath');
            cp.setAttribute('id', id);
            cp.appendChild(document.createElementNS(SVGNS, 'rect'));
            defs.appendChild(cp);
        }

        // A turned layer's box is wider than its own: the cut covers its corners.
        var b = M.bounds([l]), r = cp.firstChild, f = clip.f;
        var x = b.x, y = b.y, w = b.w, h = b.h;

        if (clip.side === 'left') {
            w = b.w * f;
        } else if (clip.side === 'right') {
            x = b.x + b.w * (1 - f);
            w = b.w * f;
        } else if (clip.side === 'top') {
            h = b.h * f;
        } else {
            y = b.y + b.h * (1 - f);
            h = b.h * f;
        }

        r.setAttribute('x', x);
        r.setAttribute('y', y);
        r.setAttribute('width', Math.max(0, w));
        r.setAttribute('height', Math.max(0, h));
        g.setAttribute('clip-path', 'url(#' + id + ')');
    };

    // The picture's slow zoom or pan over the whole animation.
    Editor.prototype.motionBase = function (end) {
        var base = M.docMotion(this.doc).base;

        if (!this.img || (base === 'none')) {
            return;
        }

        var st = M.baseAt(base, this.time, end), vr = this.viewRect(), k = this.view.k;
        var cx = vr.x + vr.w / 2, cy = vr.y + vr.h / 2;
        var move = M.mul(M.mul(M.translate(cx + st.x * vr.w, cy + st.y * vr.h), M.scale(st.scale)), M.translate(-cx, -cy));
        var m = M.mul(M.mul(M.scale(k), M.translate(-vr.x, -vr.y)), M.mul(move, M.pictureMatrix(this.doc)));

        this.img.style.transform = 'matrix(' + m.map(function (n) { return Math.round(n * 1e6) / 1e6; }).join(', ') + ')';
    };

    // ---- keys and pointers ----

    // K plays and pauses in the Animate tool, the video players' key; Space
    // keeps moving the view as in every tool.
    Editor.prototype.motionKey = function (e, mod, k) {
        if ((this.tool !== 'motion') || mod || e.altKey || ((k !== 'k') && (k !== 'K'))) {
            return false;
        }

        if (!e.repeat) {
            this.togglePlay();
        }

        return true;
    };

    Editor.prototype.motionEscape = function () {
        if (this.playing) {
            this.pause();
            return true;
        }

        return false;
    };

    // A press on the stage edits at rest.
    Editor.prototype.motionDown = function (e) {
        if (this.time !== null) {
            this.stopMotion();
        }

        return this.startDrag(e);
    };

    Editor.inits.push(function () {
        this.time = null;
        this.playing = false;
        this.raf = 0;
        this.motionTab = 'in';
        this.motionRepeat = true;
        this.motionEls = null;
        this.stageBar = null;
        this.stageBarKey = '';
        this.stageInset = 0;
    });

    Editor.panels.motion = Editor.prototype.motionPanel;
    Editor.headers.motion = Editor.prototype.motionHeader;
    Editor.hints.motion = 'hintMotion';
    Editor.pointers.motion = { down: Editor.prototype.motionDown, move: Editor.prototype.moveDrag, up: Editor.prototype.endDrag, double: Editor.prototype.editLayer };
    Editor.selectTools.push('motion');
    Editor.keys.unshift(Editor.prototype.motionKey);
    Editor.escapes.unshift(Editor.prototype.motionEscape);
    Editor.views.push(Editor.prototype.renderMotion);
}(window.PgImageEditor.parts));
