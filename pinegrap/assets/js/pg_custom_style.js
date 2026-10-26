/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer's per-element custom style: one User Styles rule keyed by a
 * generated class (`pgs-` and six characters) the element carries. This
 * file reads and writes that rule in the User Styles text through
 * PgUserStyles (assets/js/pg_user_styles.js); the designer adds the class
 * to the element and takes the undo step.
 *
 *   PgCustomStyle.classOf(cssClass)            // the element's pgs- class or ''
 *   PgCustomStyle.newClass(taken, rand)        // a pgs- class not in taken
 *   PgCustomStyle.find(text, cls)              // its base rule or null
 *   PgCustomStyle.body(text, cls)              // the base rule's declarations as text
 *   PgCustomStyle.write(text, cls, body)       // the new User Styles text
 *   PgCustomStyle.split(text, cls, next)       // a copy of the rule for next
 *   PgCustomStyle.users(classLists, cls)       // how many elements carry cls
 *   PgCustomStyle.warnings(body)               // codes: 'important', 'brace'
 *
 * The base rule is the one whose selector is the class alone, outside any
 * @media; rules for the class inside @media stay as written and come after
 * it, so they keep winning at their widths.
 *
 * window.PgCustomStyle in a browser; module.exports under Node. Plain ES5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var CLASS_RE = /^pgs-[a-z0-9]{6}$/;
    var CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789';
    var INDENT = '  ';

    function U() { return root.PgUserStyles; }

    function names(cssClass) {
        return (typeof cssClass === 'string' ? cssClass.split(/\s+/) : (cssClass || [])).filter(Boolean);
    }

    function classOf(cssClass) {
        var list = names(cssClass);
        for (var i = 0; i < list.length; i++) if (CLASS_RE.test(list[i])) return list[i];
        return '';
    }

    // taken: { class: true } or a list. rand() as Math.random.
    function newClass(taken, rand) {
        var used = {};
        if (Array.isArray(taken)) taken.forEach(function (c) { used[c] = true; });
        else if (taken) used = taken;
        rand = rand || Math.random;
        for (var tries = 0; tries < 1000; tries++) {
            var cls = 'pgs-';
            for (var i = 0; i < 6; i++) cls += CHARS.charAt(Math.floor(rand() * CHARS.length) % CHARS.length);
            if (!used[cls]) return cls;
        }
        return '';
    }

    function rules(text) { return U() ? U().parse(String(text || '')) : []; }

    function find(text, cls) {
        var list = rules(text);
        for (var i = 0; i < list.length; i++) {
            if (!list[i].media && list[i].selector.trim() === '.' + cls) return list[i];
        }
        return null;
    }

    // The declarations as the field shows them: one per line, without the
    // rule's indentation. Comments and formatting inside a line stay.
    function body(text, cls) {
        var rule = find(text, cls);
        if (!rule) return '';
        return String(text).slice(rule.bodyFrom, rule.bodyTo).split('\n').map(function (l) {
            return l.trim();
        }).filter(Boolean).join('\n');
    }

    // A line ends its declaration when the next line starts a new one (or
    // it is the last); it gets its semicolon then, before a comment that
    // closes the line. A value going on over lines (a font list ending in a
    // comma) is left as it is.
    function endDecl(line, next) {
        if (next !== undefined && !/^(--)?[A-Za-z-]+\s*:/.test(next)) return line;
        var m = /^(.*?)(\s*\/\*(?:(?!\*\/).)*\*\/\s*)$/.exec(line);
        var code = m ? m[1] : line, tail = m ? m[2] : '';
        if (!code.trim() || /[;,(]\s*$/.test(code)) return line;
        return code.replace(/\s+$/, '') + ';' + tail;
    }

    function format(value) {
        var lines = String(value || '').split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
        lines = lines.map(function (l, i) { return endDecl(l, lines[i + 1]); });
        return '\n' + lines.map(function (l) { return INDENT + l; }).join('\n') + '\n';
    }

    // Where a new base rule goes: before the first rule for the class
    // inside @media (at that at-rule's start), else at the end.
    function insertAt(text, cls) {
        var re = new RegExp('\\.' + cls + '(?![\\w-])');
        var list = rules(text);
        for (var i = 0; i < list.length; i++) {
            if (!list[i].media || !re.test(list[i].selector)) continue;
            var at = String(text).lastIndexOf('@', list[i].from);
            if (at >= 0) return at;
        }
        return -1;
    }

    // A brace outside strings and comments would end the rule early and
    // break the rest of the stylesheet.
    function hasBrace(value) {
        return /[{}]/.test(String(value || '').replace(/\/\*[\s\S]*?\*\//g, '').replace(/"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'/g, ''));
    }

    // The User Styles text with the base rule of cls holding value; an empty
    // value removes the rule. A value with a brace is refused (the text
    // comes back as it was).
    function write(text, cls, value) {
        text = String(text || '');
        if (!CLASS_RE.test(cls) || hasBrace(value)) return text;
        var empty = !String(value || '').trim();
        var rule = find(text, cls);
        if (rule) {
            if (empty) return U().removeRule(text, rule);
            return text.slice(0, rule.bodyFrom) + format(value) + text.slice(rule.bodyTo);
        }
        if (empty) return text;
        var block = '.' + cls + ' {' + format(value) + '}\n';
        var at = insertAt(text, cls);
        if (at >= 0) return text.slice(0, at) + block + '\n' + text.slice(at);
        var kept = text.replace(/\s+$/, '');
        return (kept ? kept + '\n\n' : '') + block;
    }

    // A copy of cls's base rule for next, so an element that shared the
    // style gets its own.
    function split(text, cls, next) {
        return write(text, next, body(text, cls));
    }

    function users(classLists, cls) {
        var n = 0;
        (classLists || []).forEach(function (l) { if (names(l).indexOf(cls) !== -1) n++; });
        return n;
    }

    function warnings(value) {
        var out = [];
        if (/!\s*important/i.test(String(value || ''))) out.push('important');
        if (hasBrace(value)) out.push('brace');
        return out;
    }

    var api = {
        CLASS_RE: CLASS_RE,
        classOf: classOf,
        newClass: newClass,
        find: find,
        body: body,
        write: write,
        split: split,
        users: users,
        warnings: warnings
    };

    root.PgCustomStyle = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
