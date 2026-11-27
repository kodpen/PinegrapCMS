/**
 * Pinegrap - Enterprise Website Platform
 *
 * The model of the visual designer's first tour (window.PGDesignerTour):
 * its stops and the controls they point at per access level, the order,
 * Next and Back over the stops whose control is on the screen, and what a
 * person has already been offered and seen. Pure: it reads and returns
 * plain values and touches no element and no storage.
 *
 * - stops(access): the stops of 'full' (five) or 'content' (three), each
 *   { id, anchors } with the selectors tried in order; copies, so a caller
 *   may change them.
 * - shown(list, has) / move(list, at, dir, has): `has(selector)` says
 *   whether that control is on the screen now; a stop with none of its
 *   controls on it is skipped.
 * - key(userId), read(raw), write(state): what was seen, kept per person in
 *   the browser under pg.designer.<user id>.tour.
 * - offer(state, access, busy): whether the card "New here?" may be shown.
 * - visible(box, clips, W, H): the part of a control in sight, and whether
 *   a scroll of its list brings it in.
 * - escapes(busy): whether Escape is the tour's or an open menu's.
 * - tasks(access): the Help window's "How do I…" tasks, each { id, pointers }
 *   with three pointers walked as the stops are (shown, move).
 * - starts(access, have): the starts an empty page's canvas offers.
 *
 * Nothing runs at load but the definition of window.PGDesignerTour.
 * tests/designer_tour_model_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (root) {
    'use strict';

    // The stops in their order. A designer's five: add something (the
    // palette), shape it (Options, else the canvas), see it (Preview), put
    // it on the site (the main button, else the Publish sheet's), find
    // anything (the "?" button that lists the command palette's keys).
    // A content editor's three: what they may change (the canvas), how
    // (Options), how it reaches the site (the main button).
    var STOPS = {
        full: [
            { id: 'add', anchors: ['#sd-components'] },
            { id: 'shape', anchors: ['#sd-properties', '#sd-canvas'] },
            { id: 'see', anchors: ['#sd-preview-menu-btn'] },
            { id: 'publish', anchors: ['#sd-ajax-save', '#sd-publish-page'] },
            { id: 'find', anchors: ['#sd-status-shortcuts'] }
        ],
        content: [
            { id: 'change', anchors: ['#sd-canvas'] },
            { id: 'edit', anchors: ['#sd-properties', '#sd-canvas'] },
            { id: 'send', anchors: ['#sd-ajax-save', '#sd-publish-page'] }
        ]
    };

    // The Help window's "How do I…" tasks in their order, each with three
    // pointers on the real controls; a pointer's selectors are tried in
    // order, as a stop's are. A content editor sees only the tasks they can
    // do: change a text or a picture, publish.
    var CANVAS = '#sd-canvas-iframe';
    var TASKS = [
        { id: 'section', pointers: [['#sd-pal-sw-blocks'], ['#sd-pal-blocks .sd-drag-item'], [CANVAS]] },
        { id: 'text', content: true, pointers: [[CANVAS], ['#sd-props-head .sd-otab[data-otab="content"]', '#sd-properties'], ['#sd-prop-img-src']] },
        { id: 'colours', pointers: [['#sd-rcol-tab-design'], ['#sd-assets-tabs [data-as-tab="themes"]'], [CANVAS]] },
        { id: 'phone', pointers: [['#sd-bp-bar .sd-bp-sizes', '#sd-bp-size-select'], [CANVAS], ['#sd-preview-menu-btn']] },
        { id: 'page', pointers: [['.sd-tabs-add-btn'], ['#sd-tabs-list .sd-tab.active'], ['#sd-tabs-list .sd-tab.active .sd-tab-menu']] },
        { id: 'list', pointers: [['#sd-panel-tabs [data-tab="system"]'], ['#sd-system-new-items [data-region-type="form_list_view"]'], ['#sd-properties']] },
        { id: 'form', pointers: [['#sd-panel-tabs [data-tab="system"]'], ['#sd-system-new-items [data-region-type="custom_form"]'], ['#sd-properties']] },
        { id: 'shared', pointers: [['#sd-tree-list'], ['#sd-panel-tabs [data-tab="shared"]'], [CANVAS]] },
        { id: 'history', pointers: [['#sd-main-undo'], ['#sd-vb-snapshots'], ['#sd-snapshots-panel .sd-snap-item']] },
        { id: 'publish', content: true, pointers: [['#sd-preview-menu-btn'], ['#sd-publish-page', '#sd-ajax-save'], ['#sd-tabs-list .sd-tab.active']] }
    ];

    // The starts an empty page offers, in their order.
    var STARTS = ['section', 'sketch', 'document'];

    var VERSION = 1;

    function level(access) {
        return access === 'content' ? 'content' : 'full';
    }

    function stops(access) {
        return STOPS[level(access)].map(function (s) {
            return { id: s.id, anchors: s.anchors.slice() };
        });
    }

    // The first of the stop's selectors `has` finds, '' when none is there.
    function anchor(stop, has) {
        var list = (stop && stop.anchors) || [];
        for (var i = 0; i < list.length; i++) {
            if (has(list[i])) return list[i];
        }
        return '';
    }

    // The stops whose control is on the screen, in order, each
    // { id, anchor, i } (i: its place in `list`).
    function shown(list, has) {
        var out = [];
        (list || []).forEach(function (s, i) {
            var a = anchor(s, has);
            if (a) out.push({ id: s.id, anchor: a, i: i });
        });
        return out;
    }

    // The stop after (dir 1) or before (dir -1) place `at` whose control is
    // on the screen now, as { i, id, anchor }. Asked again at each step, so a
    // control that went away since the tour began is skipped too. Forward
    // past the last: null, the tour is over. Back before the first: the
    // stop at `at` when its control is still there, else null.
    function move(list, at, dir, has) {
        list = list || [];
        var step = dir < 0 ? -1 : 1;
        for (var i = at + step; i >= 0 && i < list.length; i += step) {
            var a = anchor(list[i], has);
            if (a) return { i: i, id: list[i].id, anchor: a };
        }
        if (step < 0 && at >= 0 && at < list.length) {
            var here = anchor(list[at], has);
            if (here) return { i: at, id: list[at].id, anchor: here };
        }
        return null;
    }

    // The part of a control a person can see: its box `box` cut by the
    // window (W x H) and by `clips`, the boxes of its ancestors that cut
    // what overflows them, inner first, each { left, top, right, bottom,
    // scroll } (scroll: the ancestor scrolls). Returns { box, scroll }: box
    // the part in sight (null when none); scroll true when a scrolling
    // ancestor cuts the control and is itself in sight, so a scroll brings
    // the control in (a row below the fold of its panel's list).
    function visible(box, clips, W, H) {
        var out = { box: null, scroll: false };
        if (!box) return out;
        clips = clips || [];
        var win = { left: 0, top: 0, right: W, bottom: H };
        var v = cut(box, win);
        for (var i = 0; i < clips.length; i++) {
            var c = clips[i];
            if (c.scroll && !inside(box, c) && !out.scroll) {
                // The list scrolled to must be in sight itself.
                var own = cut(c, win);
                for (var j = i + 1; j < clips.length && own; j++) own = cut(own, clips[j]);
                out.scroll = !!own;
            }
            v = v && cut(v, c);
        }
        if (!v && !out.scroll) return out;
        out.box = v ? { left: v.left, top: v.top, right: v.right, bottom: v.bottom } : null;
        return out;
    }

    // The overlap of two boxes, null when they do not meet.
    function cut(a, b) {
        var o = {
            left: Math.max(a.left, b.left), top: Math.max(a.top, b.top),
            right: Math.min(a.right, b.right), bottom: Math.min(a.bottom, b.bottom)
        };
        return o.right > o.left && o.bottom > o.top ? o : null;
    }

    function inside(a, b) {
        return a.left >= b.left && a.top >= b.top && a.right <= b.right && a.bottom <= b.bottom;
    }

    // Whether Escape ends the tour or puts the card away: not while a
    // dialog, a menu or a typed field is open, as the key is theirs (the
    // Preview menu at the "see" stop closes with it). `busy` as offer()'s,
    // with `menu`.
    function escapes(busy) {
        busy = busy || {};
        return !(busy.dialog || busy.menu || busy.typing);
    }

    // The tour's first stop: the one after "before the first".
    function first(list, has) {
        return move(list, -1, 1, has);
    }

    // The "How do I…" tasks of `access`, each { id, pointers } where a
    // pointer is { id: '<task>.<n>', anchors }; copies, so a caller may
    // change them. A task's pointers go through shown() and move() as stops.
    function tasks(access) {
        var content = level(access) === 'content';
        return TASKS.filter(function (t) { return !content || t.content; }).map(function (t) {
            return {
                id: t.id,
                pointers: t.pointers.map(function (a, n) {
                    return { id: t.id + '.' + (n + 1), anchors: a.slice() };
                })
            };
        });
    }

    // The starts an empty page's canvas offers: `have` says which feature
    // is there ({ section, sketch, document }); a content editor gets none.
    function starts(access, have) {
        if (level(access) === 'content') return [];
        have = have || {};
        return STARTS.filter(function (id) { return have[id] === true; });
    }

    // The browser storage key of a person's tour state; '' without a person.
    function key(userId) {
        var n = parseInt(userId, 10);
        return n > 0 ? 'pg.designer.' + n + '.tour' : '';
    }

    function blank() {
        return { v: VERSION, offered: false, seen: { full: false, content: false }, tips: {}, tipsOff: false };
    }

    // The stored text as a state; anything unreadable is a first visit.
    function read(raw) {
        var s = blank();
        var o = null;
        if (typeof raw === 'string' && raw !== '') {
            try { o = JSON.parse(raw); } catch (e) { o = null; }
        }
        if (!o || typeof o !== 'object' || Array.isArray(o)) return s;
        s.offered = o.offered === true;
        if (o.seen && typeof o.seen === 'object') {
            s.seen.full = o.seen.full === true;
            s.seen.content = o.seen.content === true;
        }
        if (o.tips && typeof o.tips === 'object' && !Array.isArray(o.tips)) {
            Object.keys(o.tips).forEach(function (k) {
                if (o.tips[k] === true && /^[a-z0-9_-]{1,40}$/.test(k)) s.tips[k] = true;
            });
        }
        s.tipsOff = o.tipsOff === true;
        return s;
    }

    function write(state) {
        return JSON.stringify(read(JSON.stringify(state || {})));
    }

    function copy(state) {
        return read(write(state));
    }

    // Whether the card may be shown: never offered before, the person's
    // tour not seen, and nothing in the way. `busy` holds what is going on:
    // { dialog, typing, dragging } (a dialog open, a text being typed, a
    // drag under way).
    function offer(state, access, busy) {
        var s = read(write(state));
        busy = busy || {};
        if (s.offered || s.seen[level(access)]) return false;
        return !(busy.dialog || busy.typing || busy.dragging);
    }

    // The state after the card was shown, whatever the answer: it is
    // offered once.
    function offered(state) {
        var s = copy(state);
        s.offered = true;
        return s;
    }

    // The state after the person's tour was ended: by its last stop, Skip
    // or Escape alike.
    function seen(state, access) {
        var s = copy(state);
        s.offered = true;
        s.seen[level(access)] = true;
        return s;
    }

    // Whether the first-use tip `name` is due: tips on and not seen yet.
    function tipDue(state, name) {
        var s = read(write(state));
        return !s.tipsOff && /^[a-z0-9_-]{1,40}$/.test(String(name)) && !s.tips[name];
    }

    // The state after the tip `name` was answered with "Got it".
    function tipSeen(state, name) {
        var s = copy(state);
        if (/^[a-z0-9_-]{1,40}$/.test(String(name))) s.tips[name] = true;
        return s;
    }

    // The state after "Tips" was switched on or off.
    function tipsOn(state, on) {
        var s = copy(state);
        s.tipsOff = !on;
        return s;
    }

    root.PGDesignerTour = {
        stops: stops,
        tasks: tasks,
        starts: starts,
        anchor: anchor,
        shown: shown,
        move: move,
        first: first,
        visible: visible,
        escapes: escapes,
        key: key,
        read: read,
        write: write,
        offer: offer,
        offered: offered,
        seen: seen,
        tipDue: tipDue,
        tipSeen: tipSeen,
        tipsOn: tipsOn
    };
})(typeof window !== 'undefined' ? window : this);
