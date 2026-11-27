/**
 * Pinegrap - Enterprise Website Platform
 *
 * Background layers: an element's background as a list of colour,
 * gradient and picture layers, read from and written to the background
 * properties of its style attribute. Pure: values in, values out.
 *
 *   PGBgLayers.read(style)       // { layers, legacy } or null (a background no list reads)
 *   PGBgLayers.write(layers)     // { 'background-image': …, 'background-size': …, … } ('' drops one)
 *   PGBgLayers.color(c)          // the CSS of a colour { p, v, a }
 *   PGBgLayers.parseColor(css)   // { p, v, a }
 *   PGBgLayers.gradient(g)       // the CSS of a gradient layer
 *   PGBgLayers.parseImage(css)   // one layer read from one background-image item, or null
 *   PGBgLayers.move(list, from, to) // a new list with one item moved
 *   PGBgLayers.widest(stops)     // the middle of the widest gap between stops
 *   PGBgLayers.mixAt(stops, pos, rgbOf) // the colour found at pos
 *   PGBgLayers.focusLeft(style, vals) // whether a focus per screen is left unread by vals
 *
 * `style` holds the style attribute's background properties by name
 * (`background-image`, `-size`, `-position`, `-repeat`, `-attachment`,
 * `-blend-mode`, `-color`, and the `background` shorthand). A palette
 * colour is { p: 'primary', a: 100 }, written as the theme's variable so
 * a palette change follows; a custom one is { v: '#1f6f54', a: 45 }.
 *
 * window.PGBgLayers in a browser; module.exports under Node. Plain ES5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var PALETTE = ['primary', 'secondary', 'success', 'danger', 'light', 'dark', 'body-bg'];
    var MAX_LAYERS = 8, MIN_STOPS = 2, MAX_STOPS = 16;
    var PLACES = ['left top', 'center top', 'right top', 'left center', 'center center', 'right center', 'left bottom', 'center bottom', 'right bottom'];
    var SIDES = { 'top': 0, 'top right': 45, 'right': 90, 'bottom right': 135, 'bottom': 180, 'bottom left': 225, 'left': 270, 'top left': 315 };
    // What CSS takes for a layer with no value of its own.
    var CSS_DEFAULT = { size: 'auto', position: '0% 0%', repeat: 'repeat', attach: 'scroll', blend: 'normal' };
    var PROP = { size: 'background-size', position: 'background-position', repeat: 'background-repeat', attach: 'background-attachment', blend: 'background-blend-mode' };

    function split(s) {
        var out = [], buf = '', depth = 0, q = '';
        s = String(s || '');
        for (var i = 0; i < s.length; i++) {
            var ch = s.charAt(i);
            if (q) { if (ch === q) q = ''; }
            else if (ch === '"' || ch === "'") q = ch;
            else if (ch === '(') depth++;
            else if (ch === ')') depth--;
            if (ch === ',' && depth <= 0 && !q) { out.push(buf.trim()); buf = ''; } else buf += ch;
        }
        if (buf.trim() || out.length) out.push(buf.trim());
        return out;
    }

    function num(n) {
        return String(Math.round(Number(n) * 100) / 100);
    }

    function hexRgb(hex) {
        var m = /^#([\da-f]{3}|[\da-f]{6})$/i.exec(hex || '');
        if (!m) return null;
        var h = m[1].length === 3 ? m[1].replace(/./g, '$&$&') : m[1];
        return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)];
    }

    function toHex(rgb) {
        return '#' + rgb.map(function (x) {
            var s = Math.max(0, Math.min(255, Math.round(x))).toString(16);
            return s.length < 2 ? '0' + s : s;
        }).join('');
    }

    function color(c) {
        c = c || {};
        var a = c.a == null ? 100 : Math.max(0, Math.min(100, Number(c.a)));
        if (c.p) return a >= 100 ? 'var(--bs-' + c.p + ')' : 'rgba(var(--bs-' + c.p + '-rgb), ' + num(a / 100) + ')';
        var v = c.v || 'transparent';
        if (a >= 100) return v;
        var rgb = hexRgb(v);
        if (rgb) return 'rgba(' + rgb.join(', ') + ', ' + num(a / 100) + ')';
        return 'color-mix(in srgb, ' + v + ' ' + num(a) + '%, transparent)';
    }

    function parseColor(css) {
        var s = String(css || '').trim(), m;
        if ((m = /^var\(\s*--bs-([a-z-]+?)\s*\)$/i.exec(s)) && PALETTE.indexOf(m[1].toLowerCase()) !== -1) {
            return { p: m[1].toLowerCase(), a: 100 };
        }
        if ((m = /^rgba\(\s*var\(\s*--bs-([a-z-]+?)-rgb\s*\)\s*,\s*([\d.]+)\s*\)$/i.exec(s)) && PALETTE.indexOf(m[1].toLowerCase()) !== -1) {
            return { p: m[1].toLowerCase(), a: Math.round(parseFloat(m[2]) * 100) };
        }
        if ((m = /^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)$/i.exec(s))) {
            return { v: toHex([+m[1], +m[2], +m[3]]), a: m[4] == null ? 100 : Math.round(parseFloat(m[4]) * 100) };
        }
        if ((m = /^color-mix\(\s*in srgb\s*,\s*(.+?)\s+([\d.]+)%\s*,\s*transparent\s*\)$/i.exec(s))) {
            return { v: m[1], a: Math.round(parseFloat(m[2])) };
        }
        return { v: s, a: 100 };
    }

    function stopsCss(stops) {
        return stops.map(function (st) {
            return (st.hint != null ? num(st.hint) + '%, ' : '') + color(st.c) + ' ' + num(st.pos) + '%';
        }).join(', ');
    }

    function gradient(g) {
        var head = '';
        if (g.type === 'radial') {
            head = (g.shape === 'circle' ? 'circle' : 'ellipse') + (g.at && g.at !== 'center center' ? ' at ' + g.at : '');
            return 'radial-gradient(' + (head ? head + ', ' : '') + stopsCss(g.stops) + ')';
        }
        if (g.type === 'conic') {
            head = (Number(g.from) ? 'from ' + num(g.from) + 'deg' : '') + (g.at && g.at !== 'center center' ? (Number(g.from) ? ' ' : '') + 'at ' + g.at : '');
            return 'conic-gradient(' + (head ? head + ', ' : '') + stopsCss(g.stops) + ')';
        }
        return 'linear-gradient(' + num(g.angle == null ? 180 : g.angle) + 'deg, ' + stopsCss(g.stops) + ')';
    }

    // A place written as words or per cents, as one of the nine names
    // when it is one ('center' alone is the middle), else kept as it is.
    function place(s) {
        var raw = String(s || '').trim(), t = raw.toLowerCase().split(/\s+/), x, y;
        var X = { left: 'left', '0%': 'left', center: 'center', '50%': 'center', right: 'right', '100%': 'right' };
        var Y = { top: 'top', '0%': 'top', center: 'center', '50%': 'center', bottom: 'bottom', '100%': 'bottom' };
        if (!t[0] || t.length > 2) return raw;
        if (t.length === 1) {
            if (t[0] === 'top' || t[0] === 'bottom') { x = 'center'; y = t[0]; } else { x = X[t[0]]; y = 'center'; }
        } else if (t[0] === 'top' || t[0] === 'bottom' || t[1] === 'left' || t[1] === 'right') {
            x = X[t[1]]; y = Y[t[0]];
        } else {
            x = X[t[0]]; y = Y[t[1]];
        }
        return x && y ? x + ' ' + y : raw;
    }

    // The stops of a gradient: [{ c, pos, hint }] or null. A stop's place
    // in per cent; stops without one are spread as CSS spreads them.
    function parseStops(items) {
        var stops = [], hint = null;
        for (var i = 0; i < items.length; i++) {
            var it = items[i].replace(/\s0$/, ' 0%'), m;
            if (/^[\d.]+%$/.test(it)) { if (hint != null || !stops.length) return null; hint = parseFloat(it); continue; }
            m = /^(.*?)(?:\s+(-?[\d.]+)%)?$/.exec(it);
            if (!m || !m[1] || /\s-?[\d.]+(%|px|em|rem)$/.test(m[1]) || /[\d.]+(px|em|rem)$/.test(it)) return null;
            stops.push({ c: parseColor(m[1]), pos: m[2] == null ? null : parseFloat(m[2]), hint: hint });
            hint = null;
        }
        if (hint != null || stops.length < MIN_STOPS || stops.length > MAX_STOPS) return null;
        if (stops[0].pos == null) stops[0].pos = 0;
        if (stops[stops.length - 1].pos == null) stops[stops.length - 1].pos = 100;
        for (var a = 0; a < stops.length; a++) {
            if (stops[a].pos != null) continue;
            var b = a;
            while (stops[b].pos == null) b++;
            var lo = stops[a - 1].pos, step = (stops[b].pos - lo) / (b - a + 1);
            for (var k = a; k < b; k++) stops[k].pos = Math.round((lo + step * (k - a + 1)) * 100) / 100;
        }
        return stops;
    }

    function parseImage(css) {
        var s = String(css || '').trim(), m, items, head, g;
        if (s.toLowerCase() === 'none') return { kind: 'picture', url: '' };
        if ((m = /^url\(\s*(?:"([^"]*)"|'([^']*)'|([^)'"]*))\s*\)$/i.exec(s))) {
            return { kind: 'picture', url: m[1] != null ? m[1] : m[2] != null ? m[2] : m[3].trim() };
        }
        if (!(m = /^(linear|radial|conic)-gradient\(([\s\S]*)\)$/i.exec(s))) return null;
        items = split(m[2]);
        g = { kind: 'gradient', type: m[1].toLowerCase(), angle: 180, shape: 'ellipse', at: 'center center', from: 0 };
        head = items[0].toLowerCase();
        if (g.type === 'linear') {
            var d;
            if ((d = /^(-?[\d.]+)deg$/.exec(head))) { g.angle = parseFloat(d[1]); items.shift(); }
            else if ((d = /^to\s+(.+)$/.exec(head))) {
                var side = d[1].replace(/\s+/g, ' ').split(' ').sort(function (x, y) { return /top|bottom/.test(x) ? -1 : /top|bottom/.test(y) ? 1 : 0; }).join(' ');
                if (SIDES[side] == null) return null;
                g.angle = SIDES[side]; items.shift();
            }
        } else if (/^(closest|farthest)|^-?[\d.]+(px|%|em|rem)/.test(head)) {
            return null;
        } else if (/^(circle|ellipse|at |from )/.test(head)) {
            var hm = /^(?:(circle|ellipse)\s*)?(?:from\s+(-?[\d.]+)deg\s*)?(?:at\s+(.+))?$/.exec(head);
            if (!hm || (g.type === 'radial' && hm[2]) || (g.type === 'conic' && hm[1])) return null;
            if (hm[1]) g.shape = hm[1];
            if (hm[2]) g.from = parseFloat(hm[2]);
            if (hm[3]) g.at = place(hm[3]);
            items.shift();
        }
        g.stops = parseStops(items);
        if (!g.stops) return null;
        // A colour layer is a gradient of one colour twice.
        if (g.type === 'linear' && g.stops.length === 2 && g.angle === 180 && g.stops[0].pos === 0 && g.stops[1].pos === 100 &&
            !g.stops[1].hint && color(g.stops[0].c) === color(g.stops[1].c)) {
            return { kind: 'color', c: g.stops[0].c };
        }
        return g;
    }

    function read(style) {
        style = style || {};
        var get = function (k) { return String(style['background-' + k] || '').trim(); };
        var img = get('image'), legacyColor = get('color'), layers = [];
        if (String(style.background || '').trim()) {
            // A shorthand holding one picture or gradient alone is read; any
            // other shorthand is the hand's.
            var sh = String(style.background).trim();
            if (img || !parseImage(sh)) return null;
            img = sh;
        }
        if (img && !(img.toLowerCase() === 'none' && !get('size'))) {
            var items = split(img), lists = {};
            Object.keys(PROP).forEach(function (k) { lists[k] = get(PROP[k].slice(11)) ? split(get(PROP[k].slice(11))) : []; });
            if (items.length > MAX_LAYERS) return null;
            for (var i = 0; i < items.length; i++) {
                var l = parseImage(items[i]);
                if (!l) return null;
                if (l.kind !== 'color') Object.keys(PROP).forEach(function (k) {
                    var list = lists[k], v = list.length ? list[i % list.length] : '';
                    if (k === 'position' && v) v = place(v) === 'left top' ? CSS_DEFAULT.position : place(v);
                    l[k] = v && v !== CSS_DEFAULT[k] ? v : '';
                });
                layers.push(l);
            }
        }
        if (legacyColor && layers.length < MAX_LAYERS) layers.push({ kind: 'color', c: parseColor(legacyColor), legacy: true });
        return { layers: layers, legacy: !!(legacyColor || style.background) };
    }

    // The values to set: the whole list once anything is set, '' for a
    // property no layer needs (it is then removed with the shorthand and
    // the old custom colour).
    function write(layers) {
        var out = { 'background-image': '', 'background-color': '', 'background': '' };
        Object.keys(PROP).forEach(function (k) { out[PROP[k]] = ''; });
        if (!layers.length) return out;
        out['background-image'] = layers.map(function (l) {
            if (l.kind === 'color') return 'linear-gradient(' + color(l.c) + ', ' + color(l.c) + ')';
            if (l.kind === 'picture') return l.url ? 'url("' + String(l.url).replace(/["\\\n\r]/g, '') + '")' : 'none';
            return gradient(l);
        }).join(', ');
        Object.keys(PROP).forEach(function (k) {
            var any = layers.some(function (l) { return l.kind !== 'color' && l[k]; });
            if (!any) return;
            out[PROP[k]] = layers.map(function (l) { return (l.kind !== 'color' && l[k]) || CSS_DEFAULT[k]; }).join(', ');
        });
        return out;
    }

    // The place the nine places show for a layer's position: one with no
    // value of its own sits where CSS puts it, the top left corner.
    function shownPlace(v) {
        return place(v || CSS_DEFAULT.position);
    }

    // The Repeat list's value for a layer: '' (No repeat) for no-repeat; a
    // layer with no repeat of its own repeats, as CSS draws it.
    function shownRepeat(l) {
        var r = l && l.repeat ? String(l.repeat) : '';
        return r === 'no-repeat' ? '' : (r || CSS_DEFAULT.repeat);
    }

    // What a choice of the Repeat list writes: '' (No repeat) is no-repeat,
    // on a picture and on a gradient alike.
    function repeatOf(v) {
        return v || 'no-repeat';
    }

    // True when `style` holds a focus per screen (--pg-focus-d, written by
    // the Focus control with the pg-focus class) that nothing reads once
    // `vals` (write()'s) are set: neither the new background-position nor
    // the style's object-position (a video's) goes through var(--pg-focus).
    function focusLeft(style, vals) {
        style = style || {};
        if (style['--pg-focus-d'] === undefined) return false;
        var reads = function (v) { return /var\(\s*--pg-focus\s*[,)]/i.test(String(v || '')); };
        return !reads((vals || {})['background-position']) && !reads(style['object-position']);
    }

    function move(list, from, to) {
        var a = list.slice();
        if (from < 0 || from >= a.length || to < 0 || to >= a.length) return a;
        a.splice(to, 0, a.splice(from, 1)[0]);
        return a;
    }

    function widest(stops) {
        var ps = stops.map(function (s) { return Number(s.pos); }).sort(function (x, y) { return x - y; });
        var best = 0, at = 50;
        for (var i = 1; i < ps.length; i++) {
            if (ps[i] - ps[i - 1] > best) { best = ps[i] - ps[i - 1]; at = (ps[i] + ps[i - 1]) / 2; }
        }
        return Math.round(at);
    }

    // The colour at pos between its two stops: mixed when rgbOf(c) gives
    // both as [r, g, b, a], else the nearer stop's own.
    function mixAt(stops, pos, rgbOf) {
        var s = stops.slice().sort(function (x, y) { return x.pos - y.pos; });
        var lo = s[0], hi = s[s.length - 1];
        for (var i = 1; i < s.length; i++) if (s[i].pos >= pos) { lo = s[i - 1]; hi = s[i]; break; }
        if (pos <= s[0].pos) return { p: s[0].c.p, v: s[0].c.v, a: s[0].c.a };
        if (pos >= s[s.length - 1].pos) lo = hi = s[s.length - 1];
        var t = hi.pos === lo.pos ? 0 : (pos - lo.pos) / (hi.pos - lo.pos);
        var A = rgbOf ? rgbOf(lo.c) : null, B = rgbOf ? rgbOf(hi.c) : null;
        if (!A || !B) { var n = t < 0.5 ? lo.c : hi.c; return { p: n.p, v: n.v, a: n.a }; }
        var rgb = [0, 1, 2].map(function (k) { return A[k] + (B[k] - A[k]) * t; });
        return { v: toHex(rgb), a: Math.round(A[3] + (B[3] - A[3]) * t) };
    }

    var api = {
        PALETTE: PALETTE, PLACES: PLACES, MAX_LAYERS: MAX_LAYERS, MIN_STOPS: MIN_STOPS, MAX_STOPS: MAX_STOPS,
        read: read, write: write, color: color, parseColor: parseColor, gradient: gradient,
        parseImage: parseImage, move: move, widest: widest, mixAt: mixAt, hexRgb: hexRgb, place: place,
        shownPlace: shownPlace, shownRepeat: shownRepeat, repeatOf: repeatOf, focusLeft: focusLeft
    };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    if (root) root.PGBgLayers = api;
})(typeof window !== 'undefined' ? window : null);
