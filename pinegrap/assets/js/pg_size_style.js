/**
 * Pinegrap - Enterprise Website Platform
 *
 * A length per screen size for the designer's Size and display rows
 * (Width, Height and their Min and Max, Top, Right, Bottom, Left) and the
 * Text section's Font Size. A value from a size up is a rule on the
 * element's custom style class (`pgs-` and six characters, see
 * assets/js/pg_custom_style.js) inside `@media (min-width: …)` in User
 * Styles; the value for every size is the class's base rule.
 *
 *   PGSizeStyle.SIZES, MIN, PROPS, FONT
 *   PGSizeStyle.media(size)                     // '@media (min-width: 768px)'
 *   PGSizeStyle.sizeOf(mediaLabel)               // 'md', 'xs' for none, '' other
 *   PGSizeStyle.read(text, cls, css)             // { xs: '', sm: '', … }
 *   PGSizeStyle.at(values, size)                 // { value, here, size }
 *   PGSizeStyle.held(text, cls, cssList)         // sizes above xs holding a value
 *   PGSizeStyle.perSize(text, cls, css)          // true when a size above xs holds css
 *   PGSizeStyle.write(text, cls, css, size, v)   // the new User Styles text
 *   PGSizeStyle.fontValue(cssClass)              // a font size class as CSS
 *   PGSizeStyle.fontClass(value, classes)        // the class a value stands for
 *   PGSizeStyle.len(value)                       // a typed length as CSS
 *   PGSizeStyle.hideOn(classes)                  // { phone, tablet, desktop } hidden
 *   PGSizeStyle.hideOnWrite(classes, hidden)     // the classes with those hidden
 *   PGSizeStyle.stickyFrom(classes)              // 'xs', 'md', … or '' not sticky
 *   PGSizeStyle.stickyFromWrite(classes, size)   // the classes sticky from size
 *
 * The browser keeps the inline style and Bootstrap's `fs-*` classes
 * (`!important`) above any User Styles rule, so the designer moves a value
 * held there into the base rule before it writes a size (the part
 * `spacing`, assets/js/style_designer_spacing.js).
 *
 * window.PGSizeStyle in a browser; module.exports under Node. Plain ES5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var SIZES = ['xs', 'sm', 'md', 'lg', 'xl', 'xxl'];
    // Bootstrap 5.3's --bs-breakpoint-* widths.
    var MIN = { sm: 576, md: 768, lg: 992, xl: 1200, xxl: 1400 };
    // The rows' props and the CSS property each writes.
    var PROPS = {
        width: 'width', minWidth: 'min-width', maxWidth: 'max-width',
        height: 'height', minHeight: 'min-height', maxHeight: 'max-height',
        top: 'top', right: 'right', bottom: 'bottom', left: 'left',
        fontSize: 'font-size', aspect: 'aspect-ratio'
    };
    // Bootstrap 5.3.8's font size classes as one value: the responsive
    // size below 1200 px and the fixed one from there, as the class draws.
    var FONT = {
        'fs-1': 'min(1.375rem + 1.5vw, 2.5rem)',
        'fs-2': 'min(1.325rem + 0.9vw, 2rem)',
        'fs-3': 'min(1.3rem + 0.6vw, 1.75rem)',
        'fs-4': 'min(1.275rem + 0.3vw, 1.5rem)',
        'fs-5': '1.25rem',
        'fs-6': '1rem',
        'display-1': 'min(1.625rem + 4.5vw, 5rem)',
        'display-2': 'min(1.575rem + 3.9vw, 4.5rem)',
        'display-3': 'min(1.525rem + 3.3vw, 4rem)',
        'display-4': 'min(1.475rem + 2.7vw, 3.5rem)',
        'display-5': 'min(1.425rem + 2.1vw, 3rem)',
        'display-6': 'min(1.375rem + 1.5vw, 2.5rem)'
    };
    var CLASS_RE = /^pgs-[a-z0-9]{6}$/;
    var INDENT = '  ';

    function U() { return root.PgUserStyles; }
    function C() { return root.PgCustomStyle; }

    function media(size) {
        return MIN[size] ? '@media (min-width: ' + MIN[size] + 'px)' : '';
    }

    function sizeOf(label) {
        var s = String(label || '').trim();
        if (!s) return 'xs';
        var m = /^@media\s*\(\s*min-width\s*:\s*(\d+)px\s*\)$/i.exec(s);
        if (!m) return '';
        for (var k in MIN) if (MIN.hasOwnProperty(k) && MIN[k] === +m[1]) return k;
        return '';
    }

    // The rules of cls alone, base and one per size, in text order.
    function rulesOf(text, cls) {
        if (!U()) return [];
        return U().parse(String(text || '')).filter(function (r) {
            return r.selector.trim() === '.' + cls && sizeOf(r.media) !== '';
        }).map(function (r) { return { size: sizeOf(r.media), rule: r }; });
    }

    function lastDecl(rule, css) {
        var at = -1;
        rule.decls.forEach(function (d, i) { if (d.name.toLowerCase() === css) at = i; });
        return at;
    }

    function read(text, cls, css) {
        var out = {};
        SIZES.forEach(function (s) { out[s] = ''; });
        if (!CLASS_RE.test(cls || '')) return out;
        rulesOf(text, cls).forEach(function (r) {
            var i = lastDecl(r.rule, css);
            if (i !== -1) out[r.size] = r.rule.decls[i].value;
        });
        return out;
    }

    // The value in force at size: its own, else the nearest smaller size's.
    function at(values, size) {
        var i = SIZES.indexOf(size);
        for (var j = i; j >= 0; j--) {
            var v = values[SIZES[j]] || '';
            if (v) return { value: v, here: j === i, size: SIZES[j] };
        }
        return { value: '', here: false, size: '' };
    }

    function held(text, cls, cssList) {
        var has = {};
        cssList.forEach(function (css) {
            var v = read(text, cls, css);
            SIZES.forEach(function (s) { if (s !== 'xs' && v[s]) has[s] = true; });
        });
        return SIZES.filter(function (s) { return has[s]; });
    }

    function perSize(text, cls, css) {
        return held(text, cls, [css]).length > 0;
    }

    // A value that would end the declaration or the rule early.
    function bad(value) {
        return /[{};]/.test(String(value || '').replace(/"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'/g, ''));
    }

    // [open, close] of the block whose at-rule starts at `at` (comments and
    // strings skipped), or null.
    function block(text, at) {
        var open = -1, depth = 0;
        for (var i = at; i < text.length; i++) {
            var c = text.charAt(i);
            if (c === '/' && text.charAt(i + 1) === '*') {
                var e = text.indexOf('*/', i + 2);
                if (e === -1) return null;
                i = e + 1;
            } else if (c === '"' || c === "'") {
                for (i++; i < text.length && text.charAt(i) !== c; i++) if (text.charAt(i) === '\\') i++;
            } else if (c === '{') {
                if (open === -1) open = i;
                depth++;
            } else if (c === '}') {
                if (--depth === 0 && open !== -1) return [open, i];
            }
        }
        return null;
    }

    // Where a size's at-rule starts, its whole range [from, to).
    function mediaRange(text, rule) {
        var at = text.lastIndexOf('@', rule.from);
        var b = at === -1 ? null : block(text, at);
        if (!b || b[1] < rule.to) return null;
        return { from: at, to: b[1] + 1, open: b[0], close: b[1] };
    }

    // Removes the rule, and its @media when it held nothing else.
    function dropRule(text, r) {
        if (r.size !== 'xs') {
            var m = mediaRange(text, r.rule);
            if (m && !text.slice(m.open + 1, r.rule.from).trim() && !text.slice(r.rule.to, m.close).trim()) {
                return U().removeRule(text, { from: m.from, to: m.to });
            }
        }
        return U().removeRule(text, r.rule);
    }

    function blockText(cls, size, css, value) {
        var inner = '.' + cls + ' {\n' + INDENT + css + ': ' + value + ';\n}';
        if (size === 'xs') return inner + '\n';
        return media(size) + ' {\n' + inner.split('\n').map(function (l) { return INDENT + l; }).join('\n') + '\n}\n';
    }

    // Where a new rule for size goes: before the class's first rule of a
    // larger size (its at-rule), else after its last rule of a smaller one,
    // else at the end (-1).
    function insertAt(text, list, size) {
        var rank = SIZES.indexOf(size), after = -1;
        for (var i = 0; i < list.length; i++) {
            var r = list[i], k = SIZES.indexOf(r.size);
            var range = r.size === 'xs' ? { from: r.rule.from, to: r.rule.to } : mediaRange(text, r.rule);
            if (!range) continue;
            if (k > rank) return { at: range.from, before: true };
            after = Math.max(after, range.to);
        }
        return after === -1 ? null : { at: after, before: false };
    }

    function write(text, cls, css, size, value) {
        text = String(text || '');
        value = String(value == null ? '' : value).trim();
        if (!U() || !CLASS_RE.test(cls || '') || SIZES.indexOf(size) === -1 || bad(value)) return text;
        var list = rulesOf(text, cls);
        var own = null;
        list.forEach(function (r) { if (r.size === size && lastDecl(r.rule, css) !== -1) own = r; });
        if (!own) list.forEach(function (r) { if (!own && r.size === size) own = r; });
        if (own) {
            var i = lastDecl(own.rule, css);
            if (value) {
                return i === -1 ? U().addDecl(text, own.rule, css, value) : U().setDecl(text, own.rule, i, css, value);
            }
            if (i === -1) return text;
            // The rule goes when this was all it held (a comment keeps it).
            var d = own.rule.decls[i];
            var rest = text.slice(own.rule.bodyFrom, d.from) + text.slice(d.semi === -1 ? d.to : d.semi + 1, own.rule.bodyTo);
            if (own.rule.decls.length === 1 && !rest.trim()) return dropRule(text, own);
            return U().removeDecl(text, own.rule, i);
        }
        if (!value) return text;
        if (size === 'xs') return C() ? C().write(text, cls, css + ': ' + value) : text;
        var where = insertAt(text, list, size);
        var add = blockText(cls, size, css, value);
        if (!where) {
            var kept = text.replace(/\s+$/, '');
            return (kept ? kept + '\n\n' : '') + add;
        }
        if (where.before) return text.slice(0, where.at) + add + '\n' + text.slice(where.at);
        var tail = text.slice(where.at).replace(/^[ \t]*\n?/, '');
        return text.slice(0, where.at) + '\n\n' + add + (tail ? '\n' + tail : '');
    }

    function fontValue(cssClass) {
        var list = String(cssClass || '').split(/\s+/);
        for (var i = 0; i < list.length; i++) if (FONT.hasOwnProperty(list[i])) return { cls: list[i], value: FONT[list[i]] };
        return null;
    }

    // fs-* first: fs-1 and display-6 draw the same size.
    function fontClass(value, classes) {
        var v = String(value || '').replace(/\s+/g, ' ').trim();
        var pick = '';
        (classes || []).forEach(function (c) {
            if (pick && pick.indexOf('fs-') === 0) return;
            if (FONT[c] === v && (!pick || c.indexOf('fs-') === 0)) pick = c;
        });
        return pick;
    }

    // A bare number is pixels, as the rows' inline value (_sdCssLen()).
    function len(value) {
        var s = String(value == null ? '' : value).trim();
        return /^[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?$/.test(s) ? s + 'px' : s;
    }

    // ── Hide on: Display None per size, as Bootstrap's d-* classes.
    // Phone is XS and SM (below 768 px), Tablet MD, Desktop LG and up, as
    // the canvas bar's widths.
    var GROUPS = { phone: ['xs', 'sm'], tablet: ['md'], desktop: ['lg', 'xl', 'xxl'] };
    var DISPLAY_RE = /^d-(?:(sm|md|lg|xl|xxl)-)?(none|inline|inline-block|block|grid|inline-grid|flex|inline-flex|table|table-row|table-cell|contents)$/;

    function classList(classes) {
        return (Array.isArray(classes) ? classes : String(classes || '').split(/\s+/)).filter(Boolean);
    }

    // The display each size draws: a d-* value, '' where no class sets one.
    // With `reshow`, a d-{size}-block that only shows the element again
    // after a hidden size, with no display set before, reads '' too.
    function displays(list, reshow) {
        var set = {}, out = {}, cur = '', own = false;
        list.forEach(function (c) {
            var m = DISPLAY_RE.exec(c);
            if (m) set[m[1] || 'xs'] = m[2];
        });
        SIZES.forEach(function (s) {
            if (set.hasOwnProperty(s)) {
                cur = (reshow && !own && cur === 'none' && set[s] === 'block') ? '' : set[s];
                if (cur && cur !== 'none') own = true;
            }
            out[s] = cur;
        });
        return out;
    }

    function hideOn(classes) {
        var d = displays(classList(classes));
        var out = {};
        Object.keys(GROUPS).forEach(function (g) {
            out[g] = GROUPS[g].every(function (s) { return d[s] === 'none'; });
        });
        return out;
    }

    // The fewest d-* classes that hide the groups in `hidden` and show the
    // rest with the display they had; shown again after a hidden size, a
    // size takes the display the element has elsewhere, block where it has
    // none of its own.
    function hideOnWrite(classes, hidden) {
        var list = classList(classes);
        var d = displays(list, true);
        var hideAt = {};
        Object.keys(GROUPS).forEach(function (g) {
            if (hidden && hidden[g]) GROUPS[g].forEach(function (s) { hideAt[s] = true; });
        });
        var shown = '';
        SIZES.forEach(function (s) { if (!shown && d[s] && d[s] !== 'none') shown = d[s]; });
        var made = [], prev = '';
        SIZES.forEach(function (s) {
            var t = hideAt[s] ? 'none' : (d[s] === 'none' ? '' : d[s]);
            if (t === prev) return;
            if (!t) {
                t = shown || 'block';
                if (t === prev) return;
            }
            made.push('d-' + (s === 'xs' ? '' : s + '-') + t);
            prev = t;
        });
        var out = [], put = false;
        list.forEach(function (c) {
            if (!DISPLAY_RE.test(c)) { out.push(c); return; }
            if (!put) { out = out.concat(made); put = true; }
        });
        if (!put) out = out.concat(made);
        return out.join(' ');
    }

    // ── Sticky from a size: Position Sticky (position-sticky) at every
    // size, Bootstrap's sticky-{size}-top from a size up.
    var STICKY_FROM = SIZES.slice(1).map(function (s) { return 'sticky-' + s + '-top'; });

    function stickyFrom(classes) {
        var out = '';
        classList(classes).forEach(function (c) {
            if (c === 'position-sticky') out = out || 'xs';
            var i = STICKY_FROM.indexOf(c);
            if (i !== -1) out = SIZES[i + 1];
        });
        return out;
    }

    // `size` '' leaves the element not sticky.
    function stickyFromWrite(classes, size) {
        var add = size === 'xs' ? 'position-sticky' : (STICKY_FROM.indexOf('sticky-' + size + '-top') !== -1 ? 'sticky-' + size + '-top' : '');
        var out = [], put = false;
        classList(classes).forEach(function (c) {
            if (c !== 'position-sticky' && STICKY_FROM.indexOf(c) === -1) { out.push(c); return; }
            if (!put && add) out.push(add);
            put = true;
        });
        if (!put && add) out.push(add);
        return out.join(' ');
    }

    var api = {
        SIZES: SIZES, MIN: MIN, PROPS: PROPS, FONT: FONT, STICKY_FROM: STICKY_FROM,
        media: media, sizeOf: sizeOf, read: read, at: at, held: held, perSize: perSize,
        write: write, fontValue: fontValue, fontClass: fontClass, len: len,
        hideOn: hideOn, hideOnWrite: hideOnWrite, stickyFrom: stickyFrom, stickyFromWrite: stickyFromWrite
    };

    root.PGSizeStyle = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
