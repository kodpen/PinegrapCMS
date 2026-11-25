/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Options panel's states (Hover, Focus, Pressed): what a Style row's
 * Bootstrap class means as CSS declarations, and the rule of a state on
 * the element's custom style class in User Styles (`.pgs-xxxxxx:hover`,
 * `:focus-visible`, `:active`). Pure: the text in, the new text out,
 * through PgUserStyles (assets/js/pg_user_styles.js).
 *
 *   PGOptionStates.STATES                       // ['hover', 'focus', 'pressed']
 *   PGOptionStates.selector(cls, st)            // '.pgs-abc123:hover'
 *   PGOptionStates.decls(cls)                   // [[prop, value], ...] or null
 *   PGOptionStates.read(text, cls, st)          // { prop: value }
 *   PGOptionStates.match(map, group)            // the group's class the map holds, or ''
 *   PGOptionStates.holds(map, group)            // true when the map sets a prop of the group
 *   PGOptionStates.write(text, cls, st, group, chosen) // the new text
 *   PGOptionStates.clear(text, cls, st)         // the state's rule removed
 *   PGOptionStates.count(map)                   // the rows the map sets
 *   PGOptionStates.sections(text, cls)          // { colors: [states], border: [...], styling: [...] }
 *   PGOptionStates.transRead(text, cls)         // { what, ms, ease, raw }: the element's transition
 *   PGOptionStates.transWrite(text, cls, t)     // the new text with transition t ({ what, ms, ease })
 *   PGOptionStates.transMaybe(text, cls)        // whether the text may hold one (no parse)
 *
 * The declarations carry !important: the Bootstrap utilities they stand in
 * for carry it too, so a state's value wins over Normal's class.
 *
 * window.PGOptionStates in a browser; module.exports under Node. Plain ES5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var STATES = ['hover', 'focus', 'pressed'];
    var PSEUDO = { hover: ':hover', focus: ':focus-visible', pressed: ':active' };
    var CLASS_RE = /^pgs-[a-z0-9]{6}$/;
    var IMP_RE = /\s*!\s*important\s*$/i;

    // The rows a state writes, by section: each family is one row of the
    // panel and the properties its classes set.
    var FAMILIES = [
        { sect: 'colors', props: ['color'] },
        { sect: 'colors', props: ['background-color'] },
        { sect: 'colors', props: ['background-image'] },
        { sect: 'colors', props: ['opacity'] },
        { sect: 'border', props: ['border', 'border-top', 'border-bottom', 'border-inline-start', 'border-inline-end'] },
        { sect: 'border', props: ['border-color'] },
        { sect: 'border', props: ['border-width'] },
        { sect: 'border', props: ['border-radius'] },
        // The four corner rows share corners (Top and End both round the
        // top right one); a corner set counts once, with the first row.
        { sect: 'border', props: ['border-top-left-radius', 'border-top-right-radius'] },
        { sect: 'border', props: ['border-bottom-right-radius'] },
        { sect: 'border', props: ['border-bottom-left-radius'] },
        { sect: 'styling', props: ['box-shadow'] }
    ];

    // The order the declarations are written in: a shorthand before its
    // longhands, so `border` never undoes `border-color`.
    var ORDER = [
        'color', 'background-color', 'background-image', 'opacity',
        'border', 'border-top', 'border-bottom', 'border-inline-start', 'border-inline-end',
        'border-color', 'border-width',
        'border-radius', 'border-top-left-radius', 'border-top-right-radius',
        'border-bottom-right-radius', 'border-bottom-left-radius',
        'box-shadow'
    ];

    var CORE = { primary: 1, secondary: 1, success: 1, danger: 1, warning: 1, info: 1, light: 1, dark: 1, white: 1, black: 1 };
    var BORDER_LINE = 'var(--bs-border-width) var(--bs-border-style) var(--bs-border-color)';
    var RADIUS = ['0', 'var(--bs-border-radius-sm)', 'var(--bs-border-radius)', 'var(--bs-border-radius-lg)',
        'var(--bs-border-radius-xl)', 'var(--bs-border-radius-xxl)'];
    var CORNERS = {
        top: ['border-top-left-radius', 'border-top-right-radius'],
        end: ['border-top-right-radius', 'border-bottom-right-radius'],
        bottom: ['border-bottom-right-radius', 'border-bottom-left-radius'],
        start: ['border-bottom-left-radius', 'border-top-left-radius']
    };

    function U() { return root.PgUserStyles; }

    function selector(cls, st) {
        return '.' + cls + (PSEUDO[st] || '');
    }

    function textColor(c) {
        if (CORE[c]) return 'var(--bs-' + c + ')';
        var m = /^(primary|secondary|success|danger|warning|info|light|dark)-emphasis$/.exec(c);
        if (m) return 'var(--bs-' + m[1] + '-text-emphasis)';
        return {
            body: 'var(--bs-body-color)', muted: 'var(--bs-secondary-color)',
            'body-secondary': 'var(--bs-secondary-color)', 'body-tertiary': 'var(--bs-tertiary-color)',
            'body-emphasis': 'var(--bs-emphasis-color)'
        }[c] || null;
    }

    function bgColor(c) {
        if (CORE[c]) return 'var(--bs-' + c + ')';
        var m = /^(primary|secondary|success|danger|warning|info|light|dark)-subtle$/.exec(c);
        if (m) return 'var(--bs-' + m[1] + '-bg-subtle)';
        return {
            body: 'var(--bs-body-bg)', 'body-secondary': 'var(--bs-secondary-bg)',
            'body-tertiary': 'var(--bs-tertiary-bg)', transparent: 'transparent'
        }[c] || null;
    }

    function borderColor(c) {
        if (CORE[c]) return 'var(--bs-' + c + ')';
        var m = /^(primary|secondary|success|danger|warning|info|light|dark)-subtle$/.exec(c);
        return m ? 'var(--bs-' + m[1] + '-border-subtle)' : null;
    }

    // The declarations a Style row's class stands for, or null for a class
    // a state cannot write.
    function decls(cls) {
        var c = String(cls || ''), m, v;
        if ((m = /^text-(.+)$/.exec(c)) && (v = textColor(m[1]))) return [['color', v]];
        if (c === 'bg-gradient') return [['background-image', 'var(--bs-gradient)']];
        if ((m = /^bg-(.+)$/.exec(c)) && (v = bgColor(m[1]))) return [['background-color', v]];
        if ((m = /^opacity-(0|25|50|75|100)$/.exec(c))) return [['opacity', String(Number(m[1]) / 100)]];
        if (c === 'border') return [['border', BORDER_LINE]];
        if (c === 'border-0') return [['border', '0']];
        if ((m = /^border-(top|bottom|start|end)$/.exec(c))) {
            return [[m[1] === 'start' ? 'border-inline-start' : m[1] === 'end' ? 'border-inline-end' : 'border-' + m[1], BORDER_LINE]];
        }
        if ((m = /^border-([1-5])$/.exec(c))) return [['border-width', m[1] + 'px']];
        if ((m = /^border-(.+)$/.exec(c)) && (v = borderColor(m[1]))) return [['border-color', v]];
        if ((m = /^rounded-([0-5])$/.exec(c))) return [['border-radius', RADIUS[Number(m[1])]]];
        if (c === 'rounded-circle') return [['border-radius', '50%']];
        if (c === 'rounded-pill') return [['border-radius', 'var(--bs-border-radius-pill)']];
        if ((m = /^rounded-(top|end|bottom|start)-([0-5])$/.exec(c))) {
            return CORNERS[m[1]].map(function (p) { return [p, RADIUS[Number(m[2])]]; });
        }
        if (c === 'shadow-none') return [['box-shadow', 'none']];
        if (c === 'shadow-sm') return [['box-shadow', 'var(--bs-box-shadow-sm)']];
        if (c === 'shadow') return [['box-shadow', 'var(--bs-box-shadow)']];
        if (c === 'shadow-lg') return [['box-shadow', 'var(--bs-box-shadow-lg)']];
        return null;
    }

    // Every property a group's classes set.
    function propsOf(group) {
        var out = {};
        (group || []).forEach(function (c) {
            (decls(c) || []).forEach(function (d) { out[d[0]] = true; });
        });
        return out;
    }

    function ruleOf(text, cls, st) {
        if (!U() || !CLASS_RE.test(cls) || !PSEUDO[st]) return null;
        var sel = selector(cls, st);
        var list = U().parse(String(text || ''));
        for (var i = 0; i < list.length; i++) {
            if (!list[i].media && list[i].selector.replace(/\s+/g, '') === sel) return list[i];
        }
        return null;
    }

    function read(text, cls, st) {
        var rule = ruleOf(text, cls, st);
        var out = {};
        if (rule) rule.decls.forEach(function (d) { out[d.name.toLowerCase()] = d.value.replace(IMP_RE, ''); });
        return out;
    }

    function match(map, group) {
        for (var i = 0; i < (group || []).length; i++) {
            var d = decls(group[i]);
            if (d && d.every(function (x) { return map[x[0]] === x[1]; })) return group[i];
        }
        return '';
    }

    function holds(map, group) {
        var p = propsOf(group);
        return Object.keys(p).some(function (k) { return Object.prototype.hasOwnProperty.call(map, k); });
    }

    function body(list) {
        return '\n' + list.map(function (d) { return '  ' + d[0] + ': ' + d[1] + ';'; }).join('\n') + '\n';
    }

    // The text with the state's rule holding `chosen` for the row whose
    // classes are `group` ('' takes the row's value out). A declaration
    // no row writes stays, before the rows' own. An empty rule goes.
    function write(text, cls, st, group, chosen) {
        text = String(text || '');
        if (!U() || !CLASS_RE.test(cls) || !PSEUDO[st]) return text;
        var add = chosen ? decls(chosen) : [];
        if (add === null) return text;
        var drop = propsOf(group);
        add.forEach(function (d) { drop[d[0]] = true; });
        var rule = ruleOf(text, cls, st);
        var keep = [], known = {};
        if (rule) {
            rule.decls.forEach(function (d) {
                var name = d.name.toLowerCase();
                if (drop[name]) return;
                if (ORDER.indexOf(name) === -1) keep.push([d.name, d.value]);
                else known[name] = d.value.replace(IMP_RE, '');
            });
        }
        add.forEach(function (d) { known[d[0]] = d[1]; });
        ORDER.forEach(function (p) {
            if (Object.prototype.hasOwnProperty.call(known, p)) keep.push([p, known[p] + ' !important']);
        });
        if (!keep.length) return rule ? U().removeRule(text, rule) : text;
        if (rule) return text.slice(0, rule.bodyFrom) + body(keep) + text.slice(rule.bodyTo);
        var kept = text.replace(/\s+$/, '');
        return (kept ? kept + '\n\n' : '') + selector(cls, st) + ' {' + body(keep) + '}\n';
    }

    function clear(text, cls, st) {
        var rule = ruleOf(text, cls, st);
        return rule ? U().removeRule(String(text), rule) : String(text || '');
    }

    function famHeld(map, f) {
        return f.props.some(function (p) { return Object.prototype.hasOwnProperty.call(map, p); });
    }

    // The rows a state's declarations set (a declaration no row writes
    // counts as one row of its own).
    function count(map) {
        var n = 0, seen = {};
        FAMILIES.forEach(function (f) {
            if (famHeld(map, f)) n++;
            f.props.forEach(function (p) { seen[p] = true; });
        });
        Object.keys(map).forEach(function (p) { if (!seen[p]) n++; });
        return n;
    }

    // The states that set a row of each section.
    function sections(text, cls) {
        var out = { colors: [], border: [], styling: [] };
        STATES.forEach(function (st) {
            var map = read(text, cls, st);
            Object.keys(out).forEach(function (s) {
                if (FAMILIES.some(function (f) { return f.sect === s && famHeld(map, f); })) out[s].push(st);
            });
        });
        return out;
    }

    // ── Transition ──
    // One value for the element, not a state's: what changes smoothly,
    // the duration and the easing, as one rule on the custom style class
    // inside `@media (prefers-reduced-motion: no-preference)`, so a
    // visitor who asks for less motion gets none, on the site and on the
    // canvas. Duration and easing follow the look (--pg-duration,
    // --pg-ease) until chosen.
    var TRANS_MEDIA = '@media (prefers-reduced-motion: no-preference)';
    var TRANS_WHAT = {
        colors: 'color, background-color, border-color',
        shadow: 'box-shadow',
        place: 'transform, width, height, inset',
        all: 'all'
    };
    var TRANS_LOOK_MS = 'var(--pg-duration, 0.15s)';
    var TRANS_LOOK_EASE = 'var(--pg-ease, ease-in-out)';
    var EASES = ['ease-out', 'ease', 'ease-in', 'ease-in-out', 'linear'];
    var TRANS_PROPS = ['transition-property', 'transition-duration', 'transition-timing-function'];
    var MS_MAX = 1000;

    function flat(s) { return String(s || '').replace(/\s+/g, '').toLowerCase(); }

    function transRule(text, cls) {
        if (!U() || !CLASS_RE.test(cls)) return null;
        var list = U().parse(String(text || ''));
        for (var i = 0; i < list.length; i++) {
            if (flat(list[i].media) === flat(TRANS_MEDIA) && flat(list[i].selector) === '.' + cls) return list[i];
        }
        return null;
    }

    // A duration in ms from '320ms' or '0.32s'; NaN for anything else.
    function msOf(v) {
        var m = /^(\d+(?:\.\d+)?|\.\d+)(ms|s)$/i.exec(String(v || '').trim());
        if (!m) return NaN;
        return Math.round(Number(m[1]) * (m[2].toLowerCase() === 's' ? 1000 : 1));
    }

    // { what, ms, ease, raw }: what is a TRANS_WHAT key, 'custom' for a
    // property list no row writes (raw holds it), '' for none; ms a number
    // or '' while it follows the look; ease an easing, '' for the look's.
    function transRead(text, cls) {
        var out = { what: '', ms: '', ease: '', raw: '' };
        var rule = transRule(text, cls);
        if (!rule) return out;
        var map = {};
        rule.decls.forEach(function (d) { map[d.name.toLowerCase()] = d.value.trim(); });
        var prop = map['transition-property'];
        if (!prop) return out;
        Object.keys(TRANS_WHAT).forEach(function (k) {
            if (flat(TRANS_WHAT[k]) === flat(prop)) out.what = k;
        });
        if (!out.what) { out.what = 'custom'; out.raw = prop; }
        var ms = msOf(map['transition-duration']);
        if (!isNaN(ms)) out.ms = ms;
        var ease = String(map['transition-timing-function'] || '').trim();
        if (ease && !/^var\(\s*--pg-ease\b/.test(ease)) out.ease = ease;
        return out;
    }

    // A duration as written: a whole number of ms from 0 to 1,000 typed
    // or dragged; '' (or anything else) follows the look.
    function msClean(v) {
        if (v === '' || v === null || v === undefined) return '';
        var n = Math.round(Number(v));
        if (!isFinite(n) || n < 0) return '';
        return Math.min(n, MS_MAX);
    }

    // The text with the element's transition set to `t` ({ what, ms, ease };
    // what '' takes the transition out). A declaration the rows do not
    // write stays in the rule; an @media block left empty goes.
    function transWrite(text, cls, t) {
        text = String(text || '');
        if (!U() || !CLASS_RE.test(cls)) return text;
        t = t || {};
        var rule = transRule(text, cls);
        var was = transRead(text, cls);
        // 'custom' keeps the property list no row writes; a curve no row
        // offers stays while it is the one already written.
        var prop = t.what === 'custom' ? was.raw : (TRANS_WHAT[t.what] || '');
        if (t.what && !prop) return text;
        var keep = [];
        if (rule) {
            rule.decls.forEach(function (d) {
                if (TRANS_PROPS.indexOf(d.name.toLowerCase()) === -1) keep.push([d.name, d.value]);
            });
        }
        if (!prop) {
            if (!rule) return text;
            if (keep.length) return text.slice(0, rule.bodyFrom) + transBody(keep) + text.slice(rule.bodyTo);
            return dropBlock(text, rule);
        }
        var ms = msClean(t.ms);
        var ease = (EASES.indexOf(t.ease) !== -1 || (t.ease && t.ease === was.ease)) ? t.ease : '';
        keep = [
            ['transition-property', prop],
            ['transition-duration', ms === '' ? TRANS_LOOK_MS : ms + 'ms'],
            ['transition-timing-function', ease || TRANS_LOOK_EASE]
        ].concat(keep);
        if (rule) return text.slice(0, rule.bodyFrom) + transBody(keep) + text.slice(rule.bodyTo);
        var kept = text.replace(/\s+$/, '');
        return (kept ? kept + '\n\n' : '') + TRANS_MEDIA + ' {\n  .' + cls + ' {' + transBody(keep) + '}\n}\n';
    }

    function transBody(list) {
        return '\n' + list.map(function (d) { return '    ' + d[0] + ': ' + d[1] + ';'; }).join('\n') + '\n  ';
    }

    // The rule out, with its @media block when nothing else stands in it.
    function dropBlock(text, rule) {
        var at = text.lastIndexOf('@', rule.from);
        var open = at === -1 ? -1 : text.indexOf('{', at);
        var close = text.indexOf('}', rule.to);
        if (open !== -1 && open < rule.from && close !== -1 &&
            !text.slice(open + 1, rule.from).trim() && !text.slice(rule.to, close).trim() &&
            flat(text.slice(at, open)) === flat(TRANS_MEDIA)) {
            text = U().removeRule(text, { from: at, to: close + 1 });
        } else {
            text = U().removeRule(text, rule);
        }
        return text.replace(/\n{2,}$/, '\n');
    }

    // Whether the text may hold a transition for cls: a look, no parse.
    function transMaybe(text, cls) {
        text = String(text || '');
        return !!cls && text.indexOf('prefers-reduced-motion') !== -1 && text.indexOf('.' + cls) !== -1;
    }

    var api = {
        STATES: STATES,
        PSEUDO: PSEUDO,
        selector: selector,
        decls: decls,
        read: read,
        match: match,
        holds: holds,
        write: write,
        clear: clear,
        count: count,
        sections: sections,
        EASES: EASES,
        TRANS_WHAT: TRANS_WHAT,
        transRead: transRead,
        transWrite: transWrite,
        transMaybe: transMaybe
    };

    root.PGOptionStates = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
