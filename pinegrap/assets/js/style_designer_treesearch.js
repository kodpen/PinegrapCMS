/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the tree search part: the Overview's "Search the
 * tree…" box looks in every element's name, text, class and id, with
 * Turkish letters folded ("ikon" finds "İkon"), and lists the results with
 * their path in place of the tree; the arrow keys move through them, Enter
 * or a click selects one. Not needed to open a page: the core runs this
 * file the first time the box is typed into (_sdPartLoad(),
 * pg_designer_parts()) and hands it the page through a host
 * (_sdTreeSearchHost()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('treesearch', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    // At most this many results are drawn; the count says how many there are.
    var MAX_SHOWN = 100;
    // shown: how many entries show each name (label, else name), folded.
    var state = { entries: [], hits: [], active: 0, shown: {} };

    // Folds a text for matching, one letter for one, so a place found in the
    // folded text is the same place in the text: lower case, the Turkish
    // dotted and dotless i alike, and the letters with a mark as the plain
    // letter (ş s, ğ g, ç c, ö o, ü u, â a).
    function _tsFold(text) {
        var from = 'ıİIşŞğĞçÇöÖüÜâÂîÎûÛ', to = 'iiissggccoouuaaiiuu';
        var s = String(text == null ? '' : text), out = '';
        for (var i = 0; i < s.length; i++) {
            var c = s.charAt(i), k = from.indexOf(c);
            if (k !== -1) { out += to.charAt(k); continue; }
            var low = c.toLowerCase();
            if (low.length !== 1) low = c;
            if (low.normalize) {
                var plain = low.normalize('NFD').charAt(0);
                if (plain) low = plain;
            }
            out += low;
        }
        return out;
    }

    // A path longer than four steps: its section, a gap and the last two.
    function _tsPathCut(path) {
        return (path.length > 4) ? [path[0], '…'].concat(path.slice(path.length - 2)) : path.slice();
    }

    // The page's elements in the overview's order, each with its fields
    // (`fieldsOf(node)`: name, label, text, cls, id), those folded, and its
    // path: the names from its section down to its parent (the body is no
    // step). `kidsOf(node)` gives the children the overview lists.
    function _tsIndex(root, kidsOf, fieldsOf) {
        var out = [];
        (function walk(n, path, depth) {
            if (depth > 200) return;
            var kids = kidsOf(n) || [];
            for (var i = 0; i < kids.length; i++) {
                var c = kids[i], f = fieldsOf(c) || {};
                var e = { node: c, path: _tsPathCut(path), fold: {} };
                ['name', 'label', 'text', 'cls', 'id'].forEach(function (k) {
                    e[k] = String(f[k] || '');
                    e.fold[k] = _tsFold(e[k]);
                });
                out.push(e);
                walk(c, path.concat([e.label || e.name]), depth + 1);
            }
        })(root, [], 0);
        return out;
    }

    // The entries holding the words of `q`, folded: a name or designer
    // label that starts with them first, then one that holds them, then an
    // id, a class and a text that hold them; the page's order inside each.
    // Returns the count and the first `max` hits as { i, field, at, len }.
    function _tsMatch(entries, q, max) {
        var f = _tsFold(q).replace(/\s+/g, ' ').trim();
        if (!f) return { total: 0, hits: [] };
        var order = ['name', 'label', 'id', 'cls', 'text'];
        var groups = [[], [], [], [], []];
        entries.forEach(function (e, i) {
            for (var k = 0; k < order.length; k++) {
                var at = e.fold[order[k]].indexOf(f);
                if (at === -1) continue;
                var g = (k < 2) ? (at === 0 ? 0 : 1) : k;
                groups[g].push({ i: i, field: order[k], at: at, len: f.length });
                return;
            }
        });
        var all = [].concat.apply([], groups);
        return { total: all.length, hits: all.slice(0, max) };
    }

    // A text with the found words marked, escaped.
    function _tsMark(text, at, len) {
        return esc(text.slice(0, at)) + '<mark>' + esc(text.slice(at, at + len)) + '</mark>' + esc(text.slice(at + len));
    }

    // A long text cut around the found words, at most `max` letters:
    // { text, at } with the place moved to the cut text.
    function _tsSnippet(text, at, len, max) {
        if (text.length <= max) return { text: text, at: at };
        var from = Math.max(0, Math.min(at - Math.floor((max - len) / 3), text.length - max));
        var cut = text.slice(from, from + max);
        var lead = from > 0 ? '…' : '';
        return { text: lead + cut + (from + max < text.length ? '…' : ''), at: at - from + lead.length };
    }

    function _tsItem(host, hit, k) {
        var e = state.entries[hit.i];
        var named = (hit.field === 'name' || hit.field === 'label');
        var shown = named ? e[hit.field] : (e.label || e.name);
        var name = named ? _tsMark(shown, hit.at, hit.len) : esc(shown);
        var extra = '';
        if (hit.field === 'id') extra = '#' + _tsMark(e.id, hit.at, hit.len);
        else if (hit.field === 'cls' || hit.field === 'text') {
            var sn = _tsSnippet(e[hit.field], hit.at, hit.len, 48);
            extra = (hit.field === 'cls' ? '.' : '') + _tsMark(sn.text, sn.at, hit.len);
        } else if (state.shown[_tsFold(shown)] > 1) {
            // A name or label the page uses more than once: its hint tells
            // the results apart.
            var hint = host.hint(e.node);
            if (hint && hint !== shown) extra = esc(hint);
        }
        return '<div class="sd-ov-res' + (k === state.active ? ' active' : '') + '" role="option" id="sd-ov-res-' + k + '" data-k="' + k + '" aria-selected="' + (k === state.active ? 'true' : 'false') + '">' +
            '<span class="sd-ov-res-ico" aria-hidden="true">' + host.icon(e.node) + '</span>' +
            '<span class="sd-ov-res-body">' +
                '<span class="sd-ov-res-name"><span class="sd-ov-res-lbl">' + name + '</span>' + (extra ? '<span class="sd-ov-res-extra">· ' + extra + '</span>' : '') + '</span>' +
                (e.path.length ? '<span class="sd-ov-res-path">' + e.path.map(esc).join(' › ') + '</span>' : '') +
            '</span></div>';
    }

    function _tsDraw(host, total) {
        var box = host.box;
        if (!box) return;
        var foot = '<div class="sd-ov-res-foot">' + esc(_sdT('Looks in the name, the text, the class and the id · “ikon” also finds “İkon” · ↑ ↓ to move, Enter to select')) + '</div>';
        host.total(total);
        if (!total) {
            box.innerHTML = '<div class="sd-ov-res-none">' + esc(_sdT('Nothing in the tree matches the search.')) + '</div>' + foot;
            if (host.input) host.input.removeAttribute('aria-activedescendant');
            return;
        }
        var more = total - state.hits.length;
        box.innerHTML = '<div class="sd-ov-res-list" role="listbox" id="sd-ov-res-list" aria-label="' + esc(_sdT('Search results')) + '">' +
            state.hits.map(function (h, k) { return _tsItem(host, h, k); }).join('') + '</div>' +
            (more > 0 ? '<div class="sd-ov-res-more">' + esc(_sdT('{var} more: add words to narrow the list', more)) + '</div>' : '') + foot;
        if (host.input) host.input.setAttribute('aria-activedescendant', 'sd-ov-res-' + state.active);
        if (!box._tsBound) {
            box._tsBound = true;
            box.addEventListener('mousedown', function (ev) { if (ev.target.closest('.sd-ov-res')) ev.preventDefault(); });
            box.addEventListener('click', function (ev) {
                var el = ev.target.closest('.sd-ov-res');
                var hit = el ? state.hits[parseInt(el.dataset.k, 10)] : null;
                if (hit) box._tsHost.pick(state.entries[hit.i].node);
            });
        }
        box._tsHost = host;
    }

    // The active result moved to `k`, kept in view.
    function _tsActivate(host, k) {
        if (!state.hits.length) return;
        state.active = Math.max(0, Math.min(state.hits.length - 1, k));
        var box = host.box;
        if (!box) return;
        box.querySelectorAll('.sd-ov-res').forEach(function (el) {
            var on = parseInt(el.dataset.k, 10) === state.active;
            el.classList.toggle('active', on);
            el.setAttribute('aria-selected', on ? 'true' : 'false');
            if (on && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
        });
        if (host.input) host.input.setAttribute('aria-activedescendant', 'sd-ov-res-' + state.active);
    }

    return {
        // Searches the page for `q` and draws the results.
        search: function (host, q) {
            state.entries = _tsIndex(host.root, host.kids, host.fields);
            state.shown = {};
            state.entries.forEach(function (e) {
                var k = _tsFold(e.label || e.name);
                state.shown[k] = (state.shown[k] || 0) + 1;
            });
            var res = _tsMatch(state.entries, q, MAX_SHOWN);
            state.hits = res.hits;
            state.active = 0;
            _tsDraw(host, res.total);
        },
        // A key pressed in the box: ArrowDown, ArrowUp or Enter.
        key: function (host, key) {
            if (key === 'ArrowDown') _tsActivate(host, state.active + 1);
            else if (key === 'ArrowUp') _tsActivate(host, state.active - 1);
            else if (key === 'Enter' && state.hits[state.active]) host.pick(state.entries[state.hits[state.active].i].node);
        }
    };
});
