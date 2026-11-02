/**
 * Pinegrap - Enterprise Website Platform
 *
 * Image editor: the Animate tool's tracks: a row per layer under the play
 * bar, with a bar from the start of its entrance to the end of its exit
 * (entrance, a hatched loop, a faded exit, a thin line while it only stays)
 * and the playhead. Dragging a bar moves its delay, dragging an end changes
 * its length; ends snap to a tenth of a second and to the other layers'
 * ends (Shift: a hundredth, no snap). On a phone the panel's fields do it.
 *
 * The drag shows on the bar and is one change on release (M.trackEdit()), so
 * the play bar is not drawn again under the pointer.
 *
 * A part of window.PgImageEditor, bundled after pg_image_editor_motion.js
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

    var Editor = X.Editor, M = X.M, t = X.t, el = X.el, icon = X.icon;

    // A phone (upright or sideways) gets the panel's fields only.
    var NO_TRACKS = '(max-width: 599.98px), (max-height: 499.98px)';
    // A dragged end snaps to another end within this many pixels.
    var SLACK_PX = 6;
    var TYPE_ICONS = { path: 'vector', svg: 'vector' };
    var PHASE_LABELS = { 'in': 'motionIn', loop: 'motionLoop', out: 'motionOut' };

    function seconds(ms) {
        return X.decimal(ms / 1000) + ' ' + t('motionSecondsUnit');
    }

    function pct(ms, end) {
        return (100 * Math.max(0, Math.min(end, ms)) / end) + '%';
    }

    Editor.prototype.tracksShown = function () {
        return !(window.matchMedia && window.matchMedia(NO_TRACKS).matches) && (this.doc.layers.length > 0);
    };

    // What the rows show besides the motion the play bar keys on already.
    Editor.prototype.tracksKey = function () {
        var self = this;

        // A drag in progress keeps its bar: the rows are drawn again on release.
        if (this.trackDragging) {
            return this.trackKeyNow;
        }

        this.trackKeyNow = this.tracksShown() ? JSON.stringify([this.sel, this.doc.layers.map(function (l) { return [l.id, self.layerName(l), l.hidden ? 1 : 0]; })]) : '';

        return this.trackKeyNow;
    };

    // The kind and its times, for the selected bar's tip: "Typewriter ·
    // 1.6 s → 2.4 s"; the phase's name when the kind has no label.
    function barTip(l, b) {
        var kind = ((l.anim || {})[b.phase] || {}).kind;

        return (kind ? t('motion_' + kind) : t(PHASE_LABELS[b.phase])) + ' · ' + seconds(b.from) + ' → ' + seconds(b.to);
    }

    Editor.prototype.tracksNode = function () {
        var self = this, end = this.motionEnd(), rows, ruler, head, s;

        if (!this.tracksShown()) {
            this.trackEls = null;
            return null;
        }

        ruler = el('div', { class: 'pgie-trruler', 'aria-hidden': 'true' });

        // A labelled tick each second, up to ten labels; a short one between.
        var stepS = Math.max(1, Math.ceil(end / 10000));

        for (s = 0; s * 1000 <= end; s += 1) {
            ruler.appendChild(el('span', { class: (s % stepS) ? 'pgie-trtick' : 'pgie-trtick pgie-trlabel', style: 'left: ' + pct(s * 1000, end), text: (s % stepS) ? '' : X.decimal(s) + ' ' + t('motionSecondsUnit') }));
        }

        // A press on the ruler moves the playhead there.
        ruler.addEventListener('pointerdown', function (e) {
            var r = ruler.getBoundingClientRect();

            if (r.width > 0) {
                self.seek(Math.round((e.clientX - r.left) / r.width * end / 10) * 10);
            }
        });

        head = el('div', { class: 'pgie-trhead' }, [el('span', { class: 'pgie-trname', text: t('tracksLayers') }), el('div', { class: 'pgie-trlane' }, [ruler])]);
        rows = el('div', { class: 'pgie-trrows' });

        // The top layer first, as in the layers list; a hidden layer and a
        // redaction (which never moves) have no row.
        this.doc.layers.slice().reverse().forEach(function (l) {
            if (!l.hidden && (l.type !== 'redact')) {
                rows.appendChild(self.trackRow(l, end));
            }
        });

        var playhead = el('div', { class: 'pgie-trplay', 'aria-hidden': 'true' });
        var box = el('div', { class: 'pgie-tracks', role: 'group', 'aria-label': t('tracksRegion') }, [head, rows, playhead]);

        this.trackEls = { playhead: playhead, box: box };
        this.updateTracks();

        return box;
    };

    Editor.prototype.trackRow = function (l, end) {
        var self = this, on = (this.sel.indexOf(l.id) >= 0), name = this.layerName(l);
        var lane = el('div', { class: 'pgie-trlane' }, [el('span', { class: 'pgie-trline' })]);
        var pick = el('button', { type: 'button', class: 'pgie-trname', title: name, 'aria-pressed': on ? 'true' : 'false', onclick: function (e) {
            self.trackSelect(l.id, e.shiftKey);
        } }, [icon(TYPE_ICONS[l.type] || l.type), el('span', { text: name })]);

        M.trackBars(l, end).forEach(function (b) {
            var bar = el('span', { class: 'pgie-trbar pgie-tr-' + b.phase, style: 'left: ' + pct(b.from, end) + '; width: ' + pct(b.to - b.from, end), title: barTip(l, b) });

            bar.addEventListener('pointerdown', function (e) { self.trackDown(e, l, b, 'move', bar); });

            if (on && (self.sel.length === 1)) {
                if (b.phase !== 'loop') {
                    ['start', 'end'].forEach(function (grip) {
                        var g = el('span', { class: 'pgie-trgrip pgie-trgrip-' + grip, 'aria-hidden': 'true' });

                        g.addEventListener('pointerdown', function (e) {
                            e.stopPropagation();
                            self.trackDown(e, l, b, grip, bar);
                        });
                        bar.appendChild(g);
                    });
                }

                // Past the middle the tip goes left of the bar, inside the stage.
                bar.appendChild(el('span', { class: 'pgie-trtip' + ((b.to > end / 2) ? ' pgie-trtip-left' : ''), text: barTip(l, b) }));
            }

            lane.appendChild(bar);
        });

        // A press beside the bars selects the layer.
        lane.addEventListener('pointerdown', function (e) {
            if (e.target === lane || e.target.classList.contains('pgie-trline')) {
                self.trackSelect(l.id, e.shiftKey);
            }
        });

        return el('div', { class: 'pgie-trrow' + (on ? ' on' : '') + (l.anim ? '' : ' pgie-trstill') }, [pick, lane]);
    };

    // The panel's note under a phase's fields, where the tracks show.
    Editor.prototype.tracksNote = function () {
        return this.tracksShown() ? el('p', { class: 'pgie-note pgie-trnote', text: t('tracksNote') }) : null;
    };

    Editor.prototype.trackSelect = function (id, add) {
        var ids = add ? this.sel.filter(function (n) { return n !== id; }) : [];

        if (!add || (ids.length === this.sel.length)) {
            ids.push(id);
        }

        this.select(ids);
    };

    // A press on a bar or a grip: the layer is selected, the drag follows
    // the pointer on the bar and is one change on release.
    Editor.prototype.trackDown = function (e, l, b, grip, bar) {
        var self = this, end = this.motionEnd(), lane = bar.parentNode, width = lane.getBoundingClientRect().width;
        var x0 = e.clientX, start = M.clone(l), patch = null, moved = false;
        var targets = M.trackTargets(this.doc.layers, end, l.id), tip = bar.querySelector('.pgie-trtip');

        if ((e.button !== 0) || !(width > 0)) {
            return;
        }

        e.preventDefault();

        // A press while playing pauses first; the next one drags.
        if (this.playing) {
            this.pause();
            return;
        }

        if ((this.sel.length !== 1) || (this.sel[0] !== l.id)) {
            // A drag that starts on another layer's bar selects it first.
            this.sel = [l.id];
            this.renderPanel();
        }

        this.trackDragging = true;

        try {
            bar.setPointerCapture(e.pointerId);
        } catch (err) {
            // A pointer the browser no longer tracks: the drag still follows moves.
        }

        var move = function (ev) {
            var dt = (ev.clientX - x0) / width * end, copy, nb;

            moved = moved || (Math.abs(ev.clientX - x0) > 2);
            patch = M.trackEdit(start, b.phase, grip, dt, end, { targets: targets, slack: SLACK_PX / width * end, fine: ev.shiftKey });

            if (!patch) {
                return;
            }

            copy = M.clone(start);
            copy.anim[b.phase] = Object.assign({}, copy.anim[b.phase], patch);
            nb = M.trackBars(copy, end).filter(function (n) { return n.phase === b.phase; })[0];

            if (nb) {
                bar.style.left = pct(nb.from, end);
                bar.style.width = pct(nb.to - nb.from, end);
                bar.title = barTip(start, nb);

                if (tip) {
                    tip.textContent = barTip(start, nb);
                }
            }
        };

        var up = function () {
            bar.removeEventListener('pointermove', move);
            bar.removeEventListener('pointerup', up);
            bar.removeEventListener('pointercancel', up);
            self.trackDragging = false;

            if (moved && patch) {
                self.setTrack(l.id, b.phase, patch);
            } else {
                self.render();
            }
        };

        bar.addEventListener('pointermove', move);
        bar.addEventListener('pointerup', up);
        bar.addEventListener('pointercancel', up);
    };

    // One phase of one layer: the drag's result, one undo step.
    Editor.prototype.setTrack = function (id, phase, patch) {
        this.change(function (doc) {
            doc.layers.forEach(function (l) {
                if ((l.id === id) && l.anim && l.anim[phase]) {
                    l.anim[phase] = Object.assign({}, l.anim[phase], patch);
                }
            });
        });
    };

    // The playhead's line over the lanes, at rest at 0.
    Editor.prototype.updateTracks = function () {
        var e = this.trackEls, now = (this.time === null) ? 0 : this.time;

        if (!e) {
            return;
        }

        e.playhead.style.setProperty('--pgie-trat', String(Math.max(0, Math.min(1, now / this.motionEnd()))));
        e.playhead.classList.toggle('pgie-tridle', this.time === null);
    };

    Editor.inits.push(function () {
        this.trackEls = null;
        this.trackDragging = false;
        this.trackKeyNow = '';
    });

    Editor.motionDocks.push({ key: Editor.prototype.tracksKey, node: Editor.prototype.tracksNode, update: Editor.prototype.updateTracks, note: Editor.prototype.tracksNote });
    Editor.hints.motion = 'hintTracks';
}(window.PgImageEditor.parts));
