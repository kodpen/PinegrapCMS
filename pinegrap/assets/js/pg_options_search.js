/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Options panel's search of the visual designer
 * (window.PGOptionsSearch): it finds an option row in all three tabs by its
 * label, its section's name, the CSS property it writes and its class
 * prefix, with Turkish letters folded. Pure: it reads and returns plain
 * objects and touches no element.
 *
 * - An entry is one row of the panel: { label, section, tab, tabName, sid,
 *   nth, prefix, css } (sid: the card's data-sd-card, nth: the row's place
 *   among the card's own rows). The part builds them from the drawn panel.
 * - fold(): one letter for one, so a place found in the folded text is the
 *   same place in the text as shown.
 * - match(): the entries that hold the typed words, best first.
 * - target(): what Enter does with a result: the tab, the card, the row.
 * - noMatch(): the words of the "nothing found" line and the class offered.
 *
 * Nothing runs at load but the definition of window.PGOptionsSearch. It is
 * the first source of the designer part 'optsearch'
 * (assets/js/style_designer_optsearch.min.js, pg_designer_parts()); after
 * editing run `php tools/build_js.php`. tests/designer_options_search_test.php
 * runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (root) {
    'use strict';

    // Letters read as their plain form: the Turkish dotted and dotless i in
    // both cases, ş, ğ, ç, ö, ü and the circumflex vowels.
    var FOLD = {
        'İ': 'i', 'I': 'i', 'ı': 'i', 'Ş': 's', 'ş': 's', 'Ğ': 'g', 'ğ': 'g',
        'Ç': 'c', 'ç': 'c', 'Ö': 'o', 'ö': 'o', 'Ü': 'u', 'ü': 'u',
        'Â': 'a', 'â': 'a', 'Î': 'i', 'î': 'i', 'Û': 'u', 'û': 'u'
    };

    // Lower case with the letters above folded, one letter for one (a
    // letter whose lower case is longer keeps its first letter), so indexes
    // stay those of the text as shown. A decomposed İ (I and the combining
    // dot) is composed first.
    function fold(s) {
        s = String(s == null ? '' : s);
        if (s.normalize) s = s.normalize('NFC');
        var out = '';
        for (var i = 0; i < s.length; i++) {
            var c = s.charAt(i);
            if (Object.prototype.hasOwnProperty.call(FOLD, c)) { out += FOLD[c]; continue; }
            var l = c.toLowerCase();
            out += l.length === 1 ? l : l.charAt(0);
        }
        return out;
    }

    // The words of a typed search, folded.
    function words(q) {
        return fold(q).split(/\s+/).filter(Boolean);
    }

    var SIZE_INFIX = /-(sm|md|lg|xl|xxl)-$/;

    // The class prefix of a row's group of classes (its select's
    // data-stylegroup or data-util): the part they share up to its last
    // dash ('mt-'); a screen-size infix is dropped ('mt-md-' gives 'mt-'); a
    // group holding its own stem ('shadow', 'shadow-sm') gives the stem and a
    // dash. '' when the classes share no prefix.
    function prefixOf(classes) {
        var list = (Array.isArray(classes) ? classes : String(classes || '').split(','))
            .map(function (c) { return String(c).trim().split(/\s+/)[0]; }).filter(Boolean);
        if (!list.length) return '';
        var p = list[0];
        list.forEach(function (c) {
            var n = 0;
            while (n < p.length && n < c.length && p.charAt(n) === c.charAt(n)) n++;
            p = p.slice(0, n);
        });
        if (list.indexOf(p) !== -1 && p.charAt(p.length - 1) !== '-') p += '-';
        else p = p.slice(0, p.lastIndexOf('-') + 1);
        if (p === '-') return '';
        return p.replace(SIZE_INFIX, '-');
    }

    // The CSS property a class prefix writes, by Bootstrap's utilities.
    var CSS_OF = {
        'm-': 'margin', 'mt-': 'margin-top', 'mb-': 'margin-bottom', 'ms-': 'margin-inline-start',
        'me-': 'margin-inline-end', 'mx-': 'margin-inline', 'my-': 'margin-block',
        'p-': 'padding', 'pt-': 'padding-top', 'pb-': 'padding-bottom', 'ps-': 'padding-inline-start',
        'pe-': 'padding-inline-end', 'px-': 'padding-inline', 'py-': 'padding-block',
        'd-': 'display', 'float-': 'float', 'fs-': 'font-size', 'fw-': 'font-weight',
        'fst-': 'font-style', 'lh-': 'line-height', 'font-': 'font-family',
        'text-decoration-': 'text-decoration', 'bg-': 'background-color',
        'border-': 'border', 'rounded-': 'border-radius', 'shadow-': 'box-shadow',
        'w-': 'width', 'h-': 'height', 'mw-': 'max-width', 'mh-': 'max-height',
        'gap-': 'gap', 'row-gap-': 'row-gap', 'column-gap-': 'column-gap',
        'flex-grow-': 'flex-grow', 'flex-shrink-': 'flex-shrink', 'flex-wrap-': 'flex-wrap',
        'justify-content-': 'justify-content', 'align-items-': 'align-items',
        'align-content-': 'align-content', 'align-self-': 'align-self', 'order-': 'order',
        'opacity-': 'opacity', 'z-': 'z-index', 'position-': 'position', 'overflow-': 'overflow',
        'object-fit-': 'object-fit', 'ratio-': 'aspect-ratio', 'top-': 'top', 'bottom-': 'bottom',
        'start-': 'inset-inline-start', 'end-': 'inset-inline-end', 'user-select-': 'user-select',
        'link-': 'color', 'vertical-align-': 'vertical-align'
    };

    // The CSS property of a row option stored as a property, not a class.
    var CSS_OF_PROP = {
        zIndex: 'z-index', aspectRatio: 'aspect-ratio', fontFamily: 'font-family',
        objectPosition: 'object-position', rounded: 'border-radius', bg: 'background-color'
    };

    // The CSS property of a row: by its class prefix ('text-' by its values:
    // alignment, case or colour; 'flex-' by direction or wrap), else by its
    // property name; '' when none is known.
    function cssOf(prefix, classes, prop) {
        var list = Array.isArray(classes) ? classes : String(classes || '').split(',');
        var has = function (re) { return list.some(function (c) { return re.test(String(c).trim()); }); };
        if (prefix === 'text-') {
            if (has(/^text-(start|center|end|justify)$/)) return 'text-align';
            if (has(/^text-(lowercase|uppercase|capitalize)$/)) return 'text-transform';
            if (has(/^text-(wrap|nowrap|break)$/)) return 'white-space';
            return 'color';
        }
        if (prefix === 'flex-') {
            if (has(/^flex-(row|column)/)) return 'flex-direction';
            if (has(/^flex-(wrap|nowrap)/)) return 'flex-wrap';
            return 'flex';
        }
        if (prefix && Object.prototype.hasOwnProperty.call(CSS_OF, prefix)) return CSS_OF[prefix];
        if (prop && Object.prototype.hasOwnProperty.call(CSS_OF_PROP, prop)) return CSS_OF_PROP[prop];
        return '';
    }

    // Where a typed word stands in `text` (folded): at its start (0), at a
    // word's start (1), inside (2); -1 when not there. `at` is its place.
    function placeIn(text, w) {
        var at = text.indexOf(w);
        if (at === -1) return { rank: -1, at: -1 };
        if (at === 0) return { rank: 0, at: 0 };
        var re = new RegExp('(^|[^a-z0-9])' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
        var m = re.exec(text);
        if (m) return { rank: 1, at: m.index + m[1].length };
        return { rank: 2, at: at };
    }

    // A typed word against a class prefix: the prefix starts with it ('mt'
    // finds 'mt-') or it starts with the prefix ('mt-3' finds 'mt-'; at least
    // two letters, so a typed 'p' alone does not find every 'p-').
    function prefixHit(prefix, w) {
        if (!prefix) return false;
        var p = fold(prefix);
        if (p.indexOf(w) === 0) return true;
        return p.length >= 2 && w.indexOf(p) === 0;
    }

    var TAB_ORDER = { content: 0, style: 1, advanced: 2 };

    // The entries holding every typed word, best first. Rank: the whole
    // search at the label's start (0), at a word's start in it (1), inside
    // it (2); else the class prefix or the CSS property (3), the section's
    // name (4), each word somewhere in the four (5). Inside a rank the tabs'
    // order, then the panel's. A result: { e, rank, field ('label', 'prefix',
    // 'css', 'section', 'words'), at, len } with the place in the label.
    function match(entries, q, limit) {
        var ws = words(q);
        if (!ws.length) return [];
        var whole = ws.join(' ');
        var out = [];
        (entries || []).forEach(function (e, i) {
            var label = fold(e.label), section = fold(e.section), css = fold(e.css), prefix = e.prefix || '';
            var r = null;
            var pl = placeIn(label, whole);
            if (pl.rank !== -1) r = { rank: pl.rank, field: 'label', at: pl.at, len: whole.length };
            else if (ws.length === 1 && prefixHit(prefix, whole)) r = { rank: 3, field: 'prefix', at: -1, len: 0 };
            else if (css && css.indexOf(whole) !== -1) r = { rank: 3, field: 'css', at: -1, len: 0 };
            else if (section.indexOf(whole) !== -1) r = { rank: 4, field: 'section', at: -1, len: 0 };
            else {
                var hay = [label, section, css, fold(prefix)].join(' ');
                if (ws.every(function (w) { return hay.indexOf(w) !== -1; })) r = { rank: 5, field: 'words', at: -1, len: 0 };
            }
            if (!r) return;
            r.e = e;
            r.i = i;
            out.push(r);
        });
        out.sort(function (a, b) {
            if (a.rank !== b.rank) return a.rank - b.rank;
            var ta = TAB_ORDER[a.e.tab] == null ? 9 : TAB_ORDER[a.e.tab];
            var tb = TAB_ORDER[b.e.tab] == null ? 9 : TAB_ORDER[b.e.tab];
            return ta !== tb ? ta - tb : a.i - b.i;
        });
        if (limit > 0 && out.length > limit) out = out.slice(0, limit);
        return out;
    }

    // A result's path: its tab and its section ("Style › Shadow and effect").
    function path(e) {
        return [e.tabName || '', e.section || ''].filter(Boolean).join(' › ');
    }

    // What a result shows at its right end: the CSS property or the class
    // prefix it was found by ('margin', 'mt-*'): the property when the search
    // is the whole property or the row writes no class.
    function code(r, q) {
        if (!r || (r.field !== 'prefix' && r.field !== 'css')) return '';
        var e = r.e, whole = words(q).join(' ');
        if (e.css && (fold(e.css) === whole || !e.prefix)) return e.css;
        return e.prefix ? e.prefix + '*' : e.css || '';
    }

    // What Enter does with entry `e` while the panel shows tab `state.tab`
    // and the cards `state.open` (sid: true) open: the tab to show (and
    // whether that switches it), the card to open (and whether it was
    // closed, so it is marked "just opened") and the row whose control gets
    // the focus.
    function target(e, state) {
        state = state || {};
        var open = state.open || {};
        return {
            tab: e.tab,
            switchTab: e.tab !== state.tab,
            section: e.sid,
            openSection: !open[e.sid],
            control: { sid: e.sid, nth: e.nth, label: e.label }
        };
    }

    // The side of Style › Spacing's box a spacing row writes: { k: 'm' or
    // 'p', s: 't', 'e', 'b' or 's' }; the all-sides row goes to the top
    // side. Null for another row.
    function boxSide(prefix) {
        var m = /^([mp])([tbse]?)-$/.exec(prefix || '');
        return m ? { k: m[1], s: m[2] || 't' } : null;
    }

    // The "nothing found" line: `say` the search as typed, trimmed (the
    // line's words, "No option matches “…”."); `add` the class offered
    // under it: the words joined by dashes, only letters, digits, dashes and
    // underscores, not starting with a digit; '' when no class can be made
    // or the element holds it already (`has`: its classes).
    function noMatch(q, has) {
        var say = String(q == null ? '' : q).trim();
        var add = say.replace(/\s+/g, '-').replace(/[^\wÀ-￿-]/g, '');
        if (!/^-?[_a-zA-ZÀ-￿][\wÀ-￿-]*$/.test(add)) add = '';
        var list = Array.isArray(has) ? has : String(has || '').split(/\s+/);
        if (add && list.indexOf(add) !== -1) add = '';
        return { say: say, add: add };
    }

    root.PGOptionsSearch = {
        fold: fold,
        words: words,
        prefixOf: prefixOf,
        cssOf: cssOf,
        match: match,
        path: path,
        code: code,
        target: target,
        boxSide: boxSide,
        noMatch: noMatch
    };
})(typeof window !== 'undefined' ? window : this);
