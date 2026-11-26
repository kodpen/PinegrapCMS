/**
 * Pinegrap - Enterprise Website Platform
 *
 * A design's page transition and the names that keep an element across
 * pages, as values (window.PGPageTrans), for the part 'pagetrans'. Pure:
 * no DOM, run by the tests under Node. Twins of includes/fn/design_variables.php.
 *
 *   PGPageTrans.clean(page, ease)   // { kind, [from], [duration], [easing] }
 *                                   // or null (None); ease cleans an easing
 *   PGPageTrans.fromCss(text)       // the transition a variables file holds
 *   PGPageTrans.nameClean(text)     // a view-transition-name, '' for none
 *   PGPageTrans.nameOf(body)        // the name a custom style body holds
 *   PGPageTrans.withName(body, n)   // the body with that name ('' removes)
 *   PGPageTrans.suggest(kind, section, taken)  // a free name to start from
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (root) {
    'use strict';

    var SIDES = ['right', 'left', 'below'];
    var NAMED = ['ease-out', 'ease', 'ease-in', 'ease-in-out', 'linear'];

    // Twin of pg_design_page_transition_clean(). Without `ease` only the
    // named easings are kept.
    function clean(page, ease) {
        if (!page || typeof page !== 'object' || typeof page.kind !== 'string') return null;
        var kind = page.kind.trim().toLowerCase();
        if (kind !== 'fade' && kind !== 'slide') return null;
        var out = { kind: kind };
        if (kind === 'slide') {
            var from = typeof page.from === 'string' ? page.from.trim().toLowerCase() : '';
            out.from = SIDES.indexOf(from) !== -1 ? from : 'right';
        }
        var d = page.duration;
        if ((typeof d === 'number' || (typeof d === 'string' && d.trim() !== '')) && isFinite(Number(d))) {
            out.duration = Math.max(0, Math.min(1000, Math.round(Number(d))));
        }
        if (typeof page.easing === 'string') {
            var e = ease ? ease(page.easing) : (NAMED.indexOf(page.easing.trim().toLowerCase()) !== -1 ? page.easing.trim().toLowerCase() : '');
            if (e) out.easing = e;
        }
        return out;
    }

    // The transition in a variables file's head, null for none.
    function fromCss(text, ease) {
        var m = /^\/\*! pg-theme (\{[\s\S]*?\}) \*\//.exec(String(text || ''));
        if (!m) return null;
        var meta = null;
        try { meta = JSON.parse(m[1]); } catch (e) { return null; }
        if (!meta || meta.kind !== 'variables' || !meta.page) return null;
        return clean(meta.page, ease);
    }

    // Twin of pg_design_view_transition_name_clean().
    function nameClean(text) {
        if (typeof text !== 'string') return '';
        var s = text.trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        if (s === '' || s === 'none' || s === 'auto') return '';
        if (!/^[a-z]/.test(s)) s = 'n-' + s;
        return s.slice(0, 40).replace(/-+$/, '');
    }

    var DECL = /(^|;|\n)\s*view-transition-name\s*:\s*([^;}]*);?/i;

    function nameOf(body) {
        var m = DECL.exec(String(body || ''));
        return m ? nameClean(m[2].replace(/!important/i, '')) : '';
    }

    // The declaration taken out wherever it stands, and written last when
    // a name is given; every other declaration stays as typed.
    function withName(body, name) {
        var text = String(body || '');
        var re = new RegExp(DECL.source, 'gi');
        text = text.replace(re, function (all, lead) { return lead === ';' ? ';' : lead; }).replace(/^\s+|\s+$/g, '');
        name = nameClean(name || '');
        if (!name) return text;
        if (text && !/;$/.test(text)) text += ';';
        return (text ? text + '\n' : '') + 'view-transition-name: ' + name + ';';
    }

    // A name made from the element's kind and its section ("hero-picture"),
    // with -2, -3 ... while it is taken.
    function suggest(kind, section, taken) {
        var base = nameClean((section ? section + '-' : '') + (kind || 'element')) || 'element';
        taken = taken || [];
        var name = base;
        for (var i = 2; taken.indexOf(name) !== -1 && i < 100; i++) name = base.slice(0, 36) + '-' + i;
        return name;
    }

    var api = { SIDES: SIDES, clean: clean, fromCss: fromCss, nameClean: nameClean, nameOf: nameOf, withName: withName, suggest: suggest };
    root.PGPageTrans = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : this);
