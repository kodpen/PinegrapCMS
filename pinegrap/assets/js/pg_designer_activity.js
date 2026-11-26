/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pure rules of the designer's History "Activity" tab
 * (window.PgDesignerActivity). No DOM, no request; the tab itself is the
 * designer part `activity` (style_designer_activity.js), its events the
 * reader's (includes/fn/designer_activity.php).
 *
 * - day(at, now): the day head's code and its date: today, yesterday,
 *   this year or another year.
 * - groups(events, now): the events under their day, in the order given.
 * - mark(event): the Bootstrap icon of the event's action.
 * - opens(event) / before(event): whether the row is a button that opens
 *   Compare (a page's version with a kept version before it), and that
 *   version's number.
 * - blank(count, more, narrowed): the list's empty state, none while older
 *   events remain to be read.
 * - count(read, rows): the code of the count after the hour, from what the
 *   change reader found (PgCompareChanges.read() and the rows' length).
 * - people(events, selfId, known): the people named in the rows read so
 *   far, by name, the person asking left out ("Me" is theirs).
 * - pages(pages, openId, query): the Page chooser's pages, the open one
 *   first, filtered by the search field.
 * - params(choice) / narrowed(choice): the three choosers as the panel
 *   action takes them, and whether any of them narrows.
 * - near(scrollTop, clientHeight, scrollHeight): whether the list is near
 *   the end of what is read.
 *
 * tests/designer_activity_js_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var DAY_MS = 86400000;
    // Pixels from the end of the list at which the next fifty are asked for.
    var NEAR_PX = 240;

    var MARKS = {
        publish: 'cloud-arrow-up', restore: 'arrow-counterclockwise', 'import': 'box-arrow-in-down',
        ai: 'stars', applied: 'pencil-square', bundle: 'box-seam',
        page_recycle: 'trash', page_restore: 'reply', page_delete: 'x-circle',
        page_publish: 'eye', page_unpublish: 'eye-slash',
        link_create: 'link-45deg', link_revoke: 'slash-circle', approved: 'check-circle',
        changes_requested: 'arrow-repeat', comment: 'chat-left-text',
        style_save: 'palette', style_rename: 'palette', style_create: 'palette', style_copy: 'copy',
        style_recycle: 'palette', style_delete: 'palette',
        component_create: 'puzzle', component_save: 'puzzle', component_rename: 'puzzle',
        component_settings: 'puzzle', component_delete: 'puzzle',
        file_create: 'file-earmark-code', file_update: 'file-earmark-code', file_delete: 'file-earmark-code'
    };

    var KINDS = ['version', 'page', 'share', 'design', 'component', 'file'];

    function midnight(ms) {
        var d = new Date(ms);
        d.setHours(0, 0, 0, 0);
        return d.getTime();
    }

    // at: seconds; now: milliseconds. Days are the person's own (local).
    function day(at, now) {
        var ms = (Number(at) || 0) * 1000;
        var start = midnight(ms);
        var today = midnight(now);
        var key = String(start);
        if (start === today) return { code: 'today', key: key };
        // A day before today, also across a summer-time change.
        if (Math.round((today - start) / DAY_MS) === 1) return { code: 'yesterday', key: key };
        var same = new Date(ms).getFullYear() === new Date(now).getFullYear();
        return { code: same ? 'date' : 'year', key: key, ms: ms };
    }

    function groups(events, now) {
        var out = [];
        var last = null;
        (Array.isArray(events) ? events : []).forEach(function (e) {
            var d = day(e.at, now);
            if (!last || last.key !== d.key) {
                last = { key: d.key, day: d, events: [] };
                out.push(last);
            }
            last.events.push(e);
        });
        return out;
    }

    function mark(event) {
        return MARKS[event && event.action] || 'dot';
    }

    function isVersion(event) {
        return !!event && event.kind === 'version' && Number(event.page_id) > 0 && Number(event.revision_no) > 0;
    }

    // The version before a page's version, the left side of Compare: the
    // reader's before_no (0 when the page's retention trimmed it), else n-1.
    function before(event) {
        if (!isVersion(event)) return 0;
        var no = (event.before_no !== undefined && event.before_no !== null) ? Number(event.before_no) || 0 : Number(event.revision_no) - 1;
        return no > 0 ? no : 0;
    }

    function opens(event) {
        return before(event) > 0;
    }

    // The empty state of a list read so far: 'none', 'narrowed' (the three
    // choosers keep nothing) or '' while rows show or older ones remain.
    function blank(count, more, narrowed) {
        if (Number(count) > 0 || more) return '';
        return narrowed ? 'narrowed' : 'none';
    }

    // read: PgCompareChanges.read() of the version before against this one,
    // or null when it could not be made; rows: the length of its rows().
    function count(read, rows) {
        if (!read) return { code: '' };
        if (read.state === 'new') return { code: 'first' };
        if (read.state === 'same') return { code: 'same' };
        if (read.state !== 'changed') return { code: '' };
        var n = Number(rows) || 0;
        if (n <= 0) return { code: 'same' };
        return { code: n === 1 ? 'one' : 'many', n: n };
    }

    // known: the people found before, kept so a name does not leave the
    // menu when a narrowed list no longer shows its rows.
    function people(events, selfId, known) {
        var by = {};
        (Array.isArray(known) ? known : []).forEach(function (p) { by[p.id] = p; });
        (Array.isArray(events) ? events : []).forEach(function (e) {
            var id = Number(e.user_id) || 0;
            if (id > 0 && id !== Number(selfId) && !by[id] && e.who) by[id] = { id: id, name: String(e.who) };
        });
        return Object.keys(by).map(function (k) { return by[k]; }).sort(function (a, b) {
            return a.name.localeCompare(b.name) || a.id - b.id;
        });
    }

    function pages(list, openId, query) {
        var q = String(query || '').trim().toLowerCase();
        var out = [];
        (Array.isArray(list) ? list : []).forEach(function (p) {
            var id = Number(p && p.page_id) || 0;
            if (id <= 0) return;
            var name = String(p.page_name || '');
            if (q && name.toLowerCase().indexOf(q) === -1) return;
            out.push({ id: id, name: name, open: id === Number(openId) });
        });
        out.sort(function (a, b) {
            if (a.open !== b.open) return a.open ? -1 : 1;
            return a.name.localeCompare(b.name) || a.id - b.id;
        });
        return out;
    }

    // choice: { person: '' | 'me' | '<id>' | 'customers' | 'schedule',
    // page: 0 | id, kind: '' | one of the six }.
    function params(choice) {
        var c = choice || {};
        var person = String(c.person || '');
        if (!/^(me|customers|schedule|[1-9][0-9]*)$/.test(person)) person = '';
        var page = Number(c.page) > 0 ? String(Math.floor(Number(c.page))) : '';
        var kind = KINDS.indexOf(c.kind) !== -1 ? c.kind : '';
        return { person: person, page: page, kind: kind };
    }

    function narrowed(choice) {
        var p = params(choice);
        return !!(p.person || p.page || p.kind);
    }

    function near(top, height, full) {
        return (Number(full) || 0) - (Number(top) || 0) - (Number(height) || 0) <= NEAR_PX;
    }

    window.PgDesignerActivity = {
        day: day,
        groups: groups,
        mark: mark,
        opens: opens,
        before: before,
        blank: blank,
        isVersion: isVersion,
        count: count,
        people: people,
        pages: pages,
        params: params,
        narrowed: narrowed,
        near: near,
        kinds: function () { return KINDS.slice(); }
    };
})();
