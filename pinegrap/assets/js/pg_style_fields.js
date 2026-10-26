/**
 * Pinegrap - Enterprise Website Platform
 *
 * The text side of the smart completion in the designer's panel inputs
 * (the Attributes panel's attribute rows, the Styles panel's property,
 * value and selector fields): which part of a field the caret is in, what
 * the predictor (assets/js/pg_style_predict.js) is asked for it, and the
 * field's text once a suggestion is picked. No document access; the
 * dropdown is PgStyleSuggest.dropdown().
 *
 *   PgStyleFields.suggest(P, spec, text, caret, ctx);  // { from, to, part, items }
 *   PgStyleFields.apply(text, res, label);             // { text, caret }
 *   PgStyleFields.declAt(text, caret);                 // the declaration under the caret
 *   PgStyleFields.wordAt(text, caret, lo, hi);         // the value word under the caret
 *   PgStyleFields.cssTokens(css);                      // { '--name': value }
 *
 * spec.kind is 'property', 'value' (spec.name: the property), 'selector',
 * 'attribute', 'attrValue' (spec.name: the attribute) or 'style' (a whole
 * declaration list, as in a style attribute).
 *
 * window.PgStyleFields in a browser; module.exports under Node. Plain ES5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    function isSpace(ch) { return /\s/.test(ch); }

    // The value word around the caret, between lo and hi: words end at
    // white space and commas, so "1px solid re" asks for "re" and
    // "var(--bs-pri" stays one word.
    function wordAt(text, caret, lo, hi) {
        text = String(text || '');
        lo = lo === undefined ? 0 : lo;
        hi = hi === undefined ? text.length : hi;
        caret = Math.max(lo, Math.min(hi, caret === undefined || caret === null ? hi : caret));
        var stop = function (ch) { return isSpace(ch) || ch === ','; };
        var from = caret, to = caret;
        while (from > lo && !stop(text.charAt(from - 1))) from--;
        while (to < hi && !stop(text.charAt(to))) to++;
        return { from: from, to: to, query: text.slice(from, caret) };
    }

    function trimRange(text, from, to) {
        while (from < to && isSpace(text.charAt(from))) from++;
        while (to > from && isSpace(text.charAt(to - 1))) to--;
        return { from: from, to: to };
    }

    // A value of several words ("var(--bs-border-width) solid …") is the
    // whole value: offered only while the first word is typed, and a pick
    // replaces the whole value.
    function valueItems(items, res, wordFrom) {
        if (wordFrom <= res.valueFrom) return items;
        return items.filter(function (it) { return !/\s/.test(it.label); });
    }

    // The declarations of a style list with their offsets. Semicolons and
    // colons inside brackets or quotes (a data: URL, a font name) belong to
    // the value, and a new line ends a declaration too (the custom style
    // field holds one per line); while a bracket is still open ("var(--bs-bor"), brackets
    // are not counted, or the declarations after it would join the value.
    function splitDecls(text, brackets) {
        if (brackets === undefined) brackets = true;
        var out = [], start = 0, depth = 0, quote = '', colon = -1;
        for (var i = 0; i < text.length; i++) {
            var ch = text.charAt(i);
            if (quote) {
                if (ch === '\\') { i++; continue; }
                if (ch === quote) quote = '';
                continue;
            }
            if (ch === '"' || ch === "'") quote = ch;
            else if (brackets && ch === '(') depth++;
            else if (brackets && ch === ')') { if (depth) depth--; }
            else if (depth === 0 && ch === ':' && colon < 0) colon = i;
            else if (depth === 0 && (ch === ';' || ch === '\n')) { out.push({ start: start, end: i, colon: colon }); start = i + 1; colon = -1; }
        }
        if (brackets && depth > 0) return splitDecls(text, false);
        out.push({ start: start, end: text.length, colon: colon });
        return out;
    }

    // The declaration under the caret in a style list: part 'name' (from
    // the name's start to its end, the colon not included) or 'value' (the
    // value word under the caret) with the property; styles holds the
    // other declarations, so the predictor leaves out what is already set.
    function declAt(text, caret) {
        text = String(text || '');
        caret = Math.max(0, Math.min(text.length, caret === undefined || caret === null ? text.length : caret));
        var decls = splitDecls(text);
        var cur = decls[decls.length - 1];
        for (var i = 0; i < decls.length; i++) {
            if (caret <= decls[i].end) { cur = decls[i]; break; }
        }
        var styles = {};
        decls.forEach(function (d) {
            if (d === cur || d.colon < 0) return;
            var name = text.slice(d.start, d.colon).trim().toLowerCase();
            if (name) styles[name] = text.slice(d.colon + 1, d.end).trim();
        });
        if (cur.colon < 0 || caret <= cur.colon) {
            var from = cur.start;
            while (from < caret && isSpace(text.charAt(from))) from++;
            var to = cur.colon < 0 ? cur.end : cur.colon;
            while (to > caret && isSpace(text.charAt(to - 1))) to--;
            return { part: 'name', from: from, to: to, query: text.slice(from, caret),
                     colon: cur.colon >= 0, styles: styles };
        }
        var w = wordAt(text, caret, cur.colon + 1, cur.end);
        var v = trimRange(text, cur.colon + 1, cur.end);
        return { part: 'value', name: text.slice(cur.start, cur.colon).trim().toLowerCase(),
                 from: w.from, to: w.to, query: w.query, valueFrom: v.from, valueTo: v.to, styles: styles };
    }

    // The custom properties a style sheet declares, the last one winning.
    function cssTokens(css) {
        var out = {};
        var text = String(css || '').replace(/\/\*[\s\S]*?\*\//g, '');
        var re = /(?:^|[;{\s])(--[A-Za-z0-9_-]+)\s*:\s*([^;{}]*)/g, m;
        while ((m = re.exec(text))) {
            var v = m[2].trim();
            if (v) out[m[1]] = v;
        }
        return out;
    }

    function copyCtx(ctx, styles) {
        var c = {};
        for (var k in ctx) if (Object.prototype.hasOwnProperty.call(ctx, k)) c[k] = ctx[k];
        if (styles) c.styles = styles;
        return c;
    }

    // What to offer for a field: the predictor's list and the range a pick
    // replaces. A field that holds one thing (a property, a selector, an
    // attribute name or value) is replaced whole; a value only in the word
    // under the caret.
    function suggest(P, spec, text, caret, ctx) {
        text = String(text || '');
        spec = spec || {};
        ctx = ctx || {};
        caret = caret === undefined || caret === null ? text.length : caret;
        var res = { from: 0, to: text.length, part: spec.kind, items: [] };
        if (!P) return res;
        var whole = text.slice(0, caret).replace(/^\s+/, '');
        switch (spec.kind) {
            case 'property':
                res.items = P.properties(whole, ctx);
                break;
            case 'selector':
                res.items = P.selectors(whole, ctx);
                break;
            case 'attribute':
                res.items = P.attributes(whole, ctx);
                break;
            case 'attrValue':
                res.items = P.attributeValues(spec.name, whole, ctx);
                break;
            case 'value':
                var w = wordAt(text, caret);
                var v = trimRange(text, 0, text.length);
                res.from = w.from;
                res.to = w.to;
                res.valueFrom = v.from;
                res.valueTo = v.to;
                res.items = valueItems(P.values(spec.name, w.query, ctx), res, w.from);
                break;
            case 'style':
                var d = declAt(text, caret);
                res.from = d.from;
                res.to = d.to;
                res.part = d.part;
                if (d.part === 'name') {
                    res.colon = d.colon;
                    res.items = P.properties(d.query, copyCtx(ctx, d.styles));
                } else {
                    res.valueFrom = d.valueFrom;
                    res.valueTo = d.valueTo;
                    res.items = valueItems(P.values(d.name, d.query, copyCtx(ctx, d.styles)), res, d.from);
                }
                break;
        }
        return res;
    }

    // The field's text after a pick, and where the caret goes. A property
    // picked in a style list gets its colon, so the values come next.
    function apply(text, res, label) {
        text = String(text || '');
        var insert = String(label);
        var from = res.from, to = res.to;
        if (/\s/.test(insert) && res.valueFrom !== undefined) {
            from = res.valueFrom;
            to = res.valueTo;
        }
        if (res.part === 'name') {
            // The colon already there, and one space after it, are replaced.
            var j = to;
            while (j < text.length && isSpace(text.charAt(j))) j++;
            if (text.charAt(j) === ':') {
                to = j + 1;
                if (text.charAt(to) === ' ') to++;
            }
            insert += ': ';
        }
        var next = text.slice(0, from) + insert + text.slice(to);
        return { text: next, caret: from + insert.length };
    }

    var api = {
        suggest: suggest,
        apply: apply,
        declAt: declAt,
        wordAt: wordAt,
        cssTokens: cssTokens
    };

    root.PgStyleFields = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
