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
        'sd-ctrl-flash', 'sd-tree-drag-src', 'sd-validate-warn', 'sd-tb-inside', 'sd-tb-below', 'sd-tb-end'];
    // Elements the editor adds to the canvas around the design: element
    // toolbars, notes, badges, chips, drop zones, the align guide.
    var CHROME_CLASSES = ['sd-tb', 'sd-persistent-note', 'sd-issue-badge', 'sd-chip-row', 'sd-dz', 'sd-align-guide'];
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

    window.PGMirrorSchedule = {
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
