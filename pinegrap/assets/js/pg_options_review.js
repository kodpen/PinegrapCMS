/**
 * Pinegrap - Enterprise Website Platform
 *
 * "Only what I changed", the review view of the visual designer's Options
 * panel (window.PGOptionsReview): which rows its list holds, in which order
 * and with which words, the count on its switch, and the loop of "Reset
 * all". Pure: it reads and returns plain objects and touches no element.
 *
 * - A row is one changed row of the panel, as the part reads it:
 *   { tab, top, section, label, text, swatch, mixed, n, of } (top: the key
 *   of the outermost section card holding it, '' outside a card; n and of:
 *   in how many of the selected elements it is changed).
 * - list(): the rows the tabs' badges count (Translation and rows outside a
 *   card are not), Content, Style, Advanced, each in the panel's order.
 * - total(): the switch's count, the tabs' counts added.
 * - resetAll(): every row reset inside one undo step.
 *
 * Nothing runs at load but the definition of window.PGOptionsReview. It is
 * the first source of the designer part 'review'
 * (assets/js/style_designer_review.min.js, pg_designer_parts()); after
 * editing run `php tools/build_js.php`. tests/designer_options_review_test.php
 * runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (root) {
    'use strict';

    var TABS = ['content', 'style', 'advanced'];

    function clean(s) {
        return String(s == null ? '' : s).replace(/\s+/g, ' ').trim();
    }

    // The rows `rows` the list holds, as entries { i, tab, label, path,
    // value, kind, swatch, share }: `i` the row's place in `rows`, `path`
    // "tab › section" with the tab's name from `names`, `kind` 'mixed' when
    // the selected elements hold different values, 'color' for a colour,
    // 'value' for a value and 'none' when it is empty; `share` "2/3" for
    // several elements. Sorted by tab, the panel's order kept inside one.
    function list(rows, names) {
        names = names || {};
        var out = [];
        (rows || []).forEach(function (r, i) {
            if (!r || !r.top || r.top === 'tr') return;
            var at = TABS.indexOf(r.tab);
            var value = clean(r.text);
            var kind = r.mixed ? 'mixed' : (r.swatch ? 'color' : (value ? 'value' : 'none'));
            var section = clean(r.section);
            var tab = names[r.tab] || r.tab || '';
            out.push({
                i: i,
                tab: r.tab,
                order: at === -1 ? TABS.length : at,
                label: clean(r.label),
                path: section ? tab + ' › ' + section : tab,
                value: kind === 'mixed' ? '' : value,
                kind: kind,
                swatch: kind === 'color' ? String(r.swatch) : '',
                share: (r.of > 1 && r.n > 0) ? r.n + '/' + r.of : ''
            });
        });
        out.sort(function (a, b) { return (a.order - b.order) || (a.i - b.i); });
        out.forEach(function (e) { delete e.order; });
        return out;
    }

    // The switch's count: the tabs' counts (by tab) added.
    function total(counts) {
        var n = 0;
        Object.keys(counts || {}).forEach(function (t) { n += Number(counts[t]) || 0; });
        return n;
    }

    // "Reset all": inside `asOne` (one undo step) asks `next(done)` for the
    // next changed row not yet tried ({ key }, null when none is left) and
    // resets it with `reset(row)`, at most `max` times. A row whose reset
    // leaves it changed is not tried again. Answers how many were reset.
    function resetAll(asOne, next, reset, max) {
        var n = 0;
        var done = {};
        max = max || 500;
        asOne(function () {
            while (n < max) {
                var row = next(done);
                if (!row) break;
                done[row.key] = true;
                reset(row);
                n++;
            }
        });
        return n;
    }

    root.PGOptionsReview = {
        list: list,
        total: total,
        resetAll: resetAll
    };
})(typeof window !== 'undefined' ? window : this);
