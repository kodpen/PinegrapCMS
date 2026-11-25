/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Focus control of the visual designer's Options panel
 * (window.PGImageFocus): what the control shows for a picture's focus
 * values on the screen the canvas bar shows, and the values a move, a key
 * or "Same on every screen" leaves. Pure: it reads and returns values and
 * touches no element.
 *
 * - PGImageFocus(F) answers the helpers; F is the designer's focus twins
 *   (_sdFocusParse, _sdFocusFormat, _sdFocusForScreen, _sdFocusSet,
 *   _sdFocusSameEverywhere as parse, format, forScreen, set, same), so the
 *   control writes exactly what the site's writer reads.
 * - values: { desktop, tablet, phone }, each '' or a position; screen:
 *   'phone', 'tablet' or 'desktop'.
 * - A value typed before (a pair of words, a decimal) is shown where it
 *   points and is kept as typed: only a move writes a new value.
 *
 * Nothing runs at load but the definition of window.PGImageFocus. It is the
 * first source of the designer part 'focus'
 * (assets/js/style_designer_focus.min.js, pg_designer_parts()); after
 * editing run `php tools/build_js.php`. tests/designer_image_focus_control_test.php
 * runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
window.PGImageFocus = function (F) {
    'use strict';

    var SCREENS = ['phone', 'tablet', 'desktop'];

    function clamp(v) {
        v = parseFloat(v);
        return isNaN(v) ? 50 : Math.max(0, Math.min(100, v));
    }

    // The point of a value: its pair, or the middle for none or junk.
    function point(value) {
        var xy = value ? F.parse(value) : null;
        return xy ? [xy[0], xy[1]] : [50, 50];
    }

    // What the control shows on `screen`: the value (its own or a larger
    // screen's), its point, whether it is the screen's own, what the wider
    // screens keep, a value typed before, and each frame's point.
    function view(values, screen) {
        values = values || {};
        if (SCREENS.indexOf(screen) < 0) screen = 'desktop';
        var value = F.forScreen(values, screen);
        var xy = point(value);
        var own = screen !== 'desktop' && !!values[screen] && F.parse(values[screen]) !== null;
        var wider = own ? F.forScreen(values, screen === 'phone' ? 'tablet' : 'desktop') : '';
        var frames = {};
        SCREENS.forEach(function (s) {
            var v = F.forScreen(values, s);
            frames[s] = { xy: point(v), own: s !== 'desktop' && !!values[s] && F.parse(values[s]) !== null };
        });
        return {
            screen: screen,
            value: value,
            set: value !== '' && F.parse(value) !== null,
            xy: xy,
            own: own,
            wider: wider !== '' ? point(wider) : null,
            typed: typed(value) ? String(value).trim() : '',
            frames: frames,
            any: SCREENS.some(function (s) { return !!values[s]; })
        };
    }

    // True for a value kept as typed: readable, but not as the control
    // writes it ('center top', '12.5% 40%').
    function typed(value) {
        var xy = value ? F.parse(value) : null;
        return !!xy && F.format(xy[0], xy[1]) !== String(value).trim();
    }

    // The values with the screen's value moved to (x, y).
    function move(values, screen, x, y) {
        return F.set(values || {}, screen, F.format(clamp(x), clamp(y)));
    }

    // The point after a key on the focused point: the arrows by 1 %, Home
    // to the middle; null for any other key.
    function key(xy, name) {
        var x = Math.round(xy[0]), y = Math.round(xy[1]);
        switch (name) {
            case 'ArrowLeft': x -= 1; break;
            case 'ArrowRight': x += 1; break;
            case 'ArrowUp': y -= 1; break;
            case 'ArrowDown': y += 1; break;
            case 'Home': x = 50; y = 50; break;
            default: return null;
        }
        return [clamp(x), clamp(y)];
    }

    // The point under a pointer at (cx, cy) on a picture drawn in `rect`.
    function fromPointer(rect, cx, cy) {
        var w = rect && rect.width > 0 ? rect.width : 1;
        var h = rect && rect.height > 0 ? rect.height : 1;
        return [clamp((cx - rect.left) / w * 100), clamp((cy - rect.top) / h * 100)];
    }

    // "Reset": no value on any screen.
    function reset() {
        return { desktop: '', tablet: '', phone: '' };
    }

    // The shape of each frame as width / height. A fixed ratio (an Image's
    // Aspect Ratio, '16x9') holds on every screen; else the screen shown
    // takes the shape measured on the canvas and the others a typical one.
    function shapes(ratio, screen, measured) {
        var m = /^(\d+)x(\d+)$/.exec(String(ratio || ''));
        var fixed = m && +m[2] > 0 ? +m[1] / +m[2] : 0;
        var out = { phone: 0.8, tablet: 4 / 3, desktop: 2.4 };
        SCREENS.forEach(function (s) {
            if (fixed) out[s] = fixed;
            else if (s === screen && measured > 0) out[s] = Math.max(0.4, Math.min(4, measured));
        });
        return out;
    }

    return { view: view, typed: typed, move: move, key: key, fromPointer: fromPointer, reset: reset, shapes: shapes, same: F.same };
};
