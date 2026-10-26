/**
 * Pinegrap - Enterprise Website Platform
 *
 * User Styles text helpers: window.PgUserStyles.
 *
 * The designer's User Styles is one CSS text the operator, the Styles panel
 * and the design templates all write. The Styles panel shows its style
 * rules as cards; an edit on a card changes only that rule's or that
 * declaration's characters in the text, so everything the cards do not show
 * (comments, @font-face, @keyframes, nested rules, the formatting) stays as
 * it was written. Rules inside @media, @supports, @container and @layer are
 * listed with that wrapper as their `media`.
 *
 *   PgUserStyles.parse(text) → [rule]
 *     rule: { selector, decls: [{ name, value, from, to, semi }], media,
 *             from, to, selFrom, selTo, bodyFrom, bodyTo }
 *     from / to are offsets into text (to is exclusive); a decl's range is
 *     `name: value` without its `;` (semi: the `;` offset or -1).
 *   setSelector(text, rule, selector), removeRule(text, rule),
 *   setDecl(text, rule, index, name, value), removeDecl(text, rule, index),
 *   addDecl(text, rule, name, value), appendRule(text, selector)
 *     → the new text. A rule passed in comes from parse() of that same text.
 *
 * Pure functions, no DOM: tests/designer_user_styles_test.php runs them
 * under Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    if (root.PgUserStyles) return;

    // At-rules whose block holds style rules the cards may show.
    var GROUPS = { media: 1, supports: 1, container: 1, layer: 1 };

    // The offset just past a comment or string starting at i, or i when
    // neither starts there.
    function _skipAtom(text, i) {
        var c = text.charAt(i);
        if (c === '/' && text.charAt(i + 1) === '*') {
            var end = text.indexOf('*/', i + 2);
            return (end === -1) ? text.length : end + 2;
        }
        if (c === '"' || c === "'") {
            for (var j = i + 1; j < text.length; j++) {
                var d = text.charAt(j);
                if (d === '\\') { j++; continue; }
                if (d === c || d === '\n') return j + 1;
            }
            return text.length;
        }
        return i;
    }

    // The first of `stops` at depth 0 (outside comments, strings, brackets)
    // in [from, end), or -1.
    function _find(text, from, end, stops) {
        var depth = 0;
        for (var i = from; i < end; i++) {
            var next = _skipAtom(text, i);
            if (next !== i) { i = next - 1; continue; }
            var c = text.charAt(i);
            if (c === '(' || c === '[') depth++;
            else if ((c === ')' || c === ']') && depth > 0) depth--;
            else if (depth === 0 && stops.indexOf(c) !== -1) return i;
        }
        return -1;
    }

    // The offset of the `}` closing the block opened at `open`, or -1.
    function _close(text, open, end) {
        var depth = 0;
        for (var i = open; i < end; i++) {
            var next = _skipAtom(text, i);
            if (next !== i) { i = next - 1; continue; }
            var c = text.charAt(i);
            if (c === '{') depth++;
            else if (c === '}' && --depth === 0) return i;
        }
        return -1;
    }

    // [from, to) without the white space and comments at either end.
    function _trim(text, from, to) {
        for (;;) {
            while (from < to && /\s/.test(text.charAt(from))) from++;
            if (text.substr(from, 2) !== '/*') break;
            var end = _skipAtom(text, from);
            if (end > to) break;
            from = end;
        }
        while (to > from && /\s/.test(text.charAt(to - 1))) to--;
        return [from, to];
    }

    function _decls(text, from, to) {
        var out = [];
        var at = from;
        while (at < to) {
            var semi = _find(text, at, to, [';']);
            var stop = (semi === -1) ? to : semi;
            var r = _trim(text, at, stop);
            var colon = (r[0] < r[1]) ? _find(text, r[0], r[1], [':']) : -1;
            if (colon !== -1) {
                var name = text.slice(r[0], colon).trim();
                if (name) {
                    out.push({
                        name: name, value: text.slice(colon + 1, r[1]).trim(),
                        from: r[0], to: r[1], semi: semi
                    });
                }
            }
            if (semi === -1) break;
            at = semi + 1;
        }
        return out;
    }

    function _scan(text, from, end, media, rules) {
        var at = from;
        while (at < end) {
            var stop = _find(text, at, end, ['{', ';', '}']);
            if (stop === -1) return;
            var c = text.charAt(stop);
            if (c !== '{') { at = stop + 1; continue; }
            var close = _close(text, stop, end);
            if (close === -1) return;   // an unclosed block: nothing after it is a rule
            var pre = _trim(text, at, stop);
            var prelude = text.slice(pre[0], pre[1]);
            if (prelude.charAt(0) === '@') {
                var m = /^@([\w-]+)/.exec(prelude);
                if (m && GROUPS[m[1].toLowerCase()]) {
                    var label = prelude.replace(/\s+/g, ' ');
                    _scan(text, stop + 1, close, media ? media + ' ' + label : label, rules);
                }
            } else if (prelude && _find(text, stop + 1, close, ['{']) === -1) {
                rules.push({
                    selector: prelude, decls: _decls(text, stop + 1, close), media: media,
                    from: pre[0], to: close + 1, selFrom: pre[0], selTo: pre[1],
                    bodyFrom: stop + 1, bodyTo: close
                });
            }
            at = close + 1;
        }
    }

    function parse(text) {
        text = String(text || '');
        var rules = [];
        _scan(text, 0, text.length, '', rules);
        return rules;
    }

    function _splice(text, from, to, insert) {
        return text.slice(0, from) + insert + text.slice(to);
    }

    // The white space before `pos` on its line, or null when the line has
    // other text before it.
    function _indentAt(text, pos) {
        var start = text.lastIndexOf('\n', pos - 1) + 1;
        var lead = text.slice(start, pos);
        return /^[ \t]*$/.test(lead) ? lead : null;
    }

    // [from, to) widened to whole lines when nothing else shares them.
    function _wholeLines(text, from, to) {
        var lead = _indentAt(text, from);
        var nl = text.indexOf('\n', to);
        var tail = text.slice(to, (nl === -1) ? text.length : nl);
        if (lead !== null && /^[ \t]*$/.test(tail)) {
            return [from - lead.length, (nl === -1) ? text.length : nl + 1];
        }
        while (to < text.length && (text.charAt(to) === ' ' || text.charAt(to) === '\t')) to++;
        return [from, to];
    }

    function setSelector(text, rule, selector) {
        return _splice(String(text), rule.selFrom, rule.selTo, String(selector).trim());
    }

    function removeRule(text, rule) {
        text = String(text);
        var r = _wholeLines(text, rule.from, rule.to);
        var out = _splice(text, r[0], r[1], '');
        // One blank line between the neighbours, not two.
        var j = r[0];
        while (out.charAt(j) === '\n' && out.charAt(j - 1) === '\n' && out.charAt(j - 2) === '\n') {
            out = out.slice(0, j) + out.slice(j + 1);
        }
        return out;
    }

    function setDecl(text, rule, index, name, value) {
        var d = rule.decls[index];
        if (!d) return String(text);
        return _splice(String(text), d.from, d.to, String(name).trim() + ': ' + String(value).trim());
    }

    function removeDecl(text, rule, index) {
        text = String(text);
        var d = rule.decls[index];
        if (!d) return text;
        var r = _wholeLines(text, d.from, (d.semi === -1) ? d.to : d.semi + 1);
        return _splice(text, r[0], r[1], '');
    }

    function addDecl(text, rule, name, value) {
        text = String(text);
        var decl = String(name).trim() + ': ' + String(value).trim() + ';';
        var body = _trim(text, rule.bodyFrom, rule.bodyTo);
        var ruleIndent = _indentAt(text, rule.from) || '';
        if (body[0] === body[1]) {
            return _splice(text, rule.bodyFrom, rule.bodyTo, '\n' + ruleIndent + '  ' + decl + '\n' + ruleIndent);
        }
        var last = rule.decls[rule.decls.length - 1];
        var semi = (last && last.semi === -1 && last.to === body[1]) ? ';' : '';
        if (text.slice(rule.bodyFrom, rule.bodyTo).indexOf('\n') === -1) {
            // A rule written on one line stays on one line.
            return _splice(text, body[1], body[1], semi + ' ' + decl);
        }
        var indent = (last && _indentAt(text, last.from)) || (ruleIndent + '  ');
        return _splice(text, body[1], body[1], semi + '\n' + indent + decl);
    }

    function appendRule(text, selector) {
        var kept = String(text || '').replace(/\s+$/, '');
        return (kept ? kept + '\n\n' : '') + String(selector).trim() + ' {\n  \n}\n';
    }

    root.PgUserStyles = {
        parse: parse,
        setSelector: setSelector,
        removeRule: removeRule,
        setDecl: setDecl,
        removeDecl: removeDecl,
        addDecl: addDecl,
        appendRule: appendRule
    };
})(typeof window !== 'undefined' ? window : this);
