/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Find and Replace" of the visual designer (window.PGFindReplace): the
 * matcher behind its three scopes (this page, all pages, code files). Pure:
 * it reads plain node trees ({ _id, type, props, children }) and plain
 * texts and returns plain objects; it touches no element and reads no
 * editor state.
 *
 * - pattern(query): the RegExp of a query { search, caseSensitive,
 *   wholeWord, regex, lines }, or { error } for a pattern that does not
 *   compile ('invalid') or matches empty text ('empty'). `lines` reads ^
 *   and $ per line, as the code view's search does.
 * - count(text, re) / replace(text, re, replacement, regex): the matches of
 *   one text, and the text with them replaced. A plain search puts the
 *   replacement in as typed ($& and $1 are text); a regular expression
 *   reads $1 and $& as its groups.
 * - treeCount(tree, re) / treeReplace(tree, re, replacement, regex): the
 *   same over a page tree's words (TEXT_PROPS of every node). The inside of
 *   a placed shared component is never read: a stored page keeps it empty,
 *   and the component is its own row.
 * - plan(pages, re, holderOf): one row a page, { key, name, count, skip,
 *   who }, skip 'rights' (no content rights for its folder) or 'held'
 *   (holderOf(page) names who has it open); the pages with matches first,
 *   in the order given, then the ones left out.
 *
 * Nothing runs at load but the definition of window.PGFindReplace.
 * tests/designer_find_replace_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (root) {
    'use strict';

    // The props that hold a node's words.
    var TEXT_PROPS = [
        'text', 'content', 'html', 'paragraph', 'heading',
        'btnText', 'btnText1', 'btnText2',
        'title', 'subtitle', 'label', 'caption',
        'price', 'tier'
    ];

    // Letters and digits of any script, for a whole word that is not
    // ASCII ("çerez", "İade").
    var WORD = '[\\p{L}\\p{N}_]';

    function pattern(q) {
        q = q || {};
        var search = String(q.search == null ? '' : q.search);
        if (search === '') return { re: null, error: '' };
        var flags = 'g' + (q.caseSensitive ? '' : 'i') + (q.lines ? 'm' : '');
        var re;
        try {
            if (q.regex) {
                re = new RegExp(search, flags);
            } else {
                var body = search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                if (q.wholeWord) body = '(?<!' + WORD + ')' + body + '(?!' + WORD + ')';
                re = new RegExp(body, flags + 'u');
            }
        } catch (e) {
            return { re: null, error: 'invalid' };
        }
        // A pattern that matches empty text would put the replacement
        // between every two letters.
        re.lastIndex = 0;
        if (re.test('')) return { re: null, error: 'empty' };
        re.lastIndex = 0;
        return { re: re, error: '' };
    }

    function count(text, re) {
        if (!re || typeof text !== 'string' || text === '') return 0;
        var n = 0, m;
        re.lastIndex = 0;
        while ((m = re.exec(text)) !== null) {
            if (m[0] === '') { re.lastIndex++; continue; }
            n++;
        }
        re.lastIndex = 0;
        return n;
    }

    function replace(text, re, replacement, regex) {
        if (!re || typeof text !== 'string' || text === '') return { text: text, count: 0 };
        var n = 0;
        replacement = String(replacement == null ? '' : replacement);
        re.lastIndex = 0;
        var out = text.replace(re, function (m) {
            if (m === '') return m;
            n++;
            if (!regex) return replacement;
            // The groups as String.prototype.replace reads them.
            var args = Array.prototype.slice.call(arguments);
            var named = (typeof args[args.length - 1] === 'object') ? args.pop() : undefined;
            args.pop();                       // the whole text
            var at = args.pop();
            return expand(replacement, args, at, text, named);
        });
        re.lastIndex = 0;
        return { text: out, count: n };
    }

    // $$, $&, $`, $', $1..$99 and $<name> of a replacement, as the
    // language reads them, for the match `groups` (match first) at `at`.
    function expand(rep, groups, at, whole, named) {
        var m = groups[0];
        return rep.replace(/\$(\$|&|`|'|<([^>]*)>|(\d{1,2}))/g, function (all, what, name, num) {
            if (what === '$') return '$';
            if (what === '&') return m;
            if (what === '`') return whole.slice(0, at);
            if (what === '\'') return whole.slice(at + m.length);
            if (name !== undefined) return named ? (named[name] == null ? '' : named[name]) : all;
            var i = parseInt(num, 10);
            if (i >= 1 && i < groups.length) return groups[i] == null ? '' : groups[i];
            // $12 with one group is $1 followed by 2.
            if (num.length === 2) {
                var j = parseInt(num.charAt(0), 10);
                if (j >= 1 && j < groups.length) return (groups[j] == null ? '' : groups[j]) + num.charAt(1);
            }
            return all;
        });
    }

    function walk(node, fn) {
        if (!node || typeof node !== 'object') return;
        fn(node);
        if (node.type === 'shared_ref') return;
        var kids = node.children;
        if (Array.isArray(kids)) for (var i = 0; i < kids.length; i++) walk(kids[i], fn);
    }

    function treeCount(tree, re) {
        var n = 0;
        walk(tree, function (node) {
            if (!node.props || node.type === 'shared_ref') return;
            for (var i = 0; i < TEXT_PROPS.length; i++) n += count(node.props[TEXT_PROPS[i]], re);
        });
        return n;
    }

    // Changes the tree in place; the number of matches replaced.
    function treeReplace(tree, re, replacement, regex) {
        var n = 0;
        walk(tree, function (node) {
            if (!node.props || node.type === 'shared_ref') return;
            for (var i = 0; i < TEXT_PROPS.length; i++) {
                var k = TEXT_PROPS[i], v = node.props[k];
                if (typeof v !== 'string' || v === '') continue;
                var r = replace(v, re, replacement, regex);
                if (r.count) { node.props[k] = r.text; n += r.count; }
            }
        });
        return n;
    }

    // pages: [{ key, name, tree, locked }]; holderOf(page) is the name of
    // who else has it open, '' when nobody.
    function plan(pages, re, holderOf) {
        var found = [], out = [];
        (pages || []).forEach(function (p) {
            if (!p) return;
            var n = treeCount(p.tree, re);
            if (!n) return;
            var row = { key: p.key, name: p.name || '', count: n, skip: '', who: '' };
            if (p.locked) row.skip = 'rights';
            else {
                var who = holderOf ? (holderOf(p) || '') : '';
                if (who) { row.skip = 'held'; row.who = who; }
            }
            (row.skip ? out : found).push(row);
        });
        return found.concat(out);
    }

    // The totals of a plan: matches and pages that will change, and the
    // pages left out.
    function totals(rows) {
        var t = { count: 0, pages: 0, left: 0 };
        (rows || []).forEach(function (r) {
            if (r.skip) { t.left++; return; }
            t.count += r.count;
            t.pages++;
        });
        return t;
    }

    root.PGFindReplace = {
        TEXT_PROPS: TEXT_PROPS,
        pattern: pattern,
        count: count,
        replace: replace,
        treeCount: treeCount,
        treeReplace: treeReplace,
        plan: plan,
        totals: totals
    };
})(window);
