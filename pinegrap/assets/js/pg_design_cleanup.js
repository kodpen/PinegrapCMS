/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "Clean up" window's pure part (window.PgDesignCleanup): the four
 * lists of an answer of designer/cleanup_list in their fixed order, which
 * list opens first, a list's tick state, what is ticked, sizes in words, the
 * window's view, and for "Remove" the request, its outcome and the rows that
 * stayed. No DOM; the part style_designer_cleanup.js draws.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
var PgDesignCleanup = (function () {
    'use strict';

    // The lists, always in this order; rules and files have a size.
    var KINDS = ['rules', 'files', 'components', 'variables'];

    // A row's key, unique over the four lists: a rule by its id (its sheet,
    // place and text), else by its place in the list.
    function key(kind, row, i) {
        if (kind === 'files' || kind === 'components' || (kind === 'rules' && row.id)) return kind + ':' + row.id;
        if (kind === 'variables') return kind + ':' + row.key;
        return kind + ':' + i;
    }

    // The answer as four lists: kind, rows (each with its key), bytes
    // (rules and files only, else null) and the notes of the rules list.
    function lists(answer) {
        answer = answer || {};
        return KINDS.map(function (kind) {
            var l = answer[kind] || {};
            var rows = (l.rows || []).map(function (r, i) {
                var o = {};
                Object.keys(r).forEach(function (k) { o[k] = r[k]; });
                o.key = key(kind, r, i);
                return o;
            });
            return {
                kind: kind,
                rows: rows,
                bytes: (kind === 'rules' || kind === 'files') ? (l.bytes || 0) : null,
                notes: kind === 'rules' ? (l.notes || []) : []
            };
        });
    }

    // Which lists open at first: from the Publish check the rules list; else
    // the first one that has rows. An object kind => true.
    function firstOpen(ls, from) {
        var open = {};
        if (from === 'publishcheck') { open.rules = true; return open; }
        for (var i = 0; i < ls.length; i++) {
            if (ls[i].rows.length) { open[ls[i].kind] = true; break; }
        }
        return open;
    }

    // A list's head box: 'none', 'some' (drawn as a dash) or 'all'.
    function tickState(list, ticked) {
        var n = list.rows.filter(function (r) { return ticked[r.key]; }).length;
        if (!n) return 'none';
        return n === list.rows.length ? 'all' : 'some';
    }

    // What is ticked over all lists: count, bytes and the count per kind.
    function ticked(ls, marks) {
        var out = { count: 0, bytes: 0, kinds: {} };
        ls.forEach(function (l) {
            l.rows.forEach(function (r) {
                if (!marks[r.key]) return;
                out.count++;
                out.bytes += r.bytes || 0;
                out.kinds[l.kind] = (out.kinds[l.kind] || 0) + 1;
            });
        });
        return out;
    }

    // A size in words: "412 B", "1.1 KB", "1.8 MB" (one decimal under 10).
    function size(bytes) {
        bytes = Math.max(0, Math.round(bytes || 0));
        if (bytes < 1024) return bytes + ' B';
        var units = ['KB', 'MB', 'GB'];
        var v = bytes, u = -1;
        do { v /= 1024; u++; } while (v >= 1024 && u < units.length - 1);
        return (v < 10 ? (Math.round(v * 10) / 10) : Math.round(v)) + ' ' + units[u];
    }

    // The window's view: 'save' (unsaved changes: the lists are not drawn),
    // 'busy', 'failed', 'empty' (nothing unused anywhere) or 'lists'.
    function view(dirty, busy, answer) {
        if (dirty) return 'save';
        if (busy) return 'busy';
        if (!answer || answer.status !== 'success') return 'failed';
        var any = lists(answer).some(function (l) { return l.rows.length || l.notes.length; });
        return any ? 'lists' : 'empty';
    }

    // What "Remove" asks for: per kind the ids of the ticked rows
    // (variables by key).
    function removal(ls, marks) {
        var out = { rules: [], files: [], components: [], variables: [] };
        ls.forEach(function (l) {
            l.rows.forEach(function (r) {
                if (marks[r.key]) out[l.kind].push(l.kind === 'variables' ? r.key.slice(10) : r.id);
            });
        });
        return out;
    }

    // What "Remove" did: of (ticked), count, bytes and the count per kind of
    // what went; failed is key => reason for the rows that stayed.
    function outcome(ls, marks, failed) {
        var t = ticked(ls, marks);
        var out = { of: t.count, count: 0, bytes: 0, kinds: {} };
        ls.forEach(function (l) {
            l.rows.forEach(function (r) {
                if (!marks[r.key] || failed[r.key]) return;
                out.count++;
                out.bytes += r.bytes || 0;
                out.kinds[l.kind] = (out.kinds[l.kind] || 0) + 1;
            });
        });
        return out;
    }

    // The lists read again after "Remove", with every row that could not be
    // removed listed and its reason as `failed`: a rule read again is found
    // by its sheet, media and text (its place moved), another row by its
    // key; a row no longer read is put back at its list's end.
    function keep(after, before, failed) {
        function same(kind, a, b) {
            if (kind !== 'rules') return a.key === b.key;
            return a.sheet === b.sheet && a.media === b.media && a.text === b.text;
        }
        return after.map(function (l, n) {
            var rows = l.rows.slice();
            (before[n] ? before[n].rows : []).forEach(function (old) {
                if (!failed[old.key]) return;
                var hit = rows.filter(function (r) { return same(l.kind, r, old); })[0];
                if (!hit) { hit = {}; Object.keys(old).forEach(function (k) { hit[k] = old[k]; }); rows.push(hit); }
                hit.failed = failed[old.key];
            });
            return { kind: l.kind, rows: rows, bytes: l.bytes, notes: l.notes };
        });
    }

    return { KINDS: KINDS, lists: lists, removal: removal, outcome: outcome, keep: keep, firstOpen: firstOpen, tickState: tickState, ticked: ticked, size: size, view: view };
}());
