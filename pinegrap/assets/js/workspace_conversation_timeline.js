/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the conversation timeline: the channel's current conversation
 * as one strip, in the tabs row where it has the room and at the foot of
 * Channel info where it does not. Each turn (one writer's messages close
 * together) is a block in that writer's colour; the waits between turns
 * are the strip's grey, and a wait of half an hour or more splits the strip
 * into runs. Pointing at the strip shows a card of the message under the
 * pointer: who wrote it, when, its first words, the channel tool it carries
 * (a decision, a poll, an approval, a task...) and what came of it; moving
 * along the strip changes the card. A click, Enter or a lifted finger opens
 * the message in the conversation. In the tabs row a frame shows the part
 * of the conversation on the screen.
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

    // A wait this long, in seconds, splits the strip into two runs; one
    // writer's messages this close together are one turn.
    var BREAK = 1800;
    var JOIN = 120;

    // The space between two runs, the grey at each end of a run, the
    // narrowest turn and the least width the tabs row must leave the
    // strip, in pixels.
    var GAP_PX = 10;
    var PAD_PX = 3;
    var MIN_PX = 6;
    var NARROW_PX = 220;

    // The least time between two reads asked by the sync, and how long a
    // read is drawn again without asking, in milliseconds.
    var BRAKE = 8000;
    var FRESH = 60000;

    // The tools a card names, with their signs.
    var TOOL_ICONS = {
        decision: 'bi-patch-check', note: 'bi-sticky', task: 'bi-check2-square', file: 'bi-paperclip',
        picture: 'bi-image', voice: 'bi-mic', poll: 'bi-bar-chart-line', question: 'bi-robot',
        approval: 'bi-patch-question', ack: 'bi-eye', status: 'bi-activity', checkin: 'bi-person-raised-hand',
        agent: 'bi-stars'
    };

    // The tone of each tool's tile, and of the pill that tells what came of
    // it.
    var TOOL_TONES = {
        decision: 'amber', note: 'gray', task: 'blue', file: 'gray', picture: 'gray', voice: 'gray',
        poll: 'violet', question: 'violet', approval: 'teal', ack: 'blue', status: 'teal', checkin: 'blue',
        agent: 'violet'
    };
    var STATE_TONES = { ok: 'teal', bad: 'rose', open: 'amber' };

    // The colours a writer is drawn in, by the order they first wrote.
    var TONES = 8;

    var cache = { channelId: 0, data: null, readAt: 0, reading: null, braked: 0, fresh: {}, again: false };
    var views = {};
    var card = null;

    // ── Pure parts (tests/workspace_conversation_timeline_js_test.php) ──

    // The conversation in runs and turns: a run ends where a wait of BREAK
    // seconds or more begins; a turn is one writer's messages, each within
    // JOIN seconds of the one before. Indexes point into items. Pure.
    function timelineRuns(items, join, brk) {
        var runs = [];
        var run = null;
        var turn = null;

        join = (join === undefined) ? JOIN : join;
        brk = (brk === undefined) ? BREAK : brk;

        (items || []).forEach(function (item, index) {
            var before = index ? items[index - 1] : null;
            var wait = before ? Math.max(0, item.at - before.at) : 0;

            if (!run || (wait >= brk)) {
                run = { turns: [] };
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

    // The strip laid out in pixels: every run and turn with its left and
    // width. A turn weighs the square root of its length plus four minutes
    // a message and a wait the square root of its seconds, so a long wait
    // does not crowd the turns out and a turn reads as one beside a wait
    // of a quarter hour; a turn is never narrower than the least width,
    // which shrinks when there are more turns than the strip holds. Pure.
    function timelineLayout(runs, width, gap, least, pad) {
        var count = 0;
        var weights = [];

        gap = (gap === undefined) ? GAP_PX : gap;
        least = (least === undefined) ? MIN_PX : least;
        pad = (pad === undefined) ? PAD_PX : pad;

        runs.forEach(function (run) {
            run.turns.forEach(function (turn, index) {
                count += 1;
                weights.push({ turn: Math.sqrt(Math.max(0, turn.end - turn.start) + (240 * (turn.to - turn.from + 1))), wait: index ? Math.sqrt(turn.wait) : 0 });
            });
        });

        var room = Math.max(0, width - (gap * Math.max(0, runs.length - 1)) - (2 * pad * runs.length));

        if (!count || !(room > 0)) {
            return { width: width, runs: [] };
        }

        // Turns side by side may take at most seven tenths of the room.
        least = Math.min(least, Math.max(1, (room * 0.7) / count));

        function used(scale) {
            return weights.reduce(function (sum, weight) {
                return sum + Math.max(least, weight.turn * scale) + (weight.wait * scale);
            }, 0);
        }

        var low = 0;
        var high = 1;

        while ((used(high) < room) && (high < 1e9)) {
            high *= 2;
        }

        for (var step = 0; step < 50; step++) {
            var middle = (low + high) / 2;

            if (used(middle) > room) {
                high = middle;
            } else {
                low = middle;
            }
        }

        var scale = low;
        var x = 0;
        var at = 0;
        var out = [];

        runs.forEach(function (run, runIndex) {
            var left = x;
            var turns = [];

            x += pad;

            run.turns.forEach(function (turn) {
                var weight = weights[at];

                at += 1;
                x += weight.wait * scale;

                var size = Math.max(least, weight.turn * scale);

                turns.push({ left: x, width: size, from: turn.from, to: turn.to, s: turn.s });
                x += size;
            });

            x += pad;
            out.push({ left: left, width: x - left, turns: turns });

            if (runIndex < runs.length - 1) {
                x += gap;
            }
        });

        // What the halving left over goes to the last run.
        if (out.length) {
            out[out.length - 1].width = Math.max(out[out.length - 1].width, width - out[out.length - 1].left);
        }

        return { width: width, runs: out };
    }

    // The message under a point of the strip: in the turn the point is on
    // or the nearest one, the message at the point's share of the turn.
    // null on an empty strip. Pure.
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

        return { turn: best, index: index };
    }

    // Where a message stands on the strip: the middle of its share of its
    // turn; null for a message not on it. Pure.
    function timelinePlace(layout, index) {
        var found = null;

        layout.runs.forEach(function (run) {
            run.turns.forEach(function (turn) {
                if ((index >= turn.from) && (index <= turn.to)) {
                    var count = turn.to - turn.from + 1;

                    found = { turn: turn, x: turn.left + ((index - turn.from + 0.5) / count) * turn.width };
                }
            });
        });

        return found;
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

    function dayOf(date, h) {
        try {
            return date.toLocaleDateString('en-CA', { timeZone: h.cfg.timezone || undefined });
        } catch (error) {
            return date.toDateString();
        }
    }

    // The time of a message on its card: the time alone today, the date
    // with it on an earlier day, the year too in an earlier year.
    function when(at, h) {
        var date = new Date(at * 1000);
        var now = new Date();
        var shape = { hour: '2-digit', minute: '2-digit' };

        if (dayOf(date, h) === dayOf(now, h)) {
            shape.second = '2-digit';
        } else {
            shape.day = 'numeric';
            shape.month = 'short';

            if (dayOf(date, h).slice(0, 4) !== dayOf(now, h).slice(0, 4)) {
                shape.year = 'numeric';
            }
        }

        try {
            return date.toLocaleString(h.cfg.locale || undefined, Object.assign({ timeZone: h.cfg.timezone || undefined }, shape));
        } catch (error) {
            return date.toLocaleString(undefined, shape);
        }
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
    // words and the tools.
    function spoken(data, index, h) {
        var item = data.items[index];
        var person = data.people[item.s] || { name: '' };
        var parts = [person.name, when(item.at, h), item.text || h.t('ctl_no_text')];

        (item.tools || []).forEach(function (tool) {
            parts.push([h.t('ctl_tool_' + tool.k), tool.text, tool.result].filter(Boolean).join(': '));
        });

        return parts.filter(Boolean).join(' · ');
    }

    function draw(view) {
        var h = window.PGWsKit;
        var data = (cache.channelId === view.channelId) ? cache.data : null;
        var width = view.track.clientWidth;

        h.clear(view.track);

        if (view.legend) {
            view.legend.remove();
            view.legend = null;
        }

        view.layout = null;
        view.cursor = null;
        view.frame = null;
        view.holder.removeAttribute('data-ws-ctl-state');

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

        var layout = timelineLayout(timelineRuns(data.items), width);

        view.layout = layout;

        layout.runs.forEach(function (run) {
            var runNode = h.el('div', 'ws-ctl-run');

            runNode.style.left = run.left + 'px';
            runNode.style.width = run.width + 'px';
            view.track.appendChild(runNode);

            run.turns.forEach(function (turn) {
                var block = h.el('div', 'ws-ctl-turn');
                var marked = false;

                block.setAttribute('data-tone', toneOf(data, turn.s));
                block.style.left = (turn.left - run.left) + 'px';
                block.style.width = Math.max(1, turn.width - ((turn.width > 3) ? 1 : 0)) + 'px';

                // A decision in the turn gets a mark on its top; any other
                // tool a smaller one.
                for (var i = turn.from; i <= turn.to; i++) {
                    (data.items[i].tools || []).forEach(function (tool) {
                        if (tool.k === 'decision') {
                            marked = 'decision';
                        } else if (!marked) {
                            marked = 'tool';
                        }
                    });
                }

                if (marked) {
                    block.setAttribute('data-mark', marked);
                }

                // A turn a message just joined comes in.
                for (var j = turn.from; j <= turn.to; j++) {
                    if (cache.fresh[data.items[j].id]) {
                        block.classList.add('is-new');
                        break;
                    }
                }

                turn.node = block;
                runNode.appendChild(block);
            });
        });

        // In Channel info the strip's first and last time stand under it
        // and the writers are named as pills in their colours; in the tabs
        // row the card names them.
        if (view.place === 'info') {
            var legend = h.el('div', 'ws-ctl-foot');
            var ends = h.el('div', 'ws-ctl-ends pg-text-meta pg-tabular');
            var names = h.el('div', 'ws-ctl-legend');

            ends.appendChild(h.el('span', '', when(data.items[0].at, h)));
            ends.appendChild(h.el('span', '', when(data.items[data.items.length - 1].at, h)));
            legend.appendChild(ends);

            data.people.forEach(function (person, index) {
                var chip = h.el('span', 'pg-pill', person.name);

                chip.setAttribute('data-tone', toneOf(data, index));
                chip.title = person.name;
                names.appendChild(chip);
            });

            if (data.cut) {
                names.appendChild(h.el('span', 'ws-ctl-legend-cut pg-text-meta', h.t('ctl_cut', data.items.length, data.total)));
            }

            legend.appendChild(names);
            view.legend = legend;
            view.holder.appendChild(legend);
        }

        view.cursor = h.el('div', 'ws-ctl-cursor');
        view.cursor.hidden = true;
        view.track.appendChild(view.cursor);

        if (view.place === 'head') {
            view.frame = h.el('div', 'ws-ctl-frame');
            view.frame.hidden = true;
            view.track.appendChild(view.frame);
            frame(view);
        }

        var at = (view.at === null || view.at >= data.items.length) ? data.items.length - 1 : view.at;

        view.track.setAttribute('aria-valuemin', '1');
        view.track.setAttribute('aria-valuemax', String(data.items.length));
        view.track.setAttribute('aria-valuenow', String(at + 1));
        view.track.setAttribute('aria-valuetext', spoken(data, at, h));
        view.holder.title = data.cut ? h.t('ctl_cut', data.items.length, data.total) : '';

        if (view.showing && (view.at !== null)) {
            show(view, view.at);
        }
    }

    // The part of the conversation on the screen, framed on the strip.
    function frame(view) {
        var data = cache.data;
        var range = (view.hooks.visible && view.layout && data && (cache.channelId === view.channelId)) ? view.hooks.visible() : null;

        if (!view.frame) {
            return;
        }

        if (!range) {
            view.frame.hidden = true;
            return;
        }

        var first = timelineIndexOf(data.items, range[0]);
        var last = timelineIndexOf(data.items, range[1]);

        if (last < 0) {
            view.frame.hidden = true;
            return;
        }

        first = Math.max(0, (first < 0) ? 0 : ((data.items[first].id < range[0]) ? first + 1 : first));

        var from = timelinePlace(view.layout, Math.min(first, last));
        var to = timelinePlace(view.layout, last);

        if (!from || !to) {
            view.frame.hidden = true;
            return;
        }

        var left = Math.min(from.turn.left, to.turn.left);
        var right = Math.max(from.turn.left + from.turn.width, to.turn.left + to.turn.width);

        view.frame.hidden = false;
        view.frame.style.left = (left - 2) + 'px';
        view.frame.style.width = Math.max(6, right - left + 4) + 'px';
    }

    function cardNode(h) {
        if (card && document.body.contains(card)) {
            return card;
        }

        card = h.el('div', 'ws-ctl-card');
        card.setAttribute('aria-hidden', 'true');
        card.hidden = true;
        document.body.appendChild(card);

        return card;
    }

    // The card of one message, under the strip (over it where the screen
    // has no room below), its tip at the message.
    function show(view, index) {
        var h = window.PGWsKit;
        var data = cache.data;

        if (!data || !view.layout || !data.items[index]) {
            return;
        }

        var item = data.items[index];
        var person = data.people[item.s] || { name: '' };
        var place = timelinePlace(view.layout, index);
        var node = h.clear(cardNode(h));

        view.at = index;
        view.showing = true;
        node.setAttribute('data-tone', toneOf(data, item.s));

        // Who wrote it, on their initials in their colour, and when.
        var head = h.el('div', 'ws-ctl-card-head');
        var who = h.el('div', 'ws-ctl-card-who');

        head.appendChild(h.el('span', 'pg-initials', timelineInitials(person.name)));
        who.appendChild(h.el('span', 'ws-ctl-card-name', person.name));

        if (person.kind && (person.kind !== 'person')) {
            who.appendChild(h.el('span', 'ws-ctl-card-kind pg-text-meta', h.t('ctl_kind_' + person.kind)));
        }

        head.appendChild(who);
        head.appendChild(h.el('span', 'ws-ctl-card-time pg-text-meta pg-tabular', when(item.at, h)));
        node.appendChild(head);
        node.appendChild(h.el('div', 'ws-ctl-card-text' + (item.text ? '' : ' is-empty'), item.text || h.t('ctl_no_text')));

        // Its tools: the tool's tile, its name and subject, and what came
        // of it as a pill.
        var tools = item.tools || [];

        if (tools.length) {
            var list = h.el('div', 'ws-ctl-card-tools');

            tools.slice(0, 2).forEach(function (tool) {
                var line = h.el('div', 'ws-ctl-tool');
                var tile = h.el('span', 'pg-icon-tile pg-tone-' + (TOOL_TONES[tool.k] || 'gray'));
                var words = h.el('span', 'ws-ctl-tool-words');

                tile.appendChild(h.icon(TOOL_ICONS[tool.k] || 'bi-tools'));
                line.appendChild(tile);
                words.appendChild(h.el('span', 'ws-ctl-tool-name', h.t('ctl_tool_' + tool.k)));

                if (tool.text) {
                    words.appendChild(h.el('span', 'ws-ctl-tool-text pg-text-meta', tool.text));
                }

                line.appendChild(words);

                if (tool.result) {
                    line.appendChild(h.el('span', 'pg-pill pg-tone-' + (STATE_TONES[tool.state] || 'gray'), tool.result));
                }

                list.appendChild(line);
            });

            if (tools.length > 2) {
                list.appendChild(h.el('div', 'ws-ctl-tool-more pg-text-meta', h.t('ctl_more_tools', tools.length - 2)));
            }

            node.appendChild(list);
        }

        var foot = h.el('div', 'ws-ctl-card-foot pg-text-meta');

        if (index > 0) {
            foot.appendChild(h.icon('bi-hourglass-split'));
            foot.appendChild(h.el('span', 'ws-ctl-card-wait', h.t('ctl_after', timelineWait(item.at - data.items[index - 1].at, h.t))));
        }

        var open = h.el('span', 'ws-ctl-card-go', h.t('ctl_open'));

        open.insertBefore(h.icon('bi-arrow-return-left'), open.firstChild);
        foot.appendChild(open);
        node.appendChild(foot);

        // The turn under the pointer comes forward, the others step back;
        // in a turn of several messages a playhead marks the one shown.
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

        var box = view.track.getBoundingClientRect();
        var x = box.left + (place ? place.x : box.width / 2);
        var wide = node.offsetWidth;
        var tall = node.offsetHeight;
        var left = Math.max(8, Math.min(window.innerWidth - wide - 8, x - wide / 2));
        var below = (box.bottom + 8 + tall) <= (window.innerHeight - 8);

        node.classList.toggle('is-above', !below);
        node.style.setProperty('--ws-ctl-from', below ? '-4px' : '4px');
        node.style.left = Math.round(left) + 'px';
        node.style.top = Math.round(below ? (box.bottom + 8) : (box.top - 8 - tall)) + 'px';
    }

    function hide(view) {
        view.showing = false;

        if (card) {
            card.hidden = true;
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

    function pick(view, event) {
        if (!view.layout) {
            return null;
        }

        var box = view.track.getBoundingClientRect();
        var found = timelinePick(view.layout, event.clientX - box.left);

        return found ? found.index : null;
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

        if (view.showing) {
            hide(view);
        }

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
     * @param {object}      hooks   go(messageId); for 'head' root (the .ws-app,
     *                              marked ws-ctl-narrow while the row has no
     *                              room), pane() and visible() ([first, last]
     *                              message id on the screen, or null)
     */
    function mount(holder, channel, place, hooks) {
        var h = window.PGWsKit;

        unmount(place);

        if (!h || !holder || !channel) {
            return;
        }

        var track = h.el('div', 'ws-ctl-track');
        var view = { holder: holder, track: track, channelId: channel.id, place: place, hooks: hooks || {}, layout: null, at: null, showing: false, width: -1 };

        holder.classList.add('ws-ctl');
        holder.setAttribute('data-place', place);
        track.tabIndex = 0;
        track.setAttribute('role', 'slider');
        track.setAttribute('aria-label', h.t('ctl_label'));
        track.setAttribute('aria-orientation', 'horizontal');
        holder.appendChild(track);
        views[place] = view;

        var dragging = false;

        track.addEventListener('pointermove', function (event) {
            if ((event.pointerType === 'touch') && !dragging) {
                return;
            }

            var index = pick(view, event);

            if (index !== null) {
                show(view, index);
            }
        });

        track.addEventListener('pointerleave', function (event) {
            if (event.pointerType !== 'touch') {
                hide(view);
            }
        });

        // A finger scrubs along the strip and opens the message it is
        // lifted on; a mouse opens the one clicked.
        track.addEventListener('pointerdown', function (event) {
            if (event.pointerType !== 'touch') {
                return;
            }

            dragging = true;

            try {
                track.setPointerCapture(event.pointerId);
            } catch (error) {
                // Without capture the scrub ends at the strip's edge.
            }

            var index = pick(view, event);

            if (index !== null) {
                show(view, index);
            }
        });

        track.addEventListener('pointerup', function (event) {
            var index = pick(view, event);

            // A finger opens the message its card shows.
            if (event.pointerType === 'touch') {
                dragging = false;

                if (view.showing && (view.at !== null)) {
                    go(view, view.at);
                } else {
                    hide(view);
                }

                return;
            }

            if ((event.button === 0) && (index !== null)) {
                go(view, index);
            }
        });

        track.addEventListener('pointercancel', function () {
            dragging = false;
            hide(view);
        });

        // From the keyboard: the arrows step a message, Page Up and Down a
        // turn, Home and End to the ends; Enter or Space opens it.
        track.addEventListener('keydown', function (event) {
            var data = cache.data;

            if (!data || !data.items.length || !view.layout) {
                return;
            }

            var last = data.items.length - 1;
            var at = (view.at === null) ? last : view.at;
            var to = null;

            if ((event.key === 'ArrowLeft') || (event.key === 'ArrowDown')) {
                to = Math.max(0, at - 1);
            } else if ((event.key === 'ArrowRight') || (event.key === 'ArrowUp')) {
                to = Math.min(last, at + 1);
            } else if (event.key === 'Home') {
                to = 0;
            } else if (event.key === 'End') {
                to = last;
            } else if ((event.key === 'PageUp') || (event.key === 'PageDown')) {
                var place = timelinePlace(view.layout, at);

                if (place) {
                    to = (event.key === 'PageUp') ? Math.max(0, place.turn.from - 1) : Math.min(last, place.turn.to + 1);
                }
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
                show(view, to);
            }
        });

        track.addEventListener('focus', function () {
            if (track.matches(':focus-visible') && cache.data && cache.data.items.length) {
                show(view, (view.at === null) ? cache.data.items.length - 1 : view.at);
            }
        });

        track.addEventListener('blur', function () {
            hide(view);
        });

        // The strip is laid out again when its width changes; in the tabs
        // row too little width hands it to Channel info.
        if (window.ResizeObserver) {
            view.observer = new window.ResizeObserver(function () {
                var width = holder.clientWidth;

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

        // The frame follows the conversation as it scrolls.
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
                    frame(view);
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
     * The messages on the screen moved (drawn, loaded or appended): the
     * frame in the tabs row follows.
     */
    function scrolled() {
        if (views.head) {
            frame(views.head);
        }
    }

    window.PGWsConversationTimeline = {
        mount: mount,
        unmount: unmount,
        changed: changed,
        forget: forget,
        scrolled: scrolled
    };
})();
