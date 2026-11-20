/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the spacing box part: Style › Spacing drawn as one
 * box, outer (margin) and inner (padding) with four sides each, and the
 * screen-size chooser in the section's head. A side's number is clicked and
 * typed (0 to 5, or "auto" on margins), dragged up and down, or stepped with
 * the arrow keys; Shift changes the opposite side too, Alt all four. Grey is
 * inherited or the default, filled blue is set at the chosen size, a frame
 * is the side being typed; on several elements a side whose values differ
 * reads "~". What a change writes is window.PGSpacingBox's (the first source
 * of this part), the classes today's rows write.
 *
 * Not needed to open a page: the core runs this file the first time the
 * Spacing section is shown (_sdSpBoxBind(), pg_designer_parts()) and hands
 * it a host: the elements' classes, the chosen size and the commit.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('spacing', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var B = window.PGSpacingBox;

    // Pixels of vertical pointer travel for one step of a drag.
    var DRAG_STEP = 8;
    // The side that had the focus before a change drew the panel again
    // ('m-t'), given back to the new box.
    var refocus = '';

    function _spbSideName(kind, side) {
        var names = kind === 'm'
            ? { t: _sdT('Margin Top'), e: _sdT('Margin End'), b: _sdT('Margin Bottom'), s: _sdT('Margin Start') }
            : { t: _sdT('Padding Top'), e: _sdT('Padding End'), b: _sdT('Padding Bottom'), s: _sdT('Padding Start') };
        return names[side];
    }

    function _spbText(v) {
        if (v === 'auto') return _sdT('auto');
        return v === '' ? '0' : v;
    }

    // Each side of `kind` over all the host's elements at `size`:
    // { value, here, size, mixed }.
    function _spbRead(classes, kind, size) {
        var reads = classes.map(function (c) { return B.read(c, kind, size); });
        var out = {};
        B.SIDES.forEach(function (side) {
            var first = reads[0][side];
            var mixed = reads.some(function (r) { return r[side].value !== first.value; });
            out[side] = { value: first.value, here: reads.every(function (r) { return r[side].here; }), size: first.size, mixed: mixed };
        });
        return out;
    }

    function _spbSideHtml(kind, side, r, locked) {
        var name = _spbSideName(kind, side);
        var said = r.mixed ? _sdT('— Multiple —') : _spbText(r.value);
        var why = r.mixed ? '' : (r.here ? '' : (r.size ? _sdT('inherited from {var}', r.size.toUpperCase()) : _sdT('default')));
        return '<button type="button" class="sd-spb-v sd-spb-' + side + (r.here && !r.mixed ? ' is-set' : '') + (r.mixed ? ' is-mixed' : '') + '"' +
            ' data-k="' + kind + '" data-s="' + side + '" data-v="' + esc(r.mixed ? '' : r.value) + '"' +
            ' aria-label="' + esc(name + ': ' + said + (why ? ', ' + why : '')) + '"' +
            ' title="' + esc(name + ' — ' + _sdT('Click and type 0 to 5 or auto, or drag up and down. Shift changes the opposite side too, Alt all four.')) + '"' +
            (locked ? ' disabled' : '') + '>' + esc(r.mixed ? '~' : _spbText(r.value)) + '</button>';
    }

    function _spbBoxHtml(host) {
        var m = _spbRead(host.classes, 'm', host.size);
        var p = _spbRead(host.classes, 'p', host.size);
        var sides = function (kind, r) {
            return B.SIDES.map(function (s) { return _spbSideHtml(kind, s, r[s], host.locked); }).join('');
        };
        var core = host.classes.length > 1 ? _sdT('{var} elements', host.classes.length) : _sdT('content');
        return '<div class="sd-spb" role="group" aria-label="' + esc(_sdT('Spacing') + ' · ' + host.size.toUpperCase()) + '">' +
            '<div class="sd-spb-m"><span class="sd-spb-lab" aria-hidden="true">' + esc(_sdT('outer')) + '</span>' + sides('m', m) +
                '<div class="sd-spb-p"><span class="sd-spb-lab" aria-hidden="true">' + esc(_sdT('inner')) + '</span>' + sides('p', p) +
                    '<div class="sd-spb-core">' + esc(core) + '</div>' +
                '</div>' +
            '</div>' +
        '</div>';
    }

    // The size chooser in the section's head: the chosen size pressed, a
    // size holding a value of any element drawn lighter.
    function _spbSizesHtml(host) {
        var has = {};
        host.classes.forEach(function (c) { B.held(c).forEach(function (s) { has[s] = true; }); });
        return B.SIZES.map(function (s) {
            var on = s === host.size;
            return '<button type="button" class="sd-spb-size' + (on ? ' is-on' : '') + (has[s] && !on ? ' is-has' : '') + '" data-size="' + s + '"' +
                ' aria-pressed="' + (on ? 'true' : 'false') + '"' +
                ' title="' + esc(s === 'xs' ? _sdT('Every screen size') : _sdT('From {var} up', s.toUpperCase())) + '">' + s.toUpperCase() + '</button>';
        }).join('');
    }

    // Writes `val` on the side of the button, with the sides Shift and Alt
    // add, as one change of every element of the host.
    function _spbCommit(host, btn, val, shift, alt) {
        var kind = btn.getAttribute('data-k'), side = btn.getAttribute('data-s');
        var to = B.sides(side, shift, alt);
        refocus = kind + '-' + side;
        host.commit(function (list) { return B.write(list, kind, host.size, to.sides, val, to.all); });
    }

    // The side typed into: a field in the button's place; Enter or leaving
    // it writes, Escape gives the button back. Shift or Alt held on the
    // click or on Enter widen the change.
    function _spbEdit(host, btn, mods) {
        var kind = btn.getAttribute('data-k');
        var input = document.createElement('input');
        input.type = 'text';
        input.className = btn.className.replace(/\bis-set\b|\bis-mixed\b/g, '') + ' is-edit';
        input.value = btn.classList.contains('is-mixed') ? '' : (btn.getAttribute('data-v') || '');
        input.setAttribute('aria-label', btn.getAttribute('aria-label') || '');
        input.setAttribute('maxlength', '8');
        input.setAttribute('autocomplete', 'off');
        var done = false;
        var finish = function (write, shift, alt) {
            if (done) return;
            var v = B.value(input.value, kind);
            if (write && v === null) {
                input.classList.add('is-bad');
                input.setAttribute('aria-invalid', 'true');
                return;
            }
            done = true;
            if (write && v !== (btn.getAttribute('data-v') || '') || (write && (shift || alt))) {
                _spbCommit(host, btn, v, shift, alt);
                return;
            }
            if (input.parentNode) input.parentNode.replaceChild(btn, input);
            btn.focus();
        };
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); finish(true, mods.shift || e.shiftKey, mods.alt || e.altKey); }
            else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); finish(false); }
        });
        input.addEventListener('input', function () { input.classList.remove('is-bad'); input.removeAttribute('aria-invalid'); });
        input.addEventListener('blur', function () {
            // An invalid value left behind is not written.
            if (B.value(input.value, kind) === null) { input.value = btn.getAttribute('data-v') || ''; finish(false); return; }
            finish(true, mods.shift, mods.alt);
        });
        btn.parentNode.replaceChild(input, btn);
        input.focus();
        input.select();
    }

    // A drag up raises the value one step per DRAG_STEP pixels, down
    // lowers it; it is written once, on release. A press without travel is
    // a click.
    function _spbDragBind(host, btn) {
        btn.addEventListener('pointerdown', function (e) {
            if (e.button !== 0 || btn.disabled) return;
            var kind = btn.getAttribute('data-k');
            var from = btn.getAttribute('data-v') || '';
            var y0 = e.clientY, moved = false, shown = from;
            var was = btn.textContent;
            try { btn.setPointerCapture(e.pointerId); } catch (err) { /* an old browser keeps the plain click */ }
            var move = function (ev) {
                var dy = y0 - ev.clientY;
                if (!moved && Math.abs(dy) < 4) return;
                moved = true;
                btn.classList.add('is-drag');
                shown = B.step(from, Math.round(dy / DRAG_STEP), kind);
                btn.textContent = _spbText(shown);
            };
            var up = function (ev) {
                btn.removeEventListener('pointermove', move);
                btn.removeEventListener('pointerup', up);
                btn.removeEventListener('pointercancel', up);
                if (!moved) return;
                btn._spbDragged = true;
                btn.classList.remove('is-drag');
                if (ev.type === 'pointercancel' || shown === from) { btn.textContent = was; return; }
                _spbCommit(host, btn, shown, e.shiftKey || ev.shiftKey, e.altKey || ev.altKey);
            };
            btn.addEventListener('pointermove', move);
            btn.addEventListener('pointerup', up);
            btn.addEventListener('pointercancel', up);
        });
    }

    function _spbBind(host, box) {
        box.querySelectorAll('.sd-spb-v').forEach(function (btn) {
            _spbDragBind(host, btn);
            btn.addEventListener('click', function (e) {
                if (btn._spbDragged) { btn._spbDragged = false; return; }
                _spbEdit(host, btn, { shift: e.shiftKey, alt: e.altKey });
            });
            btn.addEventListener('keydown', function (e) {
                var kind = btn.getAttribute('data-k');
                var v = btn.getAttribute('data-v') || '';
                if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    _spbCommit(host, btn, B.step(v, e.key === 'ArrowUp' ? 1 : -1, kind), e.shiftKey, e.altKey);
                } else if (e.key === 'Delete' || e.key === 'Backspace') {
                    e.preventDefault();
                    _spbCommit(host, btn, '', e.shiftKey, e.altKey);
                }
            });
        });
    }

    function _spbSizesBind(host, bar) {
        bar.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('.sd-spb-size') : null;
            if (!b) return;
            e.stopPropagation();
            host.setSize(b.getAttribute('data-size'));
            host.size = b.getAttribute('data-size');
            draw(host);
            var again = bar.querySelector('.sd-spb-size[data-size="' + host.size + '"]');
            if (again) again.focus();
        });
        // Enter and Space press the size, not the head.
        bar.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') e.stopPropagation(); });
    }

    // Draws the box into host.el and the chooser into its section's head.
    function draw(host) {
        var el = host.el;
        if (!el || !el.isConnected) return;
        el.innerHTML = _spbBoxHtml(host);
        _spbBind(host, el);
        var card = el.closest('.sd-card');
        var hdr = card ? card.querySelector(':scope > .sd-sect-hdr') : null;
        if (hdr) {
            var bar = hdr.querySelector(':scope > .sd-spb-sizes');
            if (!bar) {
                bar = document.createElement('span');
                bar.className = 'sd-spb-sizes';
                bar.setAttribute('role', 'group');
                bar.setAttribute('aria-label', _sdT('Screen size'));
                var title = hdr.querySelector(':scope > .sd-sect-title');
                hdr.insertBefore(bar, title ? title.nextSibling : null);
                _spbSizesBind(host, bar);
            }
            bar.innerHTML = _spbSizesHtml(host);
        }
        if (refocus) {
            var parts = refocus.split('-');
            refocus = '';
            var b = el.querySelector('.sd-spb-v[data-k="' + parts[0] + '"][data-s="' + parts[1] + '"]');
            if (b && !b.disabled) b.focus();
        }
    }

    return { draw: draw };
});
