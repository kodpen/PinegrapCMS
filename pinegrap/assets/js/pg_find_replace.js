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

    // Letters and digits without the Unicode mode, for a regular
    // expression that mode refuses (`\-`, `\:`): ASCII and Latin letters.
    var WORD_LATIN = '[0-9A-Za-z_\\u00aa\\u00b5\\u00ba\\u00c0-\\u00d6\\u00d8-\\u00f6\\u00f8-\\u024f]';

    // A typed regular expression held to whole words. The Unicode mode
    // reads letters of any script; a pattern that mode refuses compiles
    // as typed with the Latin letters. Its groups keep their numbers.
    function wholeWordRegex(search, flags) {
        try {
            return new RegExp('(?<!' + WORD + ')(?:' + search + ')(?!' + WORD + ')', flags + 'u');
        } catch (e) {
            return new RegExp('(?<!' + WORD_LATIN + ')(?:' + search + ')(?!' + WORD_LATIN + ')', flags);
        }
    }

    function pattern(q) {
        q = q || {};
        var search = String(q.search == null ? '' : q.search);
        if (search === '') return { re: null, error: '' };
        var flags = 'g' + (q.caseSensitive ? '' : 'i') + (q.lines ? 'm' : '');
        var re;
        try {
            if (q.regex && q.wholeWord) {
                re = wholeWordRegex(search, flags);
            } else if (q.regex) {
                re = new RegExp(search, flags);
            } else {
                var body = search.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                // Any case: the four Turkish i letters alike, as the tree and
                // Options searches fold them ("istanbul" finds "İstanbul");
                // the language's own case folding keeps İ and ı apart.
                if (!q.caseSensitive) body = body.replace(/[iIİı]/g, '[iIİı]');
                // A typed space finds a no-break space (a text's &nbsp;).
                body = body.replace(/ /g, '[ \\u00a0]');
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

    // The entities a text decodes; any other named one is kept whole as
    // one sign no search can type, so no word is found inside it.
    var ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: '\'', nbsp: ' ' };
    var OPAQUE = '￼';

    // Whether prop `k` of `node` prints as markup: the text of a content
    // element (heading, paragraph, span, link) and a custom HTML's code.
    // Every other prop is printed escaped and is read as it is.
    function isMarkup(node, k) {
        return k === 'html' || (k === 'text' && node.type === 'content');
    }

    // One run of text between two tags, decoded: { text, from, to }, where
    // letter i of `text` stands for raw.slice(from[i], to[i]).
    function decodeRun(raw) {
        var text = '', from = [], to = [];
        var ent = /&(?:#(\d{1,7})|#[xX]([0-9a-fA-F]{1,6})|([A-Za-z][A-Za-z0-9]{0,31}));/y;
        var i = 0;
        while (i < raw.length) {
            var c = raw.charAt(i), end = i + 1, m = null;
            if (c === '&') {
                ent.lastIndex = i;
                m = ent.exec(raw);
            }
            if (m) {
                end = ent.lastIndex;
                var cp = m[1] ? parseInt(m[1], 10) : (m[2] ? parseInt(m[2], 16) : -1);
                if (cp > 0 && cp <= 0x10FFFF) c = String.fromCodePoint(cp);
                else if (m[3] && Object.prototype.hasOwnProperty.call(ENTITIES, m[3])) c = ENTITIES[m[3]];
                else c = OPAQUE;
            }
            for (var k = 0; k < c.length; k++) { text += c.charAt(k); from.push(i); to.push(end); }
            i = end;
        }
        return { text: text, from: from, to: to };
    }

    // A replacement written into markup as text.
    function encode(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/ /g, '&nbsp;');
    }

    // The matches of markup `html` in the words between its tags (a word
    // inside a tag or an entity is none), and the markup with them replaced
    // when `replacement` is given: only the matched letters change, the
    // tags and the rest of the text stay byte for byte. A match never
    // crosses a tag. A script or style block is code, kept whole with its
    // tags: a custom HTML's `"Contact"` string or `.Contact` selector is no
    // word of the page.
    function markupEdit(html, re, replacement, regex) {
        var parts = html.split(/(<!--[\s\S]*?-->|<script\b[^>]*>[\s\S]*?<\/script\s*>|<style\b[^>]*>[\s\S]*?<\/style\s*>|<\/?[A-Za-z][^>]*>)/i);
        var n = 0, out = '';
        for (var p = 0; p < parts.length; p++) {
            var raw = parts[p];
            if (p % 2 === 1 || raw === '') { out += raw; continue; }
            var run = decodeRun(raw), text = run.text, prev = 0, m;
            re.lastIndex = 0;
            while ((m = re.exec(text)) !== null) {
                if (m[0] === '') { re.lastIndex++; continue; }
                n++;
                if (replacement === null) continue;
                var s = m.index, e = s + m[0].length - 1;
                var rep = regex ? expand(replacement, m, m.index, text, m.groups) : replacement;
                out += raw.slice(prev, run.from[s]) + encode(rep);
                prev = run.to[e];
            }
            re.lastIndex = 0;
            out += raw.slice(prev);
        }
        return { text: out, count: n };
    }

    // The matches of prop `k` of `node`, and its text with them replaced.
    function valueEdit(node, k, re, replacement, regex) {
        var v = node.props[k];
        if (!re || typeof v !== 'string' || v === '') return { text: v, count: 0 };
        if (isMarkup(node, k)) return markupEdit(v, re, replacement === null ? null : String(replacement == null ? '' : replacement), regex);
        if (replacement === null) return { text: v, count: count(v, re) };
        return replace(v, re, replacement, regex);
    }

    // Calls fn(node, inArea) for every node but the inside of a placed
    // shared component; `inArea`: the node or one above it is a block
    // marked as an editable area (props._editable).
    function walk(node, fn, inArea) {
        if (!node || typeof node !== 'object') return;
        var here = !!inArea || !!(node.props && node.props._editable);
        fn(node, here);
        if (node.type === 'shared_ref') return;
        var kids = node.children;
        if (Array.isArray(kids)) for (var i = 0; i < kids.length; i++) walk(kids[i], fn, here);
    }

    // `may(node, prop, inArea)`, optional: whether that word may change;
    // a word it refuses is neither counted nor replaced.
    function treeCount(tree, re, may) {
        var n = 0;
        walk(tree, function (node, inArea) {
            if (!node.props || node.type === 'shared_ref') return;
            for (var i = 0; i < TEXT_PROPS.length; i++) {
                if (may && !may(node, TEXT_PROPS[i], inArea)) continue;
                n += valueEdit(node, TEXT_PROPS[i], re, null, false).count;
            }
        });
        return n;
    }

    // Changes the tree in place; the number of matches replaced.
    function treeReplace(tree, re, replacement, regex, may) {
        var n = 0;
        walk(tree, function (node, inArea) {
            if (!node.props || node.type === 'shared_ref') return;
            for (var i = 0; i < TEXT_PROPS.length; i++) {
                var k = TEXT_PROPS[i];
                if (may && !may(node, k, inArea)) continue;
                var r = valueEdit(node, k, re, replacement == null ? '' : replacement, regex);
                if (r.count) { node.props[k] = r.text; n += r.count; }
            }
        });
        return n;
    }

    // pages: [{ key, name, tree, locked }]; holderOf(page) is the name of
    // who else has it open, '' when nobody; `may` as treeCount() reads it.
    function plan(pages, re, holderOf, may) {
        var found = [], out = [];
        (pages || []).forEach(function (p) {
            if (!p) return;
            var n = treeCount(p.tree, re, may);
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
