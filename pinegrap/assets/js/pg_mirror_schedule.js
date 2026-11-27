/**
 * Pinegrap - Enterprise Website Platform
 *
 * When a mirror of the visual designer's canvas is drawn again, and what of
 * the canvas it leaves out (window.PGMirrorSchedule). Pure: it reads and
 * returns plain values and touches no element or timer.
 *
 * - A mirror is redrawn when the hand rests: REST ms after the last change,
 *   key or pointer release, never while a pointer is held (a drag) and never
 *   while keys keep coming (a text being typed).
 * - A pointer held with nothing happening for HOLD_MAX ms counts as let go:
 *   a release the canvas never heard (the pointer left the window) does not
 *   stop the mirrors for good.
 * - matters(): whether a change seen on the canvas is one a mirror shows.
 *   The editor's own marks (a selection, a drop highlight, a toolbar, a
 *   badge) are not.
 * - fit(): the scale that fits a mirror's width into its box, and the
 *   height its frame takes so the scaled picture fills the box.
 * - sizes(): a mirror's list of widths, the one it shows ticked and those
 *   the canvas or the other mirror show dimmed; pick() the widths after a
 *   choice in it; swap() the widths after "Edit at this size".
 * - selectionMoved(): whether a canvas change moved the selection mark, so
 *   the mirrors' frames follow; follow() where a mirror scrolls to bring
 *   the chosen element into view.
 *
 * Nothing runs at load but the definition of window.PGMirrorSchedule. It is
 * the first source of the designer part 'mirror'
 * (assets/js/style_designer_mirror.min.js, pg_designer_parts()); after
 * editing run `php tools/build_js.php`. tests/designer_mirror_test.php runs
 * it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    // How long the hand rests before the mirrors are drawn again, in ms.
    var REST = 300;
    // A pointer held this long with nothing happening counts as let go.
    var HOLD_MAX = 15000;

    // Classes the editor puts on a canvas element to show its own state.
    var STATE_CLASSES = ['sd-selected', 'sd-multi-selected', 'sd-drop-hl', 'sd-ghost', 'sd-resizing',
        'sd-ctrl-flash', 'sd-tree-drag-src', 'sd-validate-warn', 'sd-tb-inside', 'sd-tb-below', 'sd-tb-end',
        // The Outline lines, the snap grid and the Shared parts tint drawn
        // over the canvas body.
        'sd-outline-grid', 'sd-grid-overlay', 'sd-parts-lit'];
    // Elements the editor adds to the canvas around the design: element
    // toolbars, notes, badges, chips, drop zones, the align guide, the
    // grid areas tinted over a grid box (the part 'grid').
    var CHROME_CLASSES = ['sd-tb', 'sd-persistent-note', 'sd-issue-badge', 'sd-chip-row', 'sd-dz', 'sd-align-guide', 'sd-ga-overlay'];
    var CHROME_PARTS = CHROME_CLASSES.map(function (c) { return '.' + c; }).join(', ');
    var CHROME_SELECTOR = 'script, ' + CHROME_PARTS;
    // Attributes that make an element editable or draggable on the canvas.
    var EDIT_ATTRS = ['contenteditable', 'draggable', 'spellcheck'];

    function initial() {
        return { dirty: false, held: false, last: 0 };
    }

    // The state after `kind` happened at `now` (ms):
    // 'change' the canvas changed, 'key' a key went down on the canvas,
    // 'down' a pointer was pressed or a drag started, 'up' it was let go,
    // 'drawn' the mirrors were drawn.
    function step(state, kind, now) {
        var s = { dirty: !!(state && state.dirty), held: !!(state && state.held), last: (state && state.last) || 0 };
        now = Number(now) || 0;
        if (kind === 'change') {
            s.dirty = true;
            s.last = now;
        } else if (kind === 'key') {
            s.last = now;
        } else if (kind === 'down') {
            s.held = true;
            s.last = now;
        } else if (kind === 'up') {
            s.held = false;
            s.last = now;
        } else if (kind === 'drawn') {
            s.dirty = false;
        }
        return s;
    }

    // When the mirrors are to be drawn (ms), or null when nothing waits.
    // A held pointer puts the draw off until it is let go, or until it has
    // been held HOLD_MAX with nothing happening.
    function due(state) {
        if (!state || !state.dirty) return null;
        var last = state.last || 0;
        return state.held ? last + HOLD_MAX : last + REST;
    }

    function tokens(cls) {
        return String(cls || '').split(/\s+/).filter(function (t) { return t !== ''; });
    }

    function isChrome(cls) {
        return tokens(cls).some(function (t) { return CHROME_CLASSES.indexOf(t) !== -1; });
    }

    // A class list without the editor's state classes.
    function clean(cls) {
        return tokens(cls).filter(function (t) { return STATE_CLASSES.indexOf(t) === -1; }).join(' ');
    }

    // Whether a change seen on the canvas shows in a mirror. `rec`:
    // type 'attributes' with name, old and now (the attribute's values, the
    // owner's class list as cls); 'childList' with nodes, the class lists
    // of the nodes added and removed (null for a text node); 'characterData'.
    function matters(rec) {
        if (!rec) return false;
        if (rec.type === 'characterData') return !isChrome(rec.cls);
        if (isChrome(rec.cls)) return false;
        if (rec.type === 'attributes') {
            var name = String(rec.name || '').toLowerCase();
            if (EDIT_ATTRS.indexOf(name) !== -1) return false;
            if (name === 'class') return clean(rec.old) !== clean(rec.now);
            return rec.old !== rec.now;
        }
        if (rec.type === 'childList') {
            var nodes = rec.nodes || [];
            if (!nodes.length) return false;
            return nodes.some(function (c) { return c === null || !isChrome(c); });
        }
        return true;
    }

    // The scale that fits a frame `width` wide into a box `boxW` by `boxH`
    // (never above 1), and the frame's height before scaling, so the scaled
    // frame is as tall as the box.
    function fit(width, boxW, boxH) {
        width = Math.max(1, Number(width) || 1);
        boxW = Math.max(0, Number(boxW) || 0);
        boxH = Math.max(0, Number(boxH) || 0);
        var scale = Math.min(1, boxW / width);
        if (!(scale > 0)) scale = 1;
        scale = Math.round(scale * 1000) / 1000;
        return { scale: scale, height: Math.round(boxH / scale) };
    }

    // The canvas bar's five widths, the ones a mirror's list offers.
    var SIZES = [375, 576, 768, 992, 1200];

    // The list of mirror `index` of `widths`: each of SIZES with `on` for
    // the width it shows and `where` 'canvas' or 'other' for a width the
    // canvas (at `canvasW`, 0 for Full width) or another mirror shows.
    function sizes(widths, index, canvasW) {
        widths = widths || [];
        return SIZES.map(function (w) {
            var where = '';
            if (w === canvasW) where = 'canvas';
            else if (widths.some(function (o, i) { return i !== index && o === w; })) where = 'other';
            return { w: w, on: widths[index] === w, where: where };
        });
    }

    // The widths after `w` was chosen in mirror `index`'s list; a width
    // dimmed there changes nothing.
    function pick(widths, index, w, canvasW) {
        var out = (widths || []).slice();
        var row = sizes(out, index, canvasW).filter(function (r) { return r.w === w; })[0];
        if (!row || row.where || index < 0 || index >= out.length) return out;
        out[index] = w;
        return out;
    }

    // "Edit at this size" on mirror `index`: the canvas takes that mirror's
    // width and the mirror the canvas's (`canvasW`, 0 for Full width, which
    // hands over 1200). The other mirror never shows the width the swapped
    // one now shows: it takes the first free width of SIZES instead.
    // Null for a mirror that is not there.
    function swap(widths, index, canvasW) {
        var out = (widths || []).slice();
        if (index < 0 || index >= out.length) return null;
        var canvas = out[index];
        var mirror = canvasW > 0 ? canvasW : 1200;
        out[index] = mirror;
        out.forEach(function (w, i) {
            if (i === index || w !== mirror) return;
            out[i] = SIZES.filter(function (s) { return s !== mirror && s !== canvas && out.indexOf(s) === -1; })[0] || w;
        });
        return { widths: out, canvas: canvas };
    }

    // Whether a canvas change put the selection mark on an element or took
    // it off (a mutation record in the shape matters() reads).
    function selectionMoved(rec) {
        if (!rec || rec.type !== 'attributes' || String(rec.name || '').toLowerCase() !== 'class') return false;
        var had = tokens(rec.old).indexOf('sd-selected') !== -1;
        return had !== (tokens(rec.now).indexOf('sd-selected') !== -1);
    }

    // Where a mirror `viewH` px high, scrolled to `scrollY`, scrolls to show
    // an element at `top`..`bottom` (its view's coordinates): null while it
    // is in view or takes no room (that width hides it), else the scroll
    // that brings it a third of the way down.
    function follow(top, bottom, viewH, scrollY) {
        top = Number(top) || 0;
        bottom = Number(bottom) || 0;
        viewH = Math.max(0, Number(viewH) || 0);
        if (bottom <= top) return null;
        if (top >= 0 && bottom <= viewH) return null;
        return Math.max(0, Math.round((Number(scrollY) || 0) + top - viewH / 3));
    }

    window.PGMirrorSchedule = {
        swap: swap,
        selectionMoved: selectionMoved,
        follow: follow,
        SIZES: SIZES,
        sizes: sizes,
        pick: pick,
        REST: REST,
        HOLD_MAX: HOLD_MAX,
        STATE_CLASSES: STATE_CLASSES,
        CHROME_PARTS: CHROME_PARTS,
        CHROME_SELECTOR: CHROME_SELECTOR,
        EDIT_ATTRS: EDIT_ATTRS,
        initial: initial,
        step: step,
        due: due,
        clean: clean,
        matters: matters,
        fit: fit
    };
})();
