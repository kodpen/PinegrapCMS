/**
 * Pinegrap - Enterprise Website Platform
 *
 * Clip shape and Mask of an element (Style › Shadow and effect › More ›
 * Shape): what the rows mean as `clip-path` and `mask-image` (with its
 * size, position, repeat and their -webkit- twins), read from and written
 * to the rule of the element's custom style class in User Styles, or the
 * rule of one of its states. Pure: the text in, the new text out, through
 * PgUserStyles (assets/js/pg_user_styles.js).
 *
 *   PGClipShape.clipCss(c)               // the clip-path value of a shape, '' for none
 *   PGClipShape.clipParse(v)             // the shape a clip-path value is, { kind: 'own' } when no row reads it
 *   PGClipShape.maskCss(m)               // { prop: value } a mask writes (no -webkit- twins)
 *   PGClipShape.maskParse(map)           // the mask a rule's declarations are
 *   PGClipShape.read(text, cls, st)      // { clip, mask }: the rule's own ('' st: Normal)
 *   PGClipShape.writeClip(text, cls, st, c)  // the new text with the shape c (null takes it out)
 *   PGClipShape.writeMask(text, cls, st, m)  // the new text with the mask m (null takes it out)
 *   PGClipShape.maybe(text, cls)         // whether the text may hold one (no parse)
 *
 * A shape: { kind: 'circle', size, at } | { kind: 'ellipse', w, h, at } |
 * { kind: 'inset', inset, corner } | { kind: 'slant', edge, amount, dir } |
 * { kind: 'arrow', points, point } | { kind: 'none' } (a state taking
 * Normal's away) | { kind: 'own', css } (hand-written, kept as it is).
 * A mask: { kind: 'fade', edge, length } | { kind: 'picture', url, size,
 * at } | { kind: 'none' } | { kind: 'own' }. `at` is one of the nine
 * places (0 top left to 8 bottom right), as Transform's Origin.
 *
 * window.PGClipShape in a browser; module.exports under Node. Plain ES5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var CLASS_RE = /^pgs-[a-z0-9]{6}$/;
    var PSEUDO = { hover: ':hover', focus: ':focus-visible', pressed: ':active' };
    var IMP_RE = /\s*!\s*important\s*$/i;
    var PLACES = ['0% 0%', '50% 0%', '100% 0%', '0% 50%', '50% 50%', '100% 50%', '0% 100%', '50% 100%', '100% 100%'];
    var EDGES = ['top', 'right', 'bottom', 'left'];
    var SIZES = ['contain', 'cover', 'auto'];
    // The mask's properties; each is written with its -webkit- twin.
    var MASK_PROPS = ['mask-image', 'mask-size', 'mask-position', 'mask-repeat'];
    // The defaults a shape or a mask starts from.
    var DEFAULTS = {
        circle: { size: 50, at: 4 },
        ellipse: { w: 50, h: 40, at: 4 },
        inset: { inset: 5, corner: 16 },
        slant: { edge: 'bottom', amount: 10, dir: 'right' },
        arrow: { points: 'right', point: 20 },
        fade: { edge: 'bottom', length: 30 },
        picture: { url: '', size: 'contain', at: 4 }
    };

    function U() { return root.PgUserStyles; }

    function flat(s) { return String(s || '').replace(/\s+/g, ' ').trim().toLowerCase(); }

    // A number kept to [lo, hi], rounded to dp places; the default when it
    // is not one.
    function num(v, lo, hi, dflt, dp) {
        var n = Number(v);
        if (v === '' || v === null || v === undefined || !isFinite(n)) return dflt;
        var f = Math.pow(10, dp || 0);
        return Math.round(Math.min(hi, Math.max(lo, n)) * f) / f;
    }

    function place(v) {
        return /^[0-8]$/.test(String(v)) ? Number(v) : 4;
    }

    function pct(n) { return n + '%'; }

    // A shape with every value in its range (null for a kind no row has).
    function clipClean(c) {
        if (!c || !c.kind) return null;
        var d = DEFAULTS[c.kind];
        if (c.kind === 'none') return { kind: 'none' };
        if (!d) return null;
        if (c.kind === 'circle') return { kind: 'circle', size: num(c.size, 1, 100, d.size), at: place(c.at === undefined ? d.at : c.at) };
        if (c.kind === 'ellipse') return { kind: 'ellipse', w: num(c.w, 1, 100, d.w), h: num(c.h, 1, 100, d.h), at: place(c.at === undefined ? d.at : c.at) };
        if (c.kind === 'inset') return { kind: 'inset', inset: num(c.inset, 0, 45, d.inset), corner: num(c.corner, 0, 200, d.corner) };
        if (c.kind === 'slant') {
            return { kind: 'slant', edge: ['top', 'bottom', 'both'].indexOf(c.edge) !== -1 ? c.edge : d.edge,
                amount: num(c.amount, 1, 45, d.amount), dir: c.dir === 'left' ? 'left' : 'right' };
        }
        return { kind: 'arrow', points: ['right', 'left', 'down', 'up'].indexOf(c.points) !== -1 ? c.points : d.points,
            point: num(c.point, 1, 50, d.point) };
    }

    // The slant's points: the top edge, then the bottom edge, each going
    // up to the right (dir 'right') or to the left.
    function slantPoints(c) {
        var a = c.amount, lo = 100 - a;
        var top = c.edge === 'bottom' ? ['0% 0%', '100% 0%'] :
            c.dir === 'right' ? ['0% ' + pct(a), '100% 0%'] : ['0% 0%', '100% ' + pct(a)];
        var bottom = c.edge === 'top' ? ['100% 100%', '0% 100%'] :
            c.dir === 'right' ? ['100% ' + pct(lo), '0% 100%'] : ['100% 100%', '0% ' + pct(lo)];
        return top.concat(bottom);
    }

    function arrowPoints(c) {
        var p = c.point, q = 100 - p;
        if (c.points === 'left') return [pct(p) + ' 0%', '100% 0%', '100% 100%', pct(p) + ' 100%', '0% 50%'];
        if (c.points === 'down') return ['0% 0%', '100% 0%', '100% ' + pct(q), '50% 100%', '0% ' + pct(q)];
        if (c.points === 'up') return ['50% 0%', '100% ' + pct(p), '100% 100%', '0% 100%', '0% ' + pct(p)];
        return ['0% 0%', pct(q) + ' 0%', '100% 50%', pct(q) + ' 100%', '0% 100%'];
    }

    function clipCss(c) {
        c = clipClean(c);
        if (!c) return '';
        if (c.kind === 'none') return 'none';
        if (c.kind === 'circle') return 'circle(' + pct(c.size) + ' at ' + PLACES[c.at] + ')';
        if (c.kind === 'ellipse') return 'ellipse(' + pct(c.w) + ' ' + pct(c.h) + ' at ' + PLACES[c.at] + ')';
        if (c.kind === 'inset') return 'inset(' + pct(c.inset) + (c.corner ? ' round ' + c.corner + 'px' : '') + ')';
        return 'polygon(' + (c.kind === 'slant' ? slantPoints(c) : arrowPoints(c)).join(', ') + ')';
    }

    // The numbers of a polygon's points, or null.
    function polyNums(v) {
        var m = /^polygon\((.*)\)$/.exec(v);
        if (!m) return null;
        var out = [];
        var pts = m[1].split(',');
        for (var i = 0; i < pts.length; i++) {
            var p = /^\s*(-?\d+\.?\d*)%?\s+(-?\d+\.?\d*)%?\s*$/.exec(pts[i]);
            if (!p) return null;
            out.push([Number(p[1]), Number(p[2])]);
        }
        return out;
    }

    // The slant or arrow whose points are these, or null: every candidate
    // is drawn again from the one number it takes and compared.
    function polyShape(v) {
        var pts = polyNums(v);
        if (!pts) return null;
        var tries = [];
        ['top', 'bottom', 'both'].forEach(function (edge) {
            ['right', 'left'].forEach(function (dir) {
                pts.forEach(function (p) {
                    [p[0], p[1], 100 - p[0], 100 - p[1]].forEach(function (a) {
                        tries.push({ kind: 'slant', edge: edge, dir: dir, amount: a });
                    });
                });
            });
        });
        ['right', 'left', 'down', 'up'].forEach(function (dir) {
            pts.forEach(function (p) {
                [p[0], p[1], 100 - p[0], 100 - p[1]].forEach(function (a) {
                    tries.push({ kind: 'arrow', points: dir, point: a });
                });
            });
        });
        var want = flat(v);
        for (var i = 0; i < tries.length; i++) {
            var t = tries[i], n = t.kind === 'slant' ? t.amount : t.point;
            if (n < 1 || n > (t.kind === 'slant' ? 45 : 50) || n !== Math.round(n * 100) / 100) continue;
            if (flat(clipCss(t)) === want) return clipClean(t);
        }
        return null;
    }

    // The shape a clip-path value is.
    function clipParse(v) {
        var s = flat(String(v || '').replace(IMP_RE, ''));
        if (!s) return null;
        if (s === 'none') return { kind: 'none' };
        var at = function (x) { return PLACES.indexOf(x); };
        var m;
        if ((m = /^circle\((\d+\.?\d*)% at (.+)\)$/.exec(s)) && at(m[2]) !== -1) {
            return clipClean({ kind: 'circle', size: Number(m[1]), at: at(m[2]) });
        }
        if ((m = /^ellipse\((\d+\.?\d*)% (\d+\.?\d*)% at (.+)\)$/.exec(s)) && at(m[3]) !== -1) {
            return clipClean({ kind: 'ellipse', w: Number(m[1]), h: Number(m[2]), at: at(m[3]) });
        }
        if ((m = /^inset\((\d+\.?\d*)%(?: round (\d+\.?\d*)px)?\)$/.exec(s))) {
            return clipClean({ kind: 'inset', inset: Number(m[1]), corner: m[2] === undefined ? 0 : Number(m[2]) });
        }
        var c = polyShape(s);
        return c && flat(clipCss(c)) === s ? c : { kind: 'own', css: String(v).replace(IMP_RE, '').trim() };
    }

    function maskClean(m) {
        if (!m || !m.kind) return null;
        if (m.kind === 'none') return { kind: 'none' };
        if (m.kind === 'fade') {
            return { kind: 'fade', edge: EDGES.indexOf(m.edge) !== -1 ? m.edge : DEFAULTS.fade.edge, length: num(m.length, 1, 100, DEFAULTS.fade.length) };
        }
        if (m.kind === 'picture') {
            return { kind: 'picture', url: String(m.url || '').trim(), size: SIZES.indexOf(m.size) !== -1 ? m.size : DEFAULTS.picture.size,
                at: place(m.at === undefined ? DEFAULTS.picture.at : m.at) };
        }
        return null;
    }

    // A picture's address as a CSS url(), its quotes and breaks escaped.
    function urlCss(u) {
        return 'url("' + String(u).replace(/[\\"]/g, '\\$&').replace(/[\r\n]/g, '') + '")';
    }

    function maskCss(m) {
        m = maskClean(m);
        if (!m) return {};
        if (m.kind === 'none') return { 'mask-image': 'none' };
        if (m.kind === 'fade') {
            return { 'mask-image': 'linear-gradient(to ' + m.edge + ', #000 ' + pct(100 - m.length) + ', transparent)' };
        }
        // A picture not chosen yet hides nothing: nothing is written.
        if (!m.url) return {};
        return { 'mask-image': urlCss(m.url), 'mask-size': m.size, 'mask-position': PLACES[m.at], 'mask-repeat': 'no-repeat' };
    }

    // The mask a rule's declarations are ({ prop: value }), null without one.
    function maskParse(map) {
        map = map || {};
        var img = map['mask-image'] || map['-webkit-mask-image'] || '';
        if (map.mask || map['-webkit-mask']) return { kind: 'own' };
        if (!img) return null;
        var s = flat(img), m;
        if (s === 'none') return { kind: 'none' };
        if ((m = /^linear-gradient\(to (top|right|bottom|left), #000 (\d+\.?\d*)%, transparent\)$/.exec(s))) {
            var f = maskClean({ kind: 'fade', edge: m[1], length: 100 - Number(m[2]) });
            if (flat(maskCss(f)['mask-image']) === s) return f;
        }
        if ((m = /^url\(\s*(?:"((?:[^"\\]|\\.)*)"|'((?:[^'\\]|\\.)*)'|([^"'()\s]+))\s*\)$/.exec(String(img).trim()))) {
            var url = (m[1] !== undefined ? m[1] : m[2] !== undefined ? m[2] : m[3]).replace(/\\(.)/g, '$1');
            var size = flat(map['mask-size'] || map['-webkit-mask-size'] || 'auto');
            var pos = flat(map['mask-position'] || map['-webkit-mask-position'] || '0% 0%');
            if (SIZES.indexOf(size) !== -1 && PLACES.indexOf(pos) !== -1) {
                return { kind: 'picture', url: url, size: size, at: PLACES.indexOf(pos) };
            }
        }
        return { kind: 'own' };
    }

    function ruleOf(text, cls, st) {
        if (!U() || !CLASS_RE.test(cls) || (st && !PSEUDO[st])) return null;
        var sel = '.' + cls + (st ? PSEUDO[st] : '');
        var list = U().parse(String(text || ''));
        for (var i = 0; i < list.length; i++) {
            if (!list[i].media && list[i].selector.replace(/\s+/g, '') === sel) return list[i];
        }
        return null;
    }

    function declsOf(rule) {
        var map = {};
        if (rule) rule.decls.forEach(function (d) { map[d.name.toLowerCase()] = d.value.replace(IMP_RE, '').trim(); });
        return map;
    }

    // { clip, mask }: what the rule of st holds itself, null for none.
    function read(text, cls, st) {
        var map = declsOf(ruleOf(text, cls, st || ''));
        return { clip: map['clip-path'] ? clipParse(map['clip-path']) : null, mask: maskParse(map) };
    }

    // The declaration `prop: val` in the rule of st; val '' takes it out.
    // A state's rule left empty goes; Normal's stays.
    function put(text, cls, st, prop, val) {
        var rule = ruleOf(text, cls, st);
        var idx = -1;
        if (rule) rule.decls.forEach(function (d, i) { if (d.name.toLowerCase() === prop) idx = i; });
        if (!val) {
            if (idx === -1) return text;
            if (st && rule.decls.length === 1) return U().removeRule(text, rule).replace(/\n{2,}$/, '\n');
            return U().removeDecl(text, rule, idx);
        }
        if (idx !== -1) return U().setDecl(text, rule, idx, prop, val);
        if (!rule) {
            text = U().appendRule(text, '.' + cls + (st ? PSEUDO[st] : ''));
            rule = ruleOf(text, cls, st);
            if (!rule) return text;
        }
        return U().addDecl(text, rule, prop, val);
    }

    function usable(text, cls, st) {
        return !!U() && CLASS_RE.test(cls) && (!st || !!PSEUDO[st]);
    }

    // Under a state a value equal to Normal's is not written: the state
    // shows Normal's.
    function writeClip(text, cls, st, c) {
        text = String(text || '');
        if (!usable(text, cls, st)) return text;
        var val = c ? clipCss(c) : '';
        if (c && !val) return text;
        if (!st && val === 'none') val = '';
        if (st && val) {
            var base = declsOf(ruleOf(text, cls, ''))['clip-path'] || '';
            if (flat(base) === flat(val) || (val === 'none' && !base)) val = '';
        }
        return put(text, cls, st, 'clip-path', val);
    }

    function writeMask(text, cls, st, m) {
        text = String(text || '');
        if (!usable(text, cls, st)) return text;
        var vals = m ? maskCss(m) : {};
        if (m && !maskClean(m)) return text;
        if (!st && vals['mask-image'] === 'none') vals = {};
        if (st && vals['mask-image']) {
            var base = declsOf(ruleOf(text, cls, ''));
            var same = MASK_PROPS.every(function (p) { return flat(base[p] || '') === flat(vals[p] || ''); });
            if (same || (vals['mask-image'] === 'none' && !base['mask-image'])) vals = {};
        }
        MASK_PROPS.forEach(function (p) {
            text = put(text, cls, st, p, vals[p] || '');
            text = put(text, cls, st, '-webkit-' + p, vals[p] || '');
        });
        return text;
    }

    function maybe(text, cls) {
        text = String(text || '');
        return !!cls && text.indexOf('.' + cls) !== -1 && /(?:^|[\s;{])(?:clip-path|(?:-webkit-)?mask(?:-image)?)\s*:/.test(text);
    }

    var api = {
        PLACES: PLACES,
        DEFAULTS: DEFAULTS,
        clipCss: clipCss,
        clipParse: clipParse,
        maskCss: maskCss,
        maskParse: maskParse,
        read: read,
        writeClip: writeClip,
        writeMask: writeMask,
        maybe: maybe
    };

    root.PGClipShape = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
