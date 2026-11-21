/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the conversation timeline: the channel's current conversation
 * in one quiet well at the end of the tabs row, or at the foot of Channel
 * info where the row has no room. Each day is a column with its short date
 * over it; one writer's messages close together are a turn, turns with no
 * long wait between them stand side by side as one bar (a run), and the
 * space between two bars grows with the wait. A decision is the only mark
 * on the bars. An accent line under the bars shows the part of the
 * conversation on the screen.
 *
 * Pointing at a turn shows a card of its message: who wrote it, when, its
 * first words, and a shortcut row for every thing it carries (a note, a
 * task, a file, a picture, a table, a poll...) that opens that thing. A
 * click on the turn, or "Open the message", opens the message in the
 * conversation. Ctrl with the wheel zooms, Shift with the wheel moves
 * along; the plain wheel is left to the page.
 *
 * It reads ws_conversation_timeline (includes/workspace/
 * conversation_timeline.php) when a channel opens, and again when the
 * channel's sync saw a message, a vote or an answer change (at most once in
 * eight seconds). The current conversation only: never an earlier version.
 *
 * assets/js/workspace.js mounts it from drawCenter() and drawInfo() through
 * window.PGWsConversationTimeline.mount() and lends its helpers through
 * window.PGWsKit. Every text comes from the #ws-config block
 * (ws_conversation_timeline_js_strings()).
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // A wait this long, in seconds, starts a new run; one writer's messages
    // this close together are one turn.
    var BREAK = 1800;
    var JOIN = 120;

    // A turn weighs the square root of its messages plus one for every
    // TURN_SECONDS it lasts; a wait between runs the square root of its
    // halves of an hour. A weight of one is drawn as wide as the scale.
    var TURN_SECONDS = 240;
    var WAIT_SECONDS = 1800;

    // The layout in pixels: the room before the first and after the last
    // run, the room on either side of a day's hairline, the least, the
    // most and the fully zoomed out space between runs, the space a wait's
    // weight gets per scale, and a turn's least width, zoomed in and fully
    // out (under OUT_SCALE).
    var PAD_START = 6;
    var PAD_END = 8;
    var DAY_SIDE = 8;
    var RUN_GAP = 8;
    var RUN_GAP_MAX = 26;
    var RUN_GAP_OUT = 3;
    var WAIT_PX = 1.4;
    var TURN_MIN = 6;
    var TURN_MIN_OUT = 2;
    var OUT_SCALE = 2;

    // The scale a strip opens at, the most it zooms in and the least it can
    // ever be.
    var SCALE_OPEN = 5.2;
    var SCALE_TOP = 48;
    var SCALE_LEAST = 0.05;

    // A day's date needs this much column, its day number this much; the
    // date of a day cut at the left edge stands this far in, until this
    // much of the day is left.
    var DATE_ROOM_PX = 34;
    var DAY_ROOM_PX = 13;
    var STICKY_PX = 22;
    var STICKY_END_PX = 40;

    // The least width the tabs row must leave the well, in pixels.
    var NARROW_PX = 220;

    // How long the card stays after the pointer left the turn and the
    // card, in milliseconds; the shortcut rows a card shows at most.
    var LINGER = 200;
    var ROWS = 4;

    // Where the viewer's zoom of a channel's strip is kept in the browser.
    var ZOOM_KEY = 'pg_ws_ctl_scale_';

    // The least time between two reads asked by the sync, and how long a
    // read is drawn again without asking, in milliseconds.
    var BRAKE = 8000;
    var FRESH = 60000;

    // The tile of each kind of shortcut row, and of the decision's line.
    var TOOL_ICONS = {
        decision: 'bi-bookmark-check', note: 'bi-journal-text', task: 'bi-check2-square', canvas: 'bi-easel',
        picture: 'bi-image', file: 'bi-file-earmark', voice: 'bi-mic', table: 'bi-table', poll: 'bi-bar-chart',
        question: 'bi-question-circle', approval: 'bi-patch-check', ack: 'bi-eye', status: 'bi-activity',
        checkin: 'bi-person-raised-hand', agent: 'bi-stars', more: 'bi-three-dots'
    };
    var TOOL_TONES = {
        decision: 'amber', note: 'amber', task: 'blue', canvas: 'teal', picture: 'violet', file: 'gray',
        voice: 'rose', table: 'teal', poll: 'blue', question: 'blue', approval: 'teal', ack: 'blue',
        status: 'teal', checkin: 'blue', agent: 'violet', more: 'gray'
    };

    // The colours a writer is drawn in, by the order they first wrote.
    var TONES = 8;

    var cache = { channelId: 0, data: null, readAt: 0, reading: null, braked: 0, fresh: {}, again: false };
    var views = {};
    var card = null;

    // ── Pure parts (tests/workspace_conversation_timeline_test.php) ──

    // The conversation in runs and turns: a run ends where a wait of BREAK
    // seconds or more begins, and where a new day begins when dayOf(at)
    // names the day of a time (such a run has day: true); a turn is one
    // writer's messages, each within JOIN seconds of the one before.
    // Indexes point into items. Pure.
    function timelineRuns(items, join, brk, dayOf) {
        var runs = [];
        var run = null;
        var turn = null;
        var day = null;

        join = (join === undefined) ? JOIN : join;
        brk = (brk === undefined) ? BREAK : brk;

        (items || []).forEach(function (item, index) {
            var before = index ? items[index - 1] : null;
            var wait = before ? Math.max(0, item.at - before.at) : 0;
            var key = dayOf ? dayOf(item.at) : '';
            var newDay = !!run && (key !== day);

            day = key;

            if (!run || (wait >= brk) || newDay) {
                run = { turns: [], day: newDay };
                runs.push(run);
                turn = null;
            }

            if (turn && (turn.s === item.s) && (wait <= join)) {
                turn.to = index;
                turn.end = item.at;
                return;
            }

            turn = { s: item.s, from: index, to: index, start: item.at, end: item.at, wait: before ? wait : 0 };
            run.turns.push(turn);
        });

        return runs;
    }

    // The strip laid out in pixels at a scale: every day as a column (its
    // hairline, where its first turn starts and its last ends, its first
    // and last message), every run as one bar of turns 1 px apart, the
    // space between two runs by the wait. Zoomed fully out (a scale under
    // OUT_SCALE) a turn may be 2 px and a space 3 px. Pure.
    function timelineLayout(runs, scale) {
        var out = { width: 0, scale: scale, runs: [], days: [] };
        var zoomedOut = scale < OUT_SCALE;
        var x = PAD_START;
        var day = null;

        (runs || []).forEach(function (run, index) {
            if (!run.turns.length) {
                return;
            }

            if (!day || run.day) {
                var line = null;

                if (day) {
                    x += DAY_SIDE;
                    line = x;
                    x += 1 + DAY_SIDE;
                }

                day = { line: line, first: x, end: x, from: run.turns[0].from, to: run.turns[0].from };
                out.days.push(day);
            } else {
                x += Math.round(Math.max(zoomedOut ? RUN_GAP_OUT : RUN_GAP, Math.min(RUN_GAP_MAX, Math.sqrt(run.turns[0].wait / WAIT_SECONDS) * scale * WAIT_PX)));
            }

            var left = x;
            var turns = [];

            run.turns.forEach(function (turn, at) {
                var weight = Math.sqrt(((turn.end - turn.start) / TURN_SECONDS) + (turn.to - turn.from + 1));
                var width = Math.max(zoomedOut ? TURN_MIN_OUT : TURN_MIN, Math.round(weight * scale));

                turns.push({ left: x, width: width, from: turn.from, to: turn.to, s: turn.s, first: !at, last: at === run.turns.length - 1 });
                x += width + 1;
            });

            x -= 1;
            day.end = x;
            day.to = run.turns[run.turns.length - 1].to;
            out.runs.push({ left: left, width: x - left, turns: turns, day: !!(index && run.day) });
        });

        out.width = out.runs.length ? x + PAD_END : 0;

        return out;
    }

    // The scales a strip zooms between: from the one that shows the whole
    // conversation in width pixels (never more than the opening scale, so
    // a short conversation stands at the left and does not stretch) to the
    // top. Pure.
    function timelineZoomRange(runs, width) {
        var low = SCALE_LEAST;
        var high = SCALE_OPEN;

        if (!(width > 0) || !runs.length) {
            return { low: SCALE_OPEN, top: SCALE_TOP };
        }

        if (timelineLayout(runs, high).width <= width) {
            return { low: SCALE_OPEN, top: SCALE_TOP };
        }

        if (timelineLayout(runs, low).width > width) {
            return { low: SCALE_LEAST, top: SCALE_TOP };
        }

        for (var step = 0; step < 30; step++) {
            var middle = (low + high) / 2;

            if (timelineLayout(runs, middle).width > width) {
                high = middle;
            } else {
                low = middle;
            }
        }

        return { low: low, top: SCALE_TOP };
    }

    // The message under a point of the strip: in the turn the point is on
    // or the nearest one (away: how far it is), the message at the point's
    // share of the turn. null on an empty strip. Pure.
    function timelinePick(layout, x) {
        var best = null;
        var distance = Infinity;

        layout.runs.forEach(function (run) {
            run.turns.forEach(function (turn) {
                var away = (x < turn.left) ? (turn.left - x) : ((x > turn.left + turn.width) ? (x - turn.left - turn.width) : 0);

                if (away < distance) {
                    distance = away;
                    best = turn;
                }
            });
        });

        if (!best) {
            return null;
        }

        var count = best.to - best.from + 1;
        var share = (x - best.left) / (best.width || 1);
        var index = best.from + Math.min(count - 1, Math.max(0, Math.floor(share * count)));

        return { turn: best, index: index, away: distance };
    }

    // Where a message stands on the strip: its share of its turn (left to
    // right) and the middle of it (x); null for a message not on it. Pure.
    function timelinePlace(layout, index) {
        var found = null;

        layout.runs.forEach(function (run) {
            run.turns.forEach(function (turn) {
                if ((index >= turn.from) && (index <= turn.to)) {
                    var count = turn.to - turn.from + 1;
                    var left = turn.left + ((index - turn.from) / count) * turn.width;
                    var right = turn.left + ((index - turn.from + 1) / count) * turn.width;

                    found = { turn: turn, x: (left + right) / 2, left: left, right: right };
                }
            });
        });

        return found;
    }

    // The stretch of the strip from one message to another, for the line
    // under the part on the screen; null when either is not on it. Pure.
    function timelineSpan(layout, first, last) {
        var from = timelinePlace(layout, Math.min(first, last));
        var to = timelinePlace(layout, Math.max(first, last));

        return (from && to) ? { left: from.left, right: to.right } : null;
    }

    // A point of the strip as the message under it and the point's share
    // of that message, so a zoom keeps the message under the pointer. Pure.
    function timelineAnchor(layout, x) {
        var found = timelinePick(layout, x);

        if (!found) {
            return null;
        }

        var place = timelinePlace(layout, found.index);

        return { index: found.index, share: Math.max(0, Math.min(1, (x - place.left) / ((place.right - place.left) || 1))) };
    }

    // A day's date as its column has room for it: the whole date, its day
    // number, or nothing. Pure.
    function timelineDayText(full, short, room) {
        if (room >= DATE_ROOM_PX) {
            return full;
        }

        return (room >= DAY_ROOM_PX) ? (short || '') : '';
    }

    // Where a day's date stands: at its first turn, or, while the day is
    // cut at the left edge and enough of it is left, just inside the edge.
    // Pure.
    function timelineDayLeft(day, scrollLeft) {
        if ((scrollLeft > day.first) && (scrollLeft < day.end - STICKY_END_PX)) {
            return scrollLeft + STICKY_PX;
        }

        return day.first;
    }

    // The message a run's or a day's step reaches (unit 'run' or 'day'):
    // forward the first of the next one (the last message at the end), back
    // the first of this one, or of the one before when already there. Pure.
    function timelineJump(layout, index, unit, direction) {
        var spans = (unit === 'day')
            ? layout.days.map(function (day) { return [day.from, day.to]; })
            : layout.runs.map(function (run) { return [run.turns[0].from, run.turns[run.turns.length - 1].to]; });
        var at = -1;

        spans.forEach(function (span, position) {
            if ((index >= span[0]) && (index <= span[1])) {
                at = position;
            }
        });

        if (at < 0) {
            return index;
        }

        if (direction > 0) {
            return (at + 1 < spans.length) ? spans[at + 1][0] : spans[at][1];
        }

        if (index > spans[at][0]) {
            return spans[at][0];
        }

        return at ? spans[at - 1][0] : index;
    }

    // The next zoom step in (direction 1) or out (-1) from a scale, within
    // the low and the top; a step that would stop just short of either end
    // goes to it. Pure.
    function timelineZoomStep(scale, direction, low, top) {
        var to = (direction > 0) ? scale * 1.5 : scale / 1.5;

        if ((direction > 0) && (to * 1.2 >= top)) {
            to = top;
        }

        if ((direction < 0) && (to / 1.2 <= low)) {
            to = low;
        }

        return Math.max(low, Math.min(top, to));
    }

    // The scale after a wheel's or a pinch's move of delta (up, or the
    // fingers apart, is negative), within the low and the top. Pure.
    function timelineZoomWheel(scale, delta, low, top) {
        return Math.max(low, Math.min(top, scale * Math.exp(-delta / 300)));
    }

    // The scroll that shows a stretch of the strip: as it is when the
    // stretch is in view, else the stretch in the middle, or its newest end
    // when it is wider than the view. Pure.
    function timelineFollow(scrollLeft, viewWidth, left, right) {
        if ((left >= scrollLeft) && (right <= scrollLeft + viewWidth)) {
            return scrollLeft;
        }

        if (right - left > viewWidth) {
            return Math.max(0, Math.round(right - viewWidth));
        }

        return Math.max(0, Math.round(((left + right) / 2) - (viewWidth / 2)));
    }

    // A wait in short words: 40 s, 12 min, 2 h 5 min, 3 d 4 h. Pure.
    function timelineWait(seconds, t) {
        var value = Math.max(0, Math.round(Number(seconds) || 0));

        if (value < 60) {
            return t('ctl_sec', value);
        }

        if (value < 3600) {
            return t('ctl_min', Math.round(value / 60));
        }

        if (value < 86400) {
            return t('ctl_hour', Math.floor(value / 3600), Math.floor((value % 3600) / 60));
        }

        return t('ctl_day', Math.floor(value / 86400), Math.floor((value % 86400) / 3600));
    }

    // The index of the message with this id or the nearest before it; -1
    // when every message is after it. Items are in the order of their ids.
    // Pure.
    function timelineIndexOf(items, id) {
        var low = 0;
        var high = items.length - 1;
        var found = -1;

        while (low <= high) {
            var middle = (low + high) >> 1;

            if (items[middle].id <= id) {
                found = middle;
                low = middle + 1;
            } else {
                high = middle - 1;
            }
        }

        return found;
    }

    // A writer's initials: the first letters of their first two words (a
    // user name's parts count as words), an application's "(application)"
    // left out. Pure.
    function timelineInitials(name) {
        var words = String(name || '').replace(/\([^)]*\)/g, ' ').trim().split(/[\s._-]+/).filter(Boolean);
        var letters = words.slice(0, 2).map(function (word) { return word.charAt(0); }).join('');

        return (letters || '?').toLocaleUpperCase();
    }

    // ── Time ──

    // The day of a time in the site's time zone, as 2026-11-21: one
    // formatter for every message, so a long strip lays out quickly.
    var dayFormat = null;

    function dayKey(at, h) {
        if (dayFormat === null) {
            try {
                dayFormat = new Intl.DateTimeFormat('en-CA', { timeZone: h.cfg.timezone || undefined, year: 'numeric', month: '2-digit', day: '2-digit' });
            } catch (error) {
                dayFormat = false;
            }
        }

        var date = new Date(at * 1000);

        return dayFormat ? dayFormat.format(date) : date.toDateString();
    }

    // The day before a day named as 2026-11-21; '' for any other name.
    function dayBefore(key) {
        var parts = String(key).split('-');

        if (parts.length !== 3) {
            return '';
        }

        return new Date(Date.UTC(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]) - 1)).toISOString().slice(0, 10);
    }

    function localized(at, h, shape) {
        var date = new Date(at * 1000);

        try {
            return date.toLocaleString(h.cfg.locale || undefined, Object.assign({ timeZone: h.cfg.timezone || undefined }, shape));
        } catch (error) {
            return date.toLocaleString(undefined, shape);
        }
    }

    // Today, yesterday or an earlier day ('today', 'yesterday', '').
    function relativeDay(at, h) {
        var today = dayKey(Date.now() / 1000, h);
        var day = dayKey(at, h);

        if (day === today) {
            return 'today';
        }

        return (day === dayBefore(today)) ? 'yesterday' : '';
    }

    // The time of a message on its card: "Today 12:08", "Yesterday 16:20",
    // else the date with the time, the year too in an earlier year.
    function when(at, h) {
        var time = localized(at, h, { hour: '2-digit', minute: '2-digit' });
        var near = relativeDay(at, h);

        if (near) {
            return h.t('ctl_' + near + '_at', time);
        }

        var shape = { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' };

        if (dayKey(at, h).slice(0, 4) !== dayKey(Date.now() / 1000, h).slice(0, 4)) {
            shape.year = 'numeric';
        }

        return localized(at, h, shape);
    }

    // A day's date over its column: "Today", "Yesterday" or the day and the
    // month, and the day number it shortens to (none for today and
    // yesterday).
    function dayWords(at, h) {
        var near = relativeDay(at, h);

        if (near) {
            return { full: h.t('ctl_' + near), short: '', now: near === 'today' };
        }

        return { full: localized(at, h, { day: 'numeric', month: 'short' }), short: localized(at, h, { day: 'numeric' }), now: false };
    }

    // ── Reading ──

    function fresh(channelId) {
        return (cache.channelId === channelId) && cache.data && ((Date.now() - cache.readAt) < FRESH);
    }

    function read(channelId) {
        var h = window.PGWsKit;

        if (cache.reading && (cache.channelId === channelId)) {
            return cache.reading;
        }

        if (cache.channelId !== channelId) {
            cache.data = null;
            cache.readAt = 0;
        }

        cache.channelId = channelId;
        cache.reading = h.api('ws_conversation_timeline', { channel_id: channelId }).then(function (data) {
            if (cache.channelId !== channelId) {
                return;
            }

            // What came since the strip was last drawn comes onto it live.
            var before = (cache.data && cache.data.items.length) ? cache.data.items[cache.data.items.length - 1].id : null;

            cache.reading = null;
            cache.data = data.timeline || null;
            cache.readAt = Date.now();
            cache.fresh = {};

            if ((before !== null) && cache.data) {
                cache.data.items.forEach(function (item) {
                    if (item.id > before) {
                        cache.fresh[item.id] = true;
                    }
                });
            }

            Object.keys(views).forEach(function (place) { draw(views[place]); });
            cache.fresh = {};

            if (cache.again) {
                cache.again = false;
                read(channelId);
            }
        }).catch(function () {
            cache.reading = null;
            cache.again = false;
            Object.keys(views).forEach(function (place) {
                if (views[place].channelId === channelId && !cache.data) {
                    views[place].holder.setAttribute('data-ws-ctl-state', 'failed');
                    views[place].track.setAttribute('aria-valuetext', window.PGWsKit.t('ctl_failed'));
                }
            });
        });

        return cache.reading;
    }

    // ── Drawing ──

    function toneOf(data, s) {
        var person = data.people[s];
        var tone = person ? Number(person.tone) : -1;

        return (tone < 0) ? 'sys' : String(tone % TONES);
    }

    // The words a screen reader says for a message: who, when, the first
    // words, its mark and what it carries.
    function spoken(data, index, h) {
        var item = data.items[index];
        var person = data.people[item.s] || { name: '' };
        var parts = [person.name, when(item.at, h), item.text || h.t('ctl_no_text')];

        if (item.mark) {
            parts.push(h.t('ctl_tool_' + item.mark));
        }

        (item.tools || []).forEach(function (tool) {
            parts.push([h.t('ctl_tool_' + tool.k), tool.text, tool.result].filter(Boolean).join(': '));
        });

        return parts.filter(Boolean).join(' · ');
    }

    // The viewer's zoom of a channel's strip, kept in the browser; null
    // when none is kept or the browser keeps nothing.
    function savedZoom(channelId) {
        try {
            var value = parseFloat(window.localStorage.getItem(ZOOM_KEY + channelId));

            return (value > 0) ? value : null;
        } catch (error) {
            return null;
        }
    }

    function saveZoom(channelId, scale) {
        try {
            window.localStorage.setItem(ZOOM_KEY + channelId, String(Math.round(scale * 1000) / 1000));
        } catch (error) {
            // Without storage the zoom lasts while the screen is open.
        }
    }

    // Moves the strip in its well; the fades at a cut end and the dates
    // follow (the port's scroll event).
    function scrollTo(view, left) {
        var most = Math.max(0, view.full - view.port.clientWidth);

        view.port.scrollLeft = Math.round(Math.max(0, Math.min(most, left)));
    }

    // Zooms the strip, keeping the message anchor pixels into the well
    // (the middle by default) where it was.
    function zoomTo(view, scale, anchor) {
        var width = view.port.clientWidth;

        scale = Math.max(view.low, Math.min(view.top, scale));

        if (!view.layout || !(width > 0) || (Math.abs(scale - view.scale) < 0.0005)) {
            return;
        }

        var at = (anchor === undefined) ? width / 2 : anchor;

        view.anchor = timelineAnchor(view.layout, view.port.scrollLeft + at);

        if (view.anchor) {
            view.anchor.at = at;
        }

        view.scale = scale;
        saveZoom(view.channelId, scale);
        draw(view);
    }

    // The wheel and a pinch zoom on every frame at most.
    function zoomSoon(view, scale, anchor) {
        view.wanted = { scale: scale, anchor: anchor };

        if (view.queued) {
            return;
        }

        view.queued = true;
        window.requestAnimationFrame(function () {
            var wanted = view.wanted;

            view.queued = false;
            view.wanted = null;

            if (wanted && views[view.place] === view) {
                zoomTo(view, wanted.scale, wanted.anchor);
            }
        });
    }

    // The fades at a cut end and the dates, as the strip stands in its
    // well.
    function placeDates(view) {
        var layout = view.layout;
        var scroll = view.port.scrollLeft;

        view.port.classList.toggle('is-cut-l', !!layout && (scroll > 0));
        view.port.classList.toggle('is-cut-r', !!layout && (scroll + view.port.clientWidth < layout.width - 1));

        if (!layout) {
            return;
        }

        layout.days.forEach(function (day) {
            if (day.node && !day.node.hidden) {
                day.node.style.left = Math.round(timelineDayLeft(day, scroll)) + 'px';
            }
        });
    }

    function draw(view) {
        var h = window.PGWsKit;
        var data = (cache.channelId === view.channelId) ? cache.data : null;
        var width = view.port.clientWidth;
        var oldScroll = view.port.scrollLeft;
        var oldFull = view.full;
        var atEnd = !oldFull || ((oldScroll + width) >= (oldFull - 2));

        h.clear(view.track);
        view.zoomOut.disabled = true;
        view.zoomIn.disabled = true;
        view.track.style.width = '';
        view.full = 0;

        if (view.legend) {
            view.legend.remove();
            view.legend = null;
        }

        view.layout = null;
        view.cursor = null;
        view.here = null;
        view.holder.removeAttribute('data-ws-ctl-state');
        placeDates(view);

        if (!data) {
            view.holder.setAttribute('data-ws-ctl-state', 'reading');
            return;
        }

        if (!data.items.length) {
            view.holder.setAttribute('data-ws-ctl-state', 'empty');
            view.track.setAttribute('aria-valuetext', h.t('ctl_empty'));
            view.track.removeAttribute('aria-valuenow');
            return;
        }

        if (!(width > 0)) {
            return;
        }

        // It opens at the opening scale, or the viewer's own zoom, within
        // the whole conversation and the top.
        var runs = timelineRuns(data.items, JOIN, BREAK, function (at) { return dayKey(at, h); });
        var range = timelineZoomRange(runs, width);

        view.low = range.low;
        view.top = range.top;
        view.scale = Math.max(view.low, Math.min(view.top, (view.scale === null) ? SCALE_OPEN : view.scale));

        var layout = timelineLayout(runs, view.scale);

        view.layout = layout;
        view.full = layout.width;
        view.track.style.width = layout.width + 'px';
        view.zoomOut.disabled = !(view.scale > view.low * 1.001);
        view.zoomIn.disabled = !(view.scale < view.top / 1.001);

        // A day: the hairline before it, its date over its column.
        layout.days.forEach(function (day) {
            var words = dayWords(data.items[day.from].at, h);
            var text = timelineDayText(words.full, words.short, day.end - day.first);

            if (day.line !== null) {
                var line = h.el('div', 'ws-ctl-day');

                line.style.left = day.line + 'px';
                view.track.appendChild(line);
            }

            day.node = h.el('span', 'ws-ctl-date' + (words.now ? ' is-now' : ''), text);
            day.node.hidden = !text;
            view.track.appendChild(day.node);
        });

        // A run: its turns side by side, rounded at its two ends; a turn
        // with a decision carries the diamond while the strip is not fully
        // zoomed out.
        layout.runs.forEach(function (run) {
            run.turns.forEach(function (turn) {
                var block = h.el('div', 'ws-ctl-turn' + (turn.first ? ' is-first' : '') + (turn.last ? ' is-last' : ''));

                block.setAttribute('data-tone', toneOf(data, turn.s));
                block.style.left = turn.left + 'px';
                block.style.width = turn.width + 'px';

                for (var i = turn.from; i <= turn.to; i++) {
                    if ((data.items[i].mark === 'decision') && (view.scale >= OUT_SCALE)) {
                        block.setAttribute('data-mark', 'decision');
                    }

                    // A turn a message just joined comes in.
                    if (cache.fresh[data.items[i].id]) {
                        block.classList.add('is-new');
                    }
                }

                turn.node = block;
                view.track.appendChild(block);
            });
        });

        // In Channel info the writers stand under the well as pills in
        // their colours; in the tabs row the card names them.
        if (view.place === 'info') {
            var names = h.el('div', 'ws-ctl-legend');

            data.people.forEach(function (person, index) {
                var chip = h.el('span', 'pg-pill', person.name);

                chip.setAttribute('data-tone', toneOf(data, index));
                chip.title = person.name;
                names.appendChild(chip);
            });

            if (data.cut) {
                names.appendChild(h.el('span', 'ws-ctl-legend-cut pg-text-meta', h.t('ctl_cut', data.items.length, data.total)));
            }

            view.legend = names;
            view.holder.appendChild(names);
        }

        view.cursor = h.el('div', 'ws-ctl-cursor');
        view.cursor.hidden = true;
        view.track.appendChild(view.cursor);
        view.here = h.el('div', 'ws-ctl-here');
        view.here.hidden = true;
        view.track.appendChild(view.here);

        // A zoom keeps the message under the pointer where it was; the
        // newest end stays in view as messages come; any other place keeps
        // its share of the strip.
        if (view.anchor) {
            var anchored = timelinePlace(layout, view.anchor.index);

            scrollTo(view, anchored ? (anchored.left + (view.anchor.share * (anchored.right - anchored.left)) - view.anchor.at) : oldScroll);
            view.anchor = null;
        } else if (atEnd) {
            scrollTo(view, layout.width);
        } else {
            scrollTo(view, oldFull ? (oldScroll * (layout.width / oldFull)) : layout.width);
        }

        placeDates(view);

        // A strip drawn for the first time follows the part on the screen.
        frame(view, !oldFull);

        var at = (view.at === null || view.at >= data.items.length) ? data.items.length - 1 : view.at;

        view.track.setAttribute('aria-valuemin', '1');
        view.track.setAttribute('aria-valuemax', String(data.items.length));
        view.track.setAttribute('aria-valuenow', String(at + 1));
        view.track.setAttribute('aria-valuetext', spoken(data, at, h));
        view.holder.title = data.cut ? h.t('ctl_cut', data.items.length, data.total) : '';

        if (view.showing && (view.at !== null)) {
            show(view, view.at, false, true);
        }
    }

    // The part of the conversation on the screen: an accent line under its
    // turns; a strip wider than its well follows it (follow) when the
    // conversation scrolls.
    function frame(view, follow) {
        var data = cache.data;
        var range = (view.hooks.visible && view.layout && view.here && data && (cache.channelId === view.channelId)) ? view.hooks.visible() : null;

        if (!view.here) {
            return;
        }

        if (!range) {
            view.here.hidden = true;
            return;
        }

        var first = timelineIndexOf(data.items, range[0]);
        var last = timelineIndexOf(data.items, range[1]);

        if (last < 0) {
            view.here.hidden = true;
            return;
        }

        first = Math.max(0, (first < 0) ? 0 : ((data.items[first].id < range[0]) ? first + 1 : first));

        var span = timelineSpan(view.layout, Math.min(first, last), last);

        if (!span) {
            view.here.hidden = true;
            return;
        }

        view.here.hidden = false;
        view.here.style.left = Math.round(span.left) + 'px';
        view.here.style.width = Math.max(4, Math.round(span.right - span.left)) + 'px';

        if (follow && (view.full > view.port.clientWidth)) {
            scrollTo(view, timelineFollow(view.port.scrollLeft, view.port.clientWidth, span.left - 4, span.right + 4));
        }
    }

    // ── The card ──

    function cardNode(h) {
        if (card && document.body.contains(card)) {
            return card;
        }

        card = h.el('div', 'ws-ctl-card');
        card.hidden = true;
        card.view = null;
        card.timer = 0;
        document.body.appendChild(card);

        // The card stays while the pointer is on it, and a moment after.
        card.addEventListener('pointerenter', function () {
            window.clearTimeout(card.timer);
        });
        card.addEventListener('pointerleave', function (event) {
            if ((event.pointerType !== 'touch') && card.view) {
                linger(card.view);
            }
        });

        // In the card Tab goes from row to row; Escape, or Shift and Tab on
        // the first row, goes back to the strip; Tab after the last goes on
        // past the well.
        card.addEventListener('keydown', function (event) {
            var view = card.view;

            if (!view) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                view.track.focus();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            var stops = Array.prototype.slice.call(card.querySelectorAll('button'));
            var at = stops.indexOf(document.activeElement);

            if (event.shiftKey && (at === 0)) {
                event.preventDefault();
                view.track.focus();
            } else if (!event.shiftKey && (at === stops.length - 1)) {
                event.preventDefault();
                hide(view);
                focusAfter(view);
            }
        });

        card.addEventListener('focusout', function (event) {
            var view = card.view;
            var to = event.relatedTarget;

            if (view && !(to && (card.contains(to) || (to === view.track)))) {
                hide(view);
            }
        });

        // A tap or a click anywhere else hides it.
        document.addEventListener('pointerdown', function (event) {
            var view = card.view;

            if (view && !card.hidden && !card.contains(event.target) && !view.port.contains(event.target)) {
                hide(view);
            }
        }, true);

        return card;
    }

    // The first thing past the strip that takes the focus: a zoom button,
    // else what follows the well on the screen.
    function focusAfter(view) {
        var selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        var next = Array.prototype.filter.call(document.querySelectorAll(selector), function (node) {
            return (node !== view.track) && !(card && card.contains(node)) && (node.offsetParent !== null)
                && !!(view.track.compareDocumentPosition(node) & 4);
        })[0];

        (next || view.track).focus();
    }

    function linger(view) {
        if (!card) {
            return;
        }

        window.clearTimeout(card.timer);
        card.timer = window.setTimeout(function () {
            hide(view);
        }, LINGER);
    }

    // One shortcut row: the kind's tile and word, the thing's name, what
    // came of it, a chevron; the whole row opens the thing.
    function cardRow(h, kind, word, name, result, onOpen) {
        var row = h.el('button', 'ws-ctl-row');
        var tile = h.el('span', 'pg-icon-tile pg-tone-' + (TOOL_TONES[kind] || 'gray'));

        row.type = 'button';
        tile.appendChild(h.icon(TOOL_ICONS[kind] || 'bi-three-dots'));
        row.appendChild(tile);

        if (word) {
            row.appendChild(h.el('em', 'ws-ctl-row-kind', word));
        }

        row.appendChild(h.el('span', 'ws-ctl-row-name', name));

        if (result) {
            row.appendChild(h.el('small', 'ws-ctl-row-result pg-tabular', result));
        }

        row.appendChild(h.icon('bi-chevron-right', 'ws-ctl-row-go'));
        row.addEventListener('click', function (event) {
            event.preventDefault();
            onOpen();
        });

        return row;
    }

    // The card of one message, under the well (over it where the screen has
    // no room below), centred on the message; reveal moves a strip wider
    // than its well to the message first (the keyboard); again draws it
    // anew after a read.
    function show(view, index, reveal, again) {
        var h = window.PGWsKit;
        var data = cache.data;

        if (!data || !view.layout || !data.items[index]) {
            return;
        }

        var node = cardNode(h);
        var place = timelinePlace(view.layout, index);
        var key = view.place + ':' + index + ':' + cache.readAt;

        window.clearTimeout(node.timer);

        if (reveal && place && (view.full > view.port.clientWidth)) {
            scrollTo(view, timelineFollow(view.port.scrollLeft, view.port.clientWidth, place.x - 24, place.x + 24));
        }

        if (!view.showing || (node.view !== view) || (node.key !== key) || again || node.hidden) {
            fill(view, node, index, h);
            node.key = key;
        }

        view.at = index;
        view.showing = true;
        node.view = view;

        // The turn pointed at comes forward, the others step back; in a
        // turn of several messages a playhead marks the one shown.
        Array.prototype.forEach.call(view.track.querySelectorAll('.ws-ctl-turn.is-on'), function (on) { on.classList.remove('is-on'); });
        view.track.classList.add('is-pointing');

        if (place && place.turn.node) {
            place.turn.node.classList.add('is-on');
        }

        if (view.cursor && place) {
            view.cursor.hidden = (place.turn.to === place.turn.from);
            view.cursor.style.left = Math.round(place.x) + 'px';
        }

        view.track.setAttribute('aria-valuenow', String(index + 1));
        view.track.setAttribute('aria-valuetext', spoken(data, index, h));

        node.hidden = false;

        var well = view.bar.getBoundingClientRect();
        var track = view.track.getBoundingClientRect();
        var x = track.left + (place ? place.x : track.width / 2);
        var wide = node.offsetWidth;
        var tall = node.offsetHeight;
        var left = Math.max(8, Math.min(window.innerWidth - wide - 8, x - wide / 2));
        var below = (well.bottom + 8 + tall) <= (window.innerHeight - 8);

        node.style.setProperty('--ws-ctl-from', below ? '-4px' : '4px');
        node.style.left = Math.round(left) + 'px';
        node.style.top = Math.round(below ? (well.bottom + 8) : (well.top - 8 - tall)) + 'px';
    }

    // What the card says of a message.
    function fill(view, node, index, h) {
        var data = cache.data;
        var item = data.items[index];
        var person = data.people[item.s] || { name: '' };
        var tools = item.tools || [];

        h.clear(node);
        node.setAttribute('data-tone', toneOf(data, item.s));

        // Who wrote it, on their initials in their colour, what they are
        // when not a person, and when.
        var head = h.el('div', 'ws-ctl-card-head');

        head.appendChild(h.el('span', 'pg-initials', timelineInitials(person.name)));
        head.appendChild(h.el('span', 'ws-ctl-card-name', person.name));

        if (person.kind && (person.kind !== 'person')) {
            head.appendChild(h.el('span', 'pg-pill', h.t('ctl_kind_' + person.kind)));
        }

        head.appendChild(h.el('span', 'ws-ctl-card-time pg-tabular', when(item.at, h)));
        node.appendChild(head);
        node.appendChild(h.el('p', 'ws-ctl-card-text' + (item.text ? '' : ' is-empty'), item.text || h.t('ctl_no_text')));

        // A decision or a note mark is a plain line: the message is the
        // decision.
        if (item.mark) {
            var mark = h.el('div', 'ws-ctl-card-mark');
            var tile = h.el('span', 'pg-icon-tile pg-tone-' + TOOL_TONES[item.mark === 'note' ? 'note' : 'decision']);

            tile.appendChild(h.icon(TOOL_ICONS[item.mark === 'note' ? 'note' : 'decision']));
            mark.appendChild(tile);
            mark.appendChild(h.el('span', '', [h.t('ctl_tool_' + item.mark), item.by ? h.t('ctl_marked_by', item.by) : ''].filter(Boolean).join(' · ')));
            node.appendChild(mark);
        }

        // What it carries, a shortcut row each; past four, one row for the
        // rest, which opens the message.
        if (tools.length) {
            var rows = h.el('div', 'ws-ctl-rows');
            var shown = (tools.length > ROWS) ? ROWS : tools.length;

            tools.slice(0, shown).forEach(function (tool) {
                rows.appendChild(cardRow(h, tool.k, h.t('ctl_tool_' + tool.k), tool.text || h.t('ctl_tool_' + tool.k), tool.result, function () {
                    openRow(view, index, tool);
                }));
            });

            if (tools.length > shown) {
                var more = cardRow(h, 'more', '', h.t('ctl_more', tools.length - shown), '', function () {
                    go(view, index);
                });

                more.classList.add('is-more');
                rows.appendChild(more);
            }

            node.appendChild(rows);
        }

        // The wait since the message before, and the way into the message.
        var foot = h.el('div', 'ws-ctl-card-foot');
        var open = h.el('button', 'ws-ctl-card-go', h.t('ctl_open'));

        foot.appendChild(h.el('span', 'ws-ctl-card-wait', index ? h.t('ctl_after', timelineWait(item.at - data.items[index - 1].at, h.t)) : ''));
        open.type = 'button';
        open.addEventListener('click', function () { go(view, index); });
        foot.appendChild(open);
        node.appendChild(foot);
    }

    function hide(view) {
        view.showing = false;

        if (card) {
            window.clearTimeout(card.timer);

            if (card.view === view) {
                card.hidden = true;
                card.view = null;
            }
        }

        if (view.cursor) {
            view.cursor.hidden = true;
        }

        view.track.classList.remove('is-pointing');
        Array.prototype.forEach.call(view.track.querySelectorAll('.ws-ctl-turn.is-on'), function (on) { on.classList.remove('is-on'); });
    }

    function go(view, index) {
        var data = cache.data;

        if (!data || !data.items[index]) {
            return;
        }

        hide(view);
        view.hooks.go(data.items[index].id);
    }

    // A shortcut row: the thing itself where it has a window of its own,
    // the message otherwise (workspace.js decides by the row's go).
    function openRow(view, index, tool) {
        var data = cache.data;

        if (!data || !data.items[index]) {
            return;
        }

        hide(view);

        if (view.hooks.open) {
            view.hooks.open(tool.go || 'message', tool, data.items[index].id);
        } else {
            view.hooks.go(data.items[index].id);
        }
    }

    // The message under the pointer; null off the strip.
    function pick(view, event) {
        if (!view.layout) {
            return null;
        }

        var box = view.track.getBoundingClientRect();
        var found = timelinePick(view.layout, event.clientX - box.left);

        return (found && (found.away <= 16)) ? found.index : null;
    }

    // ── Mounting ──

    function unmount(place) {
        var view = views[place];

        if (!view) {
            return;
        }

        delete views[place];

        if (view.observer) {
            view.observer.disconnect();
        }

        if (view.unwatch) {
            view.unwatch();
        }

        hide(view);

        if ((place === 'head') && view.hooks.root) {
            view.hooks.root.classList.remove('ws-ctl-narrow');
        }
    }

    /**
     * Draws the strip of a channel's conversation into a holder.
     *
     * @param {HTMLElement} holder  where the strip goes
     * @param {object}      channel the channel as the screen holds it
     * @param {string}      place   'head' (the tabs row) or 'info' (Channel info)
     * @param {object}      hooks   go(messageId); open(go, row, messageId) for a
     *                              card's shortcut row; visible() ([first, last]
     *                              message id on the screen, or null); for 'head'
     *                              root (the .ws-app, marked ws-ctl-narrow while
     *                              the row has no room) and pane() (whose scroll
     *                              the line under the screen's part follows)
     */
    function mount(holder, channel, place, hooks) {
        var h = window.PGWsKit;

        unmount(place);

        if (!h || !holder || !channel) {
            return;
        }

        var bar = h.el('div', 'ws-ctl-bar');
        var port = h.el('div', 'ws-ctl-port');
        var track = h.el('div', 'ws-ctl-track');
        var zoomBox = h.el('div', 'ws-ctl-zoom');
        var zoomOut = h.el('button', 'pg-icon-button pg-icon-button-sm');
        var zoomIn = h.el('button', 'pg-icon-button pg-icon-button-sm');
        var view = {
            holder: holder, bar: bar, port: port, track: track, zoomOut: zoomOut, zoomIn: zoomIn, channelId: channel.id, place: place,
            hooks: hooks || {}, layout: null, at: null, showing: false, width: -1, scale: savedZoom(channel.id), low: SCALE_OPEN,
            top: SCALE_TOP, full: 0, anchor: null, queued: false, wanted: null, cursor: null, here: null, legend: null
        };

        holder.classList.add('ws-ctl');
        holder.setAttribute('data-place', place);
        track.tabIndex = 0;
        track.setAttribute('role', 'slider');
        track.setAttribute('aria-label', h.t('ctl_label'));
        track.setAttribute('aria-orientation', 'horizontal');
        port.appendChild(track);
        bar.appendChild(port);

        // Zoom out and in, inside the well after a hairline.
        [[zoomOut, 'bi-zoom-out', 'ctl_zoom_out', -1], [zoomIn, 'bi-zoom-in', 'ctl_zoom_in', 1]].forEach(function (one) {
            one[0].type = 'button';
            one[0].disabled = true;
            one[0].title = h.t(one[2]);
            one[0].setAttribute('aria-label', h.t(one[2]));
            one[0].appendChild(h.icon(one[1]));
            one[0].addEventListener('click', function () {
                zoomTo(view, timelineZoomStep(view.scale || SCALE_OPEN, one[3], view.low, view.top));
            });
            zoomBox.appendChild(one[0]);
        });

        bar.appendChild(zoomBox);
        holder.appendChild(bar);
        views[place] = view;

        port.addEventListener('scroll', function () {
            placeDates(view);
        }, { passive: true });

        var drag = null;
        var touches = {};
        var pinch = null;
        var pinched = false;

        function touchList() {
            return Object.keys(touches).map(function (id) { return touches[id]; });
        }

        function spread() {
            var list = touchList();

            return {
                distance: Math.max(1, Math.abs(list[0].x - list[1].x)),
                middle: ((list[0].x + list[1].x) / 2) - port.getBoundingClientRect().left
            };
        }

        function capture(event) {
            try {
                port.setPointerCapture(event.pointerId);
            } catch (error) {
                // Without capture the drag ends at the well's edge.
            }
        }

        // A mouse shows the card of the turn under it; a drag moves a strip
        // wider than its well along and opens nothing.
        port.addEventListener('pointermove', function (event) {
            if (event.pointerType === 'touch') {
                if (touches[event.pointerId]) {
                    touches[event.pointerId].x = event.clientX;
                }

                // Two fingers zoom around the point between them.
                if (pinch) {
                    var now = spread();

                    zoomSoon(view, pinch.scale * (now.distance / pinch.distance), now.middle);
                    return;
                }
            }

            if (drag) {
                var moved = event.clientX - drag.x;

                if (drag.moved || (Math.abs(moved) > ((event.pointerType === 'touch') ? 6 : 4))) {
                    drag.moved = true;
                    scrollTo(view, drag.scroll - moved);
                    hide(view);
                }

                return;
            }

            if (event.pointerType === 'touch') {
                return;
            }

            var index = pick(view, event);

            if (index !== null) {
                show(view, index);
            } else if (view.showing) {
                linger(view);
            }
        });

        port.addEventListener('pointerleave', function (event) {
            if ((event.pointerType !== 'touch') && !drag && view.showing) {
                linger(view);
            }
        });

        port.addEventListener('pointerdown', function (event) {
            if (event.pointerType === 'touch') {
                touches[event.pointerId] = { x: event.clientX };

                if (touchList().length === 2) {
                    drag = null;
                    hide(view);
                    pinch = { distance: spread().distance, scale: view.scale || SCALE_OPEN };
                    pinched = true;
                    return;
                }
            } else if (event.button !== 0) {
                return;
            }

            drag = { x: event.clientX, scroll: port.scrollLeft, moved: false };
            capture(event);
        });

        // A click opens the message clicked; a tap shows its card, and a tap
        // on the card's row or its "Open the message" opens.
        port.addEventListener('pointerup', function (event) {
            var dragged = drag && drag.moved;
            var index = pick(view, event);

            drag = null;

            if (event.pointerType === 'touch') {
                var wasPinch = pinched;

                delete touches[event.pointerId];
                pinch = null;
                pinched = pinched && (touchList().length > 0);

                if (!wasPinch && !dragged && (index !== null)) {
                    show(view, index);
                }

                return;
            }

            if (!dragged && (event.button === 0) && (index !== null)) {
                go(view, index);
            }
        });

        port.addEventListener('pointercancel', function () {
            drag = null;
            pinch = null;
            pinched = false;
            touches = {};
            hide(view);
        });

        // Ctrl or Cmd with the wheel (a pinch on a touchpad) zooms around
        // the pointer; Shift with the wheel, or a sideways swipe, moves
        // along. The plain wheel is left to the page.
        bar.addEventListener('wheel', function (event) {
            var unit = (event.deltaMode === 1) ? 16 : ((event.deltaMode === 2) ? port.clientWidth : 1);
            var down = event.deltaY * unit;
            var side = event.deltaX * unit;

            if (event.ctrlKey || event.metaKey) {
                event.preventDefault();

                if (view.layout) {
                    zoomSoon(view, timelineZoomWheel(view.wanted ? view.wanted.scale : view.scale, down, view.low, view.top), event.clientX - port.getBoundingClientRect().left);
                }

                return;
            }

            var along = event.shiftKey ? (down || side) : ((Math.abs(side) > Math.abs(down)) ? side : 0);

            if (along && (view.full > port.clientWidth)) {
                event.preventDefault();
                scrollTo(view, port.scrollLeft + along);
                hide(view);
            }
        }, { passive: false });

        // From the keyboard: the arrows step a message, with Shift a run,
        // Page Up and Down a day, Home and End to the ends; Enter or Space
        // opens it; + and - zoom around the message shown, 0 shows the
        // whole conversation; Tab goes into the card's rows.
        track.addEventListener('keydown', function (event) {
            var data = cache.data;

            if (!data || !data.items.length || !view.layout) {
                return;
            }

            var last = data.items.length - 1;
            var at = (view.at === null) ? last : view.at;
            var to = null;
            var plain = !event.ctrlKey && !event.metaKey && !event.altKey;
            var shown = view.showing ? timelinePlace(view.layout, at) : null;

            if (plain && ((event.key === '+') || (event.key === '=') || (event.key === '-') || (event.key === '0'))) {
                event.preventDefault();
                zoomTo(view, (event.key === '0') ? view.low : timelineZoomStep(view.scale, (event.key === '-') ? -1 : 1, view.low, view.top), shown ? shown.x - port.scrollLeft : undefined);
                return;
            }

            if ((event.key === 'Tab') && !event.shiftKey && view.showing && card && (card.view === view)) {
                var first = card.querySelector('button');

                if (first) {
                    event.preventDefault();
                    first.focus();
                }

                return;
            }

            if ((event.key === 'ArrowLeft') || (event.key === 'ArrowDown')) {
                to = event.shiftKey ? timelineJump(view.layout, at, 'run', -1) : Math.max(0, at - 1);
            } else if ((event.key === 'ArrowRight') || (event.key === 'ArrowUp')) {
                to = event.shiftKey ? timelineJump(view.layout, at, 'run', 1) : Math.min(last, at + 1);
            } else if ((event.key === 'PageUp') || (event.key === 'PageDown')) {
                to = timelineJump(view.layout, at, 'day', (event.key === 'PageUp') ? -1 : 1);
            } else if (event.key === 'Home') {
                to = 0;
            } else if (event.key === 'End') {
                to = last;
            } else if ((event.key === 'Enter') || (event.key === ' ')) {
                event.preventDefault();
                go(view, at);
                return;
            } else if (event.key === 'Escape') {
                if (view.showing) {
                    event.stopPropagation();
                    hide(view);
                }

                return;
            }

            if (to !== null) {
                event.preventDefault();
                show(view, to, true);
            }
        });

        track.addEventListener('focus', function () {
            if (track.matches(':focus-visible') && cache.data && cache.data.items.length) {
                show(view, (view.at === null) ? cache.data.items.length - 1 : view.at, true);
            }
        });

        track.addEventListener('blur', function (event) {
            if (!(event.relatedTarget && card && card.contains(event.relatedTarget))) {
                hide(view);
            }
        });

        // The strip is laid out again when its width changes; in the tabs
        // row too little width hands it to Channel info. The holder's own
        // width is measured, never the well's, so the switch cannot swing.
        if (window.ResizeObserver) {
            view.observer = new window.ResizeObserver(function (entries) {
                var width = Math.round(entries[0].contentRect.width);

                if (width === view.width) {
                    return;
                }

                view.width = width;

                if ((place === 'head') && view.hooks.root) {
                    view.hooks.root.classList.toggle('ws-ctl-narrow', width < NARROW_PX);
                }

                draw(view);
            });
            view.observer.observe(holder);
        }

        // The line under the screen's part follows the conversation as it
        // scrolls, on every strip, and a strip wider than its well follows
        // the line.
        var pane = (place === 'head' && view.hooks.pane) ? view.hooks.pane() : null;

        if (pane) {
            var queued = false;
            var onScroll = function () {
                if (queued) {
                    return;
                }

                queued = true;
                window.requestAnimationFrame(function () {
                    queued = false;
                    scrolled();
                });
            };

            pane.addEventListener('scroll', onScroll, { passive: true });
            view.unwatch = function () { pane.removeEventListener('scroll', onScroll); };
        }

        draw(view);

        if (!fresh(channel.id)) {
            read(channel.id);
        }
    }

    /**
     * The channel's sync saw a message, a vote or an answer change: the
     * strip reads again, at once for a new message, which comes onto it
     * live; for a vote or an answer at most once in eight seconds.
     *
     * @param {number}  channelId
     * @param {boolean} written a new message came
     */
    function changed(channelId, written) {
        if (cache.channelId !== channelId) {
            return;
        }

        if (written) {
            window.clearTimeout(cache.braked);
            cache.braked = 0;

            // A read on its way may have left the message out: one more
            // follows it.
            if (cache.reading) {
                cache.again = true;
                return;
            }

            read(channelId);
            return;
        }

        if (cache.braked) {
            return;
        }

        var wait = cache.readAt ? Math.max(0, BRAKE - (Date.now() - cache.readAt)) : 0;

        if (!wait) {
            read(channelId);
            return;
        }

        cache.braked = window.setTimeout(function () {
            cache.braked = 0;

            if (cache.channelId === channelId) {
                read(channelId);
            }
        }, wait);
    }

    /**
     * The channel's conversation was cleared: what the strip holds of it is
     * dropped, so the next mount reads the new one.
     *
     * @param {number} channelId
     */
    function forget(channelId) {
        if (cache.channelId === channelId) {
            window.clearTimeout(cache.braked);
            cache.braked = 0;
            cache.data = null;
            cache.readAt = 0;
            cache.reading = null;
            cache.again = false;
        }
    }

    /**
     * The messages on the screen moved (drawn, loaded, appended or
     * scrolled): the line under the screen's part follows on every strip,
     * and a strip wider than its well follows the line.
     */
    function scrolled() {
        Object.keys(views).forEach(function (place) {
            frame(views[place], true);
        });
    }

    window.PGWsConversationTimeline = {
        mount: mount,
        unmount: unmount,
        changed: changed,
        forget: forget,
        scrolled: scrolled
    };
})();
