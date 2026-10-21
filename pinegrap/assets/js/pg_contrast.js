/**
 * Pinegrap - Enterprise Website Platform
 *
 * Colour contrast (WCAG 2.x, success criterion 1.4.3). The relative luminance
 * and contrast ratio are the ones the theme palette builder computes on the
 * server (_pg_theme_luminance(), _pg_theme_contrast() in
 * includes/fn/design_themes.php); tests/contrast_test.php runs both on the
 * same colours and keeps them equal. Around them, what a page needs to apply
 * the rule: reading a CSS colour, laying a translucent colour over another,
 * the ratio a text needs at its size, the colour a text actually sits on and
 * whether a picture is under it instead.
 *
 * window.PgContrast in a browser; module.exports under Node, where only the
 * functions that need no document can run. Plain ES5, no dependencies.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var WHITE = [255, 255, 255, 1];
    var SVG_NS = 'http://www.w3.org/2000/svg';
    var MATH_NS = 'http://www.w3.org/1998/Math/MathML';

    // Elements whose text is not text a visitor reads on the page: code,
    // styles, the options of a closed list, the contents of a frame.
    var NO_TEXT = {
        SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, TEMPLATE: 1, TEXTAREA: 1, SELECT: 1, OPTION: 1,
        IFRAME: 1, OBJECT: 1, EMBED: 1, CANVAS: 1, VIDEO: 1, AUDIO: 1
    };

    // Elements that paint a picture rather than a colour.
    var PICTURE = { IMG: 1, PICTURE: 1, VIDEO: 1, CANVAS: 1, IFRAME: 1, OBJECT: 1, EMBED: 1 };

    function clamp(v, lo, hi) {
        return v < lo ? lo : (v > hi ? hi : v);
    }

    // One channel of rgb(): a number 0-255 or a percentage.
    function channel(part) {
        part = String(part).trim();
        if (/%$/.test(part)) return clamp(parseFloat(part) * 2.55, 0, 255);
        return clamp(parseFloat(part), 0, 255);
    }

    // An alpha value: a number 0-1 or a percentage.
    function alpha(part) {
        if (part === undefined || part === null || String(part).trim() === '') return 1;
        part = String(part).trim();
        if (/%$/.test(part)) return clamp(parseFloat(part) / 100, 0, 1);
        return clamp(parseFloat(part), 0, 1);
    }

    /**
     * A CSS colour as [r, g, b, a] (r, g, b 0-255, a 0-1): hex, rgb()/rgba()
     * in the comma and the space syntax, color(srgb …) and transparent - what
     * getComputedStyle() gives for sRGB colours. null for anything else (a
     * named colour, another colour space): the caller then cannot tell.
     */
    function parse(value) {
        var s = String(value === undefined || value === null ? '' : value).trim().toLowerCase();
        var m, parts, a;

        if (s === 'transparent') return [0, 0, 0, 0];

        if ((m = s.match(/^#([0-9a-f]{3,8})$/)) && [3, 4, 6, 8].indexOf(m[1].length) !== -1) {
            var h = m[1];
            if (h.length < 6) h = h.replace(/./g, '$&$&');
            return [
                parseInt(h.substr(0, 2), 16),
                parseInt(h.substr(2, 2), 16),
                parseInt(h.substr(4, 2), 16),
                h.length === 8 ? Math.round(parseInt(h.substr(6, 2), 16) / 255 * 1000) / 1000 : 1
            ];
        }

        if ((m = s.match(/^rgba?\(([^)]*)\)$/))) {
            if (m[1].indexOf(',') !== -1) {
                parts = m[1].split(',');
                a = parts[3];
            } else {
                var slash = m[1].split('/');
                parts = slash[0].trim().split(/\s+/);
                a = slash[1];
            }
            if (parts.length < 3) return null;
            for (var i = 0; i < 3; i++) {
                if (!/^-?[\d.]+%?$/.test(String(parts[i]).trim())) return null;
            }
            return [channel(parts[0]), channel(parts[1]), channel(parts[2]), alpha(a)];
        }

        if ((m = s.match(/^color\(srgb\s+([^)]*)\)$/))) {
            var srgb = m[1].split('/');
            parts = srgb[0].trim().split(/\s+/);
            if (parts.length < 3) return null;
            var out = [];
            for (var j = 0; j < 3; j++) {
                var p = parts[j];
                if (!/^-?[\d.]+%?$/.test(p)) return null;
                out.push(clamp(/%$/.test(p) ? parseFloat(p) * 2.55 : parseFloat(p) * 255, 0, 255));
            }
            out.push(alpha(srgb[1]));
            return out;
        }

        return null;
    }

    /** Relative luminance of [r, g, b], as _pg_theme_luminance(). */
    function luminance(c) {
        var l = [];
        for (var i = 0; i < 3; i++) {
            var v = clamp(c[i], 0, 255) / 255;
            l.push(v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
        }
        return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2];
    }

    /** Contrast ratio of two colours, 1 to 21, as _pg_theme_contrast(). */
    function ratio(a, b) {
        var la = luminance(a), lb = luminance(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }

    /** `top` laid over `bottom` (source-over); both [r, g, b, a]. */
    function over(top, bottom) {
        var ta = top.length > 3 ? top[3] : 1;
        var ba = bottom.length > 3 ? bottom[3] : 1;
        var oa = ta + ba * (1 - ta);
        if (oa <= 0) return [0, 0, 0, 0];
        var out = [];
        for (var i = 0; i < 3; i++) out.push((top[i] * ta + bottom[i] * ba * (1 - ta)) / oa);
        out.push(oa);
        return out;
    }

    /**
     * A text colour and its background as they are drawn with opacity on the
     * way. `text` is the product of the opacities from the text's element up
     * to its backdrop element (not including it): they fade the text alone
     * over the backdrop. Each of `groups`, from the backdrop element outwards
     * ({opacity, under}), fades text and backdrop together over the colour
     * under it. Returns {color, background}, both [r, g, b, a].
     */
    function throughOpacity(fg, bg, text, groups) {
        var a = (fg.length > 3 ? fg[3] : 1) * text;
        var color = (a < 1) ? over([fg[0], fg[1], fg[2], a], bg) : fg;
        var background = bg;

        for (var i = 0; i < (groups || []).length; i++) {
            var g = groups[i];
            color = over([color[0], color[1], color[2], g.opacity], g.under);
            background = over([background[0], background[1], background[2], g.opacity], g.under);
        }

        return { color: color, background: background };
    }

    // An element's own opacity, 0-1; an editor's box around an element
    // (opts.ignore) is not the page's and counts as 1.
    function opacityOf(el, opts) {
        if (opts && opts.ignore && opts.ignore(el)) return 1;
        var o = parseFloat(styleOf(el).opacity);
        return isNaN(o) ? 1 : clamp(o, 0, 1);
    }

    /** A computed font-weight as a number. */
    function weight(value) {
        if (value === 'bold' || value === 'bolder') return 700;
        var n = parseInt(value, 10);
        return isNaN(n) ? 400 : n;
    }

    /**
     * The ratio a text needs (WCAG 1.4.3): 3:1 for large text - 24px, or
     * 18.66px (14pt) and bold - and 4.5:1 for every other.
     */
    function minimum(sizePx, fontWeight) {
        var size = parseFloat(sizePx) || 0;
        return (size >= 24 || (size >= 18.66 && weight(fontWeight) >= 700)) ? 3 : 4.5;
    }

    function styleOf(el) {
        return el.ownerDocument.defaultView.getComputedStyle(el);
    }

    /**
     * What a text inside `el` sits on: the background colours of `el` and its
     * ancestors, nearest first, laid over each other down to the first opaque
     * one, or white when none is. Returns {color, element} - element is the
     * opaque one, null when the page canvas itself is under the text - or null
     * when a background image or gradient is on the way down, or a colour
     * this cannot read: the text then does not sit on one colour.
     *
     * opts.ignore(el): the element's background is not the page's own (an
     * editor's box around an element); it is looked through.
     * opts.noImage(el): the element's background image is not the page's own.
     * opts.background(el, style): the element's own background colour as
     * [r, g, b, a] where its computed one is not the page's; undefined to use
     * the computed one.
     * cache: an optional WeakMap kept across the texts of one page.
     */
    function backdrop(el, opts, cache) {
        if (!el || el.nodeType !== 1) return { color: WHITE, element: null };
        if (cache && cache.has(el)) return cache.get(el);

        var result;

        if (opts && opts.ignore && opts.ignore(el)) {
            result = backdrop(el.parentElement, opts, cache);
        } else {
            var cs = styleOf(el);
            var image = cs.backgroundImage;

            if (image && image !== 'none' && !(opts && opts.noImage && opts.noImage(el))) {
                result = null;
            } else {
                var own = (opts && opts.background) ? opts.background(el, cs) : undefined;
                var c = (own !== undefined) ? own : parse(cs.backgroundColor);

                if (!c) {
                    result = null;
                } else if (c[3] >= 1) {
                    result = { color: c, element: el };
                } else {
                    var below = backdrop(el.parentElement, opts, cache);
                    result = (!below || c[3] <= 0) ? below : { color: over(c, below.color), element: below.element };
                }
            }
        }

        if (cache) cache.set(el, result);
        return result;
    }

    // Walks the elements under rootEl, an element opts.skip() refuses with
    // everything inside it left out.
    function walker(rootEl, opts, extraSkip) {
        return rootEl.ownerDocument.createTreeWalker(rootEl, 1, {
            acceptNode: function (n) {
                if (extraSkip && extraSkip(n)) return 2;
                return (opts && opts.skip && opts.skip(n)) ? 2 : 1;
            }
        });
    }

    /**
     * Everything under rootEl that paints a picture: images, video, canvas,
     * inline SVG, frames, and elements with a background image or gradient.
     * Returns [{el, rect}], each only once.
     */
    function pictures(rootEl, opts) {
        var out = [];
        var w = walker(rootEl, opts, function (n) { return n.namespaceURI === SVG_NS && n.tagName.toLowerCase() !== 'svg'; });

        for (var n = w.currentNode; n; n = w.nextNode()) {
            var painted = !!PICTURE[n.tagName] || n.tagName.toLowerCase() === 'svg';

            if (!painted && !(opts && opts.ignore && opts.ignore(n))) {
                var image = styleOf(n).backgroundImage;
                painted = !!image && image !== 'none' && !(opts && opts.noImage && opts.noImage(n));
            }

            if (painted) {
                var r = n.getBoundingClientRect();
                if (r.width > 0 && r.height > 0) out.push({ el: n, rect: r });
            }
        }

        return out;
    }

    /**
     * The texts under rootEl: one entry per element with text of its own
     * (not only in its children), {el, node, text} - node is its first text
     * node, text all of its own text, whitespace folded.
     */
    function textRuns(rootEl, opts) {
        var runs = [];
        var w = walker(rootEl, opts, function (n) { return !!NO_TEXT[n.tagName] || n.namespaceURI === SVG_NS || n.namespaceURI === MATH_NS; });

        for (var el = w.currentNode; el; el = w.nextNode()) {
            var first = null, text = '';

            for (var c = el.firstChild; c; c = c.nextSibling) {
                if (c.nodeType === 3 && /\S/.test(c.nodeValue)) {
                    if (!first) first = c;
                    text += ' ' + c.nodeValue;
                }
            }

            if (first) runs.push({ el: el, node: first, text: text.replace(/\s+/g, ' ').trim() });
        }

        return runs;
    }

    function textRect(node) {
        var range = node.ownerDocument.createRange();
        range.selectNodeContents(node);
        return range.getBoundingClientRect();
    }

    function intersects(a, b) {
        return a.left < b.right - 1 && b.left < a.right - 1 && a.top < b.bottom - 1 && b.top < a.bottom - 1;
    }

    /**
     * The zoom an element is drawn at: CSS zoom on it or an ancestor (the
     * visual designer zooms its canvas that way) scales what
     * getBoundingClientRect() reports, so a size in CSS pixels is the
     * rectangle's divided by this.
     */
    function zoom(el) {
        var z = el && el.currentCSSZoom;
        return (typeof z === 'number' && z > 0) ? z : 1;
    }

    /**
     * The contrast of one text (a textRuns() entry): {ratio, min, ok, color,
     * background}, or null when it cannot be told or does not apply - the
     * text is hidden or disabled (inactive controls are exempt), a picture is
     * under it, or a colour cannot be read. pics: pictures() of the page.
     */
    function check(run, pics, opts, cache) {
        var el = run.el;
        var cs = styleOf(el);

        if (cs.visibility !== 'visible' || parseFloat(cs.opacity) === 0) return null;
        if (el.closest && el.closest(':disabled, [aria-disabled="true"], .disabled')) return null;

        var z = zoom(el);

        // Text for screen readers only (Bootstrap's .visually-hidden and its
        // kin): a one-pixel box clipped to nothing, read aloud, never seen.
        // Its own glyphs still report a full-size rectangle.
        if (/^rect\(\s*0(px)?[\s,]+0(px)?[\s,]+0(px)?[\s,]+0(px)?\s*\)$/.test(cs.clip || '')) return null;
        if (cs.display !== 'contents') {
            var box = el.getBoundingClientRect();
            if (box.width / z <= 1 || box.height / z <= 1) return null;
        }

        var rect = textRect(run.node);

        // Not drawn: inside something hidden (display: none, a closed menu).
        if (!rect || rect.width / z < 2 || rect.height / z < 2) return null;

        var bg = backdrop(el, opts, cache);
        if (!bg) return null;

        for (var i = 0; i < (pics || []).length; i++) {
            var p = pics[i];

            // Its own icon, or a picture the opaque background covers.
            if (p.el === el || el.contains(p.el) || p.el.contains(el)) continue;
            if (bg.element && !bg.element.contains(p.el)) continue;
            if (intersects(rect, p.rect)) return null;
        }

        var fg = parse(cs.color);
        if (!fg) return null;

        // Opacity on the way (Bootstrap's opacity-75 on a text, a faded
        // box): what is drawn, not the colours the style names. Below the
        // backdrop element it fades the text alone; at it and above, text
        // and backdrop together over what lies under them.
        var text = 1;
        var groups = [];

        for (var n = el; n && n !== bg.element; n = n.parentElement) {
            text *= opacityOf(n, opts);
        }

        for (var m = bg.element; m; m = m.parentElement) {
            var o = opacityOf(m, opts);

            if (o < 1) {
                var under = backdrop(m.parentElement, opts, cache);
                if (!under) return null;
                groups.push({ opacity: o, under: under.color });
            }
        }

        var drawn = throughOpacity(fg, bg.color, text, groups);
        var r = ratio(drawn.color, drawn.background);
        var min = minimum(cs.fontSize, cs.fontWeight);

        return { ratio: r, min: min, ok: r >= min, color: drawn.color, background: drawn.background };
    }

    var api = {
        parse: parse,
        luminance: luminance,
        ratio: ratio,
        over: over,
        throughOpacity: throughOpacity,
        weight: weight,
        minimum: minimum,
        zoom: zoom,
        backdrop: backdrop,
        pictures: pictures,
        textRuns: textRuns,
        check: check
    };

    root.PgContrast = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
