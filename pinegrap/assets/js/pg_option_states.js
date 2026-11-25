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
 *   PGOptionStates.tfRead(text, cls, st)        // { tx, ty, s, r, skx, sky, o }: the transform ('' st: Normal)
 *   PGOptionStates.tfWrite(text, cls, st, key, v) // the new text with one transform value set
 *   PGOptionStates.tfMaybe(text, cls)           // whether the text may hold one (no parse)
 *   PGOptionStates.fxRead(text, cls, st)        // { blur, bright, contrast, gray, sat, sepia, hue, bdblur, blend, bgblend }
 *   PGOptionStates.fxWrite(text, cls, st, key, v) // the new text with one effect value set
 *   PGOptionStates.fxFilter(vals)               // the filter value of a set of Filter values
 *   PGOptionStates.fxMaybe(text, cls)           // whether the text may hold one (no parse)
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
        { sect: 'styling', props: ['box-shadow'] },
        // Transform: Move, Scale, Rotate, Skew, Origin (tfWrite()).
        { sect: 'styling', props: ['translate'] },
        { sect: 'styling', props: ['scale'] },
        { sect: 'styling', props: ['rotate'] },
        { sect: 'styling', props: ['transform'] },
        { sect: 'styling', props: ['transform-origin'] },
        // Effects: Filter, Background blur (with its prefixed twin), Blend
        // and Background blend (fxWrite()).
        { sect: 'styling', props: ['filter'] },
        { sect: 'styling', props: ['backdrop-filter', '-webkit-backdrop-filter'] },
        { sect: 'styling', props: ['mix-blend-mode'] },
        { sect: 'styling', props: ['background-blend-mode'] }
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
        place: 'transform, translate, rotate, scale, width, height, inset',
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
        // Size and place as written before it named the transform's own
        // properties (translate, rotate, scale).
        if (flat(prop) === 'transform,width,height,inset') out.what = 'place';
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


    // ── Transform ──
    // Move, Scale and Rotate as the element's own `translate`, `scale` and
    // `rotate`, Skew as `transform: skew()`, Origin as `transform-origin`,
    // on the custom style class (Normal) or the state's rule. The separate
    // properties compose with an Interactions effect's inline `transform`
    // instead of being replaced by it (pg_fx.src.js keeps the element's
    // own transform under its effect). Written without !important, so the
    // effect's inline value still applies. A value this model cannot read
    // (a translate in three axes, a transform other than a skew) is left as
    // it is: the row reads empty and a change of another row keeps it.
    var ORIGINS = ['0% 0%', '50% 0%', '100% 0%', '0% 50%', '50% 50%', '100% 50%', '0% 100%', '50% 100%', '100% 100%'];
    var TF_KEYS = { tx: 'translate', ty: 'translate', s: 'scale', r: 'rotate', skx: 'transform', sky: 'transform', o: 'transform-origin' };
    var NUM = '(-?(?:\\d+\\.?\\d*|\\.\\d+))';
    var LEN_RE = new RegExp('^' + NUM + '(px|%|rem|em|vw|vh)?$', 'i');
    var DEG_RE = new RegExp('^' + NUM + 'deg$', 'i');
    var SKEW_RE = new RegExp('^skew\\(\\s*' + NUM + 'deg\\s*(?:,\\s*' + NUM + 'deg\\s*)?\\)$', 'i');

    function tfRule(text, cls, st) {
        if (!st) {
            if (!U() || !CLASS_RE.test(cls)) return null;
            var list = U().parse(String(text || ''));
            for (var i = 0; i < list.length; i++) {
                if (!list[i].media && flat(list[i].selector) === '.' + cls) return list[i];
            }
            return null;
        }
        return ruleOf(text, cls, st);
    }

    function tfDecls(rule) {
        var map = {};
        if (rule) rule.decls.forEach(function (d) { map[d.name.toLowerCase()] = d.value.replace(IMP_RE, '').trim(); });
        return map;
    }

    // A length as written: a bare number is px; '' for anything else.
    function lenClean(v) {
        var m = LEN_RE.exec(String(v === undefined || v === null ? '' : v).trim());
        if (!m) return '';
        return Number(m[1]) + (m[2] ? m[2].toLowerCase() : 'px');
    }

    function numClean(v, lo, hi, dp) {
        if (v === '' || v === null || v === undefined) return '';
        var n = Number(String(v).trim());
        if (!isFinite(n)) return '';
        var f = Math.pow(10, dp);
        return Math.round(Math.min(hi, Math.max(lo, n)) * f) / f;
    }

    // { tx, ty, s, r, skx, sky, o }: lengths as written ('-2px'), numbers
    // for scale and the degrees, the index of the Origin place (0 to 8);
    // '' where the rule holds none.
    function tfRead(text, cls, st) {
        var out = { tx: '', ty: '', s: '', r: '', skx: '', sky: '', o: '' };
        var map = tfDecls(tfRule(text, cls, st));
        var m;
        if (map.translate) {
            var p = map.translate.split(/\s+/);
            if (p.length <= 2 && lenClean(p[0]) && (p.length === 1 || lenClean(p[1]))) {
                out.tx = lenClean(p[0]);
                out.ty = p.length === 2 ? lenClean(p[1]) : '0px';
            }
        }
        if (map.scale && /^\S+$/.test(map.scale) && isFinite(Number(map.scale))) out.s = Number(map.scale);
        if (map.rotate && (m = DEG_RE.exec(map.rotate))) out.r = Number(m[1]);
        if (map.transform && (m = SKEW_RE.exec(map.transform))) {
            out.skx = Number(m[1]);
            out.sky = m[2] === undefined ? 0 : Number(m[2]);
        }
        if (map['transform-origin']) {
            var o = ORIGINS.indexOf(String(map['transform-origin']).replace(/\s+/g, ' '));
            if (o !== -1) out.o = o;
        }
        return out;
    }

    // The declaration a row's key belongs to, with the value `v` set;
    // value '' takes it out. Under a state the axis not set falls back to
    // Normal's, and a pair equal to Normal's goes.
    function tfValue(cur, base, st, key, v) {
        var pair = function (a, b, unit, zero) {
            var x = key === a ? v : cur[a];
            var y = key === b ? v : cur[b];
            if (st) {
                if (x === '') x = base[a];
                if (y === '') y = base[b];
                if (String(x) === String(base[a]) && String(y) === String(base[b])) return '';
            }
            x = x === '' ? zero : x;
            y = y === '' ? zero : y;
            if (!st && String(x) === zero && String(y) === zero) return '';
            return [x, y, unit];
        };
        if (key === 'tx' || key === 'ty') {
            var t = pair('tx', 'ty', '', '0px');
            return t ? t[0] + ' ' + t[1] : '';
        }
        if (key === 'skx' || key === 'sky') {
            var k = pair('skx', 'sky', 'deg', '0');
            return k ? 'skew(' + k[0] + 'deg, ' + k[1] + 'deg)' : '';
        }
        if (v === '') return '';
        if (key === 's') return (!st && v === 1) ? '' : String(v);
        if (key === 'r') return (!st && v === 0) ? '' : v + 'deg';
        return (!st && v === 4) ? '' : ORIGINS[v];
    }

    // The text with one transform value of the state `st` ('' Normal) set:
    // key tx or ty (a length, a bare number is px), s (0 to 10), r (−360 to
    // 360), skx or sky (−89 to 89), o (an Origin place, 0 to 8); '' takes
    // the value out. A state's rule left empty goes; Normal's stays.
    function tfWrite(text, cls, st, key, value) {
        text = String(text || '');
        var prop = TF_KEYS[key];
        if (!U() || !CLASS_RE.test(cls) || !prop || (st && !PSEUDO[st])) return text;
        var v = value;
        if (v !== '' && v !== null && v !== undefined) {
            if (key === 'tx' || key === 'ty') v = lenClean(v);
            else if (key === 's') v = numClean(v, 0, 10, 3);
            else if (key === 'r') v = numClean(v, -360, 360, 2);
            else if (key === 'o') v = (/^[0-8]$/.test(String(v))) ? Number(v) : '';
            else v = numClean(v, -89, 89, 2);
            if (v === '') return text;
        } else {
            v = '';
        }
        var rule = tfRule(text, cls, st);
        var map = tfDecls(rule);
        // A value no row reads is not written over.
        if (prop === 'transform' && map.transform && !SKEW_RE.test(map.transform)) return text;
        var cur = tfRead(text, cls, st);
        if (prop === 'translate' && map.translate && cur.tx === '') return text;
        var base = st ? tfRead(text, cls, '') : cur;
        var val = tfValue(cur, base, st, key, v);
        var out = tfPut(text, cls, st, prop, val);
        // A state's pair keeps following Normal on the axis it took from it.
        if (!st && (prop === 'translate' || prop === 'transform')) {
            var a = prop === 'translate' ? 'tx' : 'skx', b = prop === 'translate' ? 'ty' : 'sky';
            var zero = prop === 'translate' ? '0px' : '0';
            var or0 = function (x) { return x === '' ? zero : String(x); };
            var now = tfRead(out, cls, '');
            STATES.forEach(function (s2) {
                var sc = tfRead(out, cls, s2);
                if (sc[a] === '') return;
                var c2 = {};
                c2[a] = or0(sc[a]) === or0(base[a]) ? or0(now[a]) : sc[a];
                c2[b] = or0(sc[b]) === or0(base[b]) ? or0(now[b]) : sc[b];
                out = tfPut(out, cls, s2, prop, tfValue(c2, now, s2, a, c2[a]));
            });
        }
        return out;
    }

    // The declaration `prop: val` in the rule of `st`; val '' takes it out.
    function tfPut(text, cls, st, prop, val) {
        var rule = tfRule(text, cls, st);
        var idx = -1;
        if (rule) {
            rule.decls.forEach(function (d, i) { if (d.name.toLowerCase() === prop) idx = i; });
        }
        if (!val) {
            if (idx === -1) return text;
            if (st && rule.decls.length === 1) return U().removeRule(text, rule).replace(/\n{2,}$/, '\n');
            return U().removeDecl(text, rule, idx);
        }
        if (idx !== -1) return U().setDecl(text, rule, idx, prop, val);
        if (!rule) {
            text = U().appendRule(text, st ? selector(cls, st) : '.' + cls);
            rule = tfRule(text, cls, st);
            if (!rule) return text;
        }
        return U().addDecl(text, rule, prop, val);
    }

    // Whether the text may hold a transform for cls: a look, no parse.
    function tfMaybe(text, cls) {
        text = String(text || '');
        return !!cls && text.indexOf('.' + cls) !== -1 && /(?:^|[\s;{])(?:translate|rotate|scale|transform|transform-origin)\s*:/.test(text);
    }

    // ── Effects: Filter, Background blur, Blend ──
    // Filter's seven sliders are one `filter` declaration, written in a fixed
    // order with Normal's defaults left out; a state's rule holds the whole
    // list (a state's `filter` replaces Normal's), and a value of it equal
    // to Normal's reads as not set. Background blur is `backdrop-filter:
    // blur()` with its -webkit- twin (Safari before 18); Blend and
    // Background blend are mix-blend-mode and background-blend-mode. A
    // filter no row reads (a drop-shadow, a url()) is left as it is.
    var FX_FILTER = [
        // key, CSS function, unit, default, lowest, highest
        ['blur', 'blur', 'px', 0, 0, 100],
        ['bright', 'brightness', '%', 100, 0, 300],
        ['contrast', 'contrast', '%', 100, 0, 300],
        ['gray', 'grayscale', '%', 0, 0, 100],
        ['sat', 'saturate', '%', 100, 0, 300],
        ['sepia', 'sepia', '%', 0, 0, 100],
        ['hue', 'hue-rotate', 'deg', 0, -180, 180]
    ];
    var BLENDS = ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn',
        'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity'];
    var FX_NONE = function () {
        return { blur: '', bright: '', contrast: '', gray: '', sat: '', sepia: '', hue: '', bdblur: '', blend: '', bgblend: '' };
    };

    function fxSpec(key) {
        for (var i = 0; i < FX_FILTER.length; i++) if (FX_FILTER[i][0] === key) return FX_FILTER[i];
        return null;
    }

    // { key: number } of a filter value, or null when a function in it is
    // not one of the seven (or not in the unit the row writes).
    function fxParse(v) {
        var out = {}, rest = String(v || '').trim();
        if (rest === '' || rest === 'none') return out;
        var re = /^([a-z-]+)\(\s*(-?(?:\d+\.?\d*|\.\d+))(px|%|deg)?\s*\)\s*/i, m;
        while (rest !== '') {
            if (!(m = re.exec(rest))) return null;
            var spec = null;
            FX_FILTER.forEach(function (f) { if (f[1] === m[1].toLowerCase()) spec = f; });
            var unit = (m[3] || '').toLowerCase();
            if (!spec || (unit !== spec[2] && !(unit === '' && m[2] === '0'))) return null;
            out[spec[0]] = Number(m[2]);
            rest = rest.slice(m[0].length);
        }
        return out;
    }

    // The filter value of a set of Filter values ('' leaves one out).
    function fxFilter(vals) {
        return FX_FILTER.filter(function (f) { return vals[f[0]] !== '' && vals[f[0]] !== undefined && vals[f[0]] !== null; })
            .map(function (f) { return f[1] + '(' + vals[f[0]] + f[2] + ')'; }).join(' ');
    }

    // The values the rule of st holds, none taken from Normal.
    function fxOwn(text, cls, st) {
        var out = FX_NONE();
        var map = tfDecls(tfRule(text, cls, st));
        var f = map.filter ? fxParse(map.filter) : null;
        if (f) Object.keys(f).forEach(function (k) { out[k] = f[k]; });
        var m = /^blur\(\s*(\d+\.?\d*|\.\d+)px\s*\)$/i.exec(map['backdrop-filter'] || '');
        if (m) out.bdblur = Number(m[1]);
        if (BLENDS.indexOf(map['mix-blend-mode']) !== -1) out.blend = map['mix-blend-mode'];
        if (BLENDS.indexOf(map['background-blend-mode']) !== -1) out.bgblend = map['background-blend-mode'];
        return out;
    }

    // { blur … bgblend }: numbers, a blend's name, '' where not set. Under a
    // state a Filter value equal to Normal's (or its default) reads as ''.
    function fxRead(text, cls, st) {
        var out = fxOwn(text, cls, st);
        if (!st) return out;
        var base = fxOwn(text, cls, '');
        FX_FILTER.forEach(function (f) {
            var b = base[f[0]] === '' ? f[3] : base[f[0]];
            if (out[f[0]] === b) out[f[0]] = '';
        });
        return out;
    }

    // The filter of the state st from its values and Normal's: the whole
    // list under a state, '' when it equals Normal's.
    function fxFilterOf(own, base, st) {
        if (!st) {
            var n = {};
            FX_FILTER.forEach(function (f) { n[f[0]] = own[f[0]] === f[3] ? '' : own[f[0]]; });
            return fxFilter(n);
        }
        var all = {}, same = true;
        FX_FILTER.forEach(function (f) {
            var b = base[f[0]] === '' ? f[3] : base[f[0]];
            var v = own[f[0]] === '' ? b : own[f[0]];
            if (v !== b) same = false;
            all[f[0]] = (v === f[3] && own[f[0]] === '' && base[f[0]] === '') ? '' : v;
        });
        return same ? '' : fxFilter(all);
    }

    // The text with one effect value of the state st ('' Normal) set: a
    // Filter key (a number, kept to its row's ends), bdblur (0 to 100 px),
    // blend or bgblend (a blend's name); '' takes the value out.
    function fxWrite(text, cls, st, key, value) {
        text = String(text || '');
        if (!U() || !CLASS_RE.test(cls) || (st && !PSEUDO[st])) return text;
        var spec = fxSpec(key), v = value === null || value === undefined ? '' : String(value).trim();
        if (spec || key === 'bdblur') {
            if (v !== '') {
                v = spec ? numClean(v, spec[4], spec[5], 2) : numClean(v, 0, 100, 2);
                if (v === '') return text;
            }
        } else if (key === 'blend' || key === 'bgblend') {
            v = v.toLowerCase();
            if (v !== '' && BLENDS.indexOf(v) === -1) return text;
        } else {
            return text;
        }
        if (key === 'blend' || key === 'bgblend') {
            var prop = key === 'blend' ? 'mix-blend-mode' : 'background-blend-mode';
            return tfPut(text, cls, st, prop, (!st && v === 'normal') ? '' : v);
        }
        if (key === 'bdblur') {
            var bd = v === '' || (!st && v === 0) ? '' : 'blur(' + v + 'px)';
            return tfPut(tfPut(text, cls, st, 'backdrop-filter', bd), cls, st, '-webkit-backdrop-filter', bd);
        }
        var map = tfDecls(tfRule(text, cls, st));
        var baseMap = st ? tfDecls(tfRule(text, cls, '')) : map;
        if ((map.filter && !fxParse(map.filter)) || (baseMap.filter && !fxParse(baseMap.filter))) return text;
        var base = fxOwn(text, cls, '');
        var own = fxRead(text, cls, st);
        own[key] = v;
        var out = tfPut(text, cls, st, 'filter', fxFilterOf(own, base, st));
        // A state's values taken from Normal keep following it.
        if (!st) {
            var now = fxOwn(out, cls, '');
            STATES.forEach(function (s2) {
                var sm = tfDecls(tfRule(out, cls, s2));
                if (!sm.filter || !fxParse(sm.filter)) return;
                var mine = fxOwn(out, cls, s2);
                FX_FILTER.forEach(function (f) {
                    var b = base[f[0]] === '' ? f[3] : base[f[0]];
                    if (mine[f[0]] === '' || mine[f[0]] === b) mine[f[0]] = '';
                });
                out = tfPut(out, cls, s2, 'filter', fxFilterOf(mine, now, s2));
            });
        }
        return out;
    }

    // Whether the text may hold an effect for cls: a look, no parse.
    function fxMaybe(text, cls) {
        text = String(text || '');
        return !!cls && text.indexOf('.' + cls) !== -1 && /(?:^|[\s;{])(?:filter|backdrop-filter|mix-blend-mode|background-blend-mode)\s*:/.test(text);
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
        transMaybe: transMaybe,
        ORIGINS: ORIGINS,
        tfRead: tfRead,
        tfWrite: tfWrite,
        tfMaybe: tfMaybe,
        FX_FILTER: FX_FILTER,
        BLENDS: BLENDS,
        fxRead: fxRead,
        fxWrite: fxWrite,
        fxFilter: fxFilter,
        fxMaybe: fxMaybe
    };

    root.PGOptionStates = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
