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
 * The same drawing holds the four corners of Style › Border and corner: a
 * side's number writes the radius class of that side (its two corners),
 * the centre the all-corners class, each corner drawn and numbered as the
 * browser rounds it; Shift, Alt, typing, dragging and the keys work as on
 * the spacing box. What a change writes is window.PGCornerBox's.
 *
 * On one element of a design with themes, a side of the box and the
 * corners' centre also take a design variable: "Variables" beside the
 * field a side is typed in opens the list of the part 'variables' ("Base
 * unit", "Section rhythm", the units, "Corners", the designer's own sizes);
 * a bound side is an inline style the box marks with braces, and its chip
 * with "Detach" is a row under the box. Binding drops the side's classes,
 * which are !important; writing a class drops the side's inline value.
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
    var R = window.PGCornerBox;
    var Z = window.PGSizeStyle;

    // Pixels of vertical pointer travel for one step of a drag.
    var DRAG_STEP = 8;
    // The side that had the focus before a change drew the panel again
    // ('m-t'), given back to the new box.
    var refocus = '';
    // The side whose typed field opens again after its binding went
    // ("Custom value…" of the list), as 'p-t'.
    var reedit = '';

    function _spbSideName(kind, side) {
        var names = kind === 'm'
            ? { t: _sdT('Margin Top'), e: _sdT('Margin End'), b: _sdT('Margin Bottom'), s: _sdT('Margin Start') }
            : { t: _sdT('Padding Top'), e: _sdT('Padding End'), b: _sdT('Padding Bottom'), s: _sdT('Padding Start') };
        return names[side];
    }

    // A value as the box shows it; kind 'r' is a radius (none is '–').
    function _spbText(v, kind) {
        if (v === 'auto') return _sdT('auto');
        if (v === 'circle') return _sdT('circle');
        if (v === 'pill') return _sdT('pill');
        if (v === '') return kind === 'r' ? '–' : '0';
        return v;
    }

    // A typed value and a step of a drag or an arrow key, by kind.
    function _spbVal(kind, side, text) {
        return kind === 'r' ? R.value(text, side) : B.value(text, kind);
    }
    function _spbStep(kind, from, delta) {
        return kind === 'r' ? R.step(from, delta) : B.step(from, delta, kind);
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

    // ── Design variables on a side

    // The inline property of a side ('r' 'all' is the corners' centre);
    // '' where a side takes none.
    function _spbProp(kind, side) {
        if (kind === 'r') return side === 'all' ? 'border-radius' : '';
        return B.styleProp(kind, side);
    }

    // A side's inline value on a host that offers variables:
    // { value, ref } (ref null for a typed value), or null.
    function _spbInline(host, kind, side) {
        var prop = host.vars ? _spbProp(kind, side) : '';
        var v = prop ? String(host.style(prop) || '').trim() : '';
        return v ? { value: v, ref: host.parse(v) } : null;
    }

    // The side's button as an inline value draws it: braces for a bound
    // side, the typed value as written.
    function _spbInlineBtn(kind, side, name, inl, cls, locked) {
        var said = inl.ref ? name + ': ' + _sdT('Variable') : name + ': ' + inl.value;
        return '<button type="button" class="sd-spb-v ' + cls + ' is-set' + (inl.ref ? ' is-var' : ' is-lit') + '"' +
            ' data-k="' + kind + '" data-s="' + side + '" data-v=""' + (inl.ref ? ' data-var="1"' : '') +
            ' aria-label="' + esc(said) + '" title="' + esc(inl.ref ? name : name + ': ' + inl.value) + '"' +
            (locked ? ' disabled' : '') + '>' +
            (inl.ref ? '<span class="bi bi-braces" aria-hidden="true"></span>' : esc(inl.value)) + '</button>';
    }

    // The chips of the bound sides, a row each under the box.
    function _spbVarRows(host, kinds) {
        if (!host.vars) return '';
        var h = '';
        kinds.forEach(function (k) {
            var sides = k === 'r' ? ['all'] : B.SIDES;
            sides.forEach(function (side) {
                var inl = _spbInline(host, k, side);
                if (!inl || !inl.ref) return;
                var label = k === 'r' ? _sdT('Corners') : _spbSideName(k, side);
                h += '<div class="sd-spb-var"><span class="sd-spb-var-lab">' + esc(label) + '</span>' +
                    '<div class="sd-var-ctl sd-var-bound" data-sd-var="' + (k === 'r' ? 'radius' : 'space') + '" data-sd-var-prop="' + _spbProp(k, side) + '"' +
                    ' data-sd-var-side="' + k + '-' + side + '">' + host.chip(k === 'r' ? 'radius' : 'space', inl.ref) + '</div></div>';
            });
        });
        return h ? '<div class="sd-spb-vars">' + h + '</div>' : '';
    }

    // What the list needs of a side's place: the class clean-up of a
    // binding and the way out to the side's typed field.
    function _spbVarHook(host, ctl) {
        var ks = String(ctl.getAttribute('data-sd-var-side') || '').split('-');
        var kind = ks[0], side = ks[1], prop = ctl.getAttribute('data-sd-var-prop');
        ctl._sdVarClean = function (list) {
            return kind === 'r' ? R.write(list, R.SIDES, '', true) : B.clearSide(list, kind, side);
        };
        ctl._sdVarTyped = function () {
            if (_spbInline(host, kind, side)) {
                reedit = kind + '-' + side;
                host.commit(function (c) { return c; }, [prop]);
                return;
            }
            var b = host.el.querySelector('.sd-spb-v[data-k="' + kind + '"][data-s="' + side + '"]');
            if (b && !b.disabled) _spbEdit(host, b, {});
        };
    }

    function _spbVarBind(host) {
        host.el.querySelectorAll('.sd-var-ctl[data-sd-var-side]').forEach(function (ctl) { _spbVarHook(host, ctl); });
    }

    // Opens the list for a side: from its chip when bound, else from a
    // place of no height under the box, made for the list to hang from.
    function _spbVarOpen(host, kind, side) {
        var key = kind + '-' + side;
        var ctl = host.el.querySelector('.sd-var-ctl[data-sd-var-side="' + key + '"]');
        if (!ctl) {
            host.el.querySelectorAll('.sd-spb-slot').forEach(function (o) { o.remove(); });
            ctl = document.createElement('div');
            ctl.className = 'sd-var-ctl sd-spb-slot';
            ctl.setAttribute('data-sd-var', kind === 'r' ? 'radius' : 'space');
            ctl.setAttribute('data-sd-var-prop', _spbProp(kind, side));
            ctl.setAttribute('data-sd-var-side', key);
            ctl.innerHTML = '<button type="button" hidden data-sd-var-open></button>';
            host.el.appendChild(ctl);
            _spbVarHook(host, ctl);
        }
        // The core's panel listener opens the list (_sdVarOpen()).
        var open = ctl.querySelector('[data-sd-var-open]');
        if (open) open.click();
    }

    function _spbSideHtml(kind, side, r, locked, host) {
        var name = _spbSideName(kind, side);
        var inl = host ? _spbInline(host, kind, side) : null;
        if (inl) return _spbInlineBtn(kind, side, name, inl, 'sd-spb-' + side, locked);
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
            return B.SIDES.map(function (s) { return _spbSideHtml(kind, s, r[s], host.locked, host); }).join('');
        };
        var core = host.classes.length > 1 ? _sdT('{var} elements', host.classes.length) : _sdT('content');
        return '<div class="sd-spb" role="group" aria-label="' + esc(_sdT('Spacing') + ' · ' + host.size.toUpperCase()) + '">' +
            '<div class="sd-spb-m"><span class="sd-spb-lab" aria-hidden="true">' + esc(_sdT('outer')) + '</span>' + sides('m', m) +
                '<div class="sd-spb-p"><span class="sd-spb-lab" aria-hidden="true">' + esc(_sdT('inner')) + '</span>' + sides('p', p) +
                    '<div class="sd-spb-core">' + esc(core) + '</div>' +
                '</div>' +
            '</div>' +
        '</div>' + _spbVarRows(host, ['m', 'p']);
    }

    // The size chooser in the section's head: the chosen size pressed, a
    // size holding a value of any element drawn lighter.
    function _spbSizesHtml(host) {
        var has = {};
        host.classes.forEach(function (c) { B.held(c).forEach(function (s) { has[s] = true; }); });
        return _spbChooserHtml(host.size, has);
    }

    // The six sizes, the chosen one pressed; a size holding a value of any
    // element carries a dot (is-has).
    function _spbChooserHtml(size, has) {
        return B.SIZES.map(function (s) {
            var on = s === size;
            var tip = s === 'xs' ? _sdT('Every screen size') : _sdT('From {var} up', s.toUpperCase());
            return '<button type="button" class="sd-spb-size' + (on ? ' is-on' : '') + (has[s] ? ' is-has' : '') + '" data-size="' + s + '"' +
                ' aria-pressed="' + (on ? 'true' : 'false') + '"' +
                ' title="' + esc(tip + (has[s] ? ' · ' + _sdT('a value is set for this size') : '')) + '">' + s.toUpperCase() + '</button>';
        }).join('');
    }

    // The chooser's place in the head of the section holding el.
    function _spbBar(el) {
        var card = el.closest('.sd-card');
        var hdr = card ? card.querySelector(':scope > .sd-sect-hdr') : null;
        if (!hdr) return null;
        var bar = hdr.querySelector(':scope > .sd-spb-sizes');
        if (!bar) {
            bar = document.createElement('span');
            bar.className = 'sd-spb-sizes';
            bar.setAttribute('role', 'group');
            bar.setAttribute('aria-label', _sdT('Screen size'));
            var title = hdr.querySelector(':scope > .sd-sect-title');
            hdr.insertBefore(bar, title ? title.nextSibling : null);
            _spbSizesBind(bar);
        }
        return bar;
    }

    // Writes `val` on the side of the button, with the sides Shift and Alt
    // add, as one change of every element of the host.
    // A side's inline value goes with the class written on it; on the
    // corners any write drops the centre's, which rounds all four.
    function _spbCommit(host, btn, val, shift, alt) {
        var kind = btn.getAttribute('data-k'), side = btn.getAttribute('data-s');
        refocus = kind + '-' + side;
        if (kind === 'r') {
            var rto = R.sides(side, shift, alt);
            host.commit(function (list) { return R.write(list, rto.sides, val, rto.all); }, host.vars ? ['border-radius'] : null);
            return;
        }
        var to = B.sides(side, shift, alt);
        host.commit(function (list) { return B.write(list, kind, host.size, to.sides, val, to.all); },
            host.vars ? to.sides.map(function (s) { return B.styleProp(kind, s); }) : null);
    }

    // The side typed into: a field in the button's place; Enter or leaving
    // it writes, Escape gives the button back. Shift or Alt held on the
    // click or on Enter widen the change.
    function _spbEdit(host, btn, mods) {
        var kind = btn.getAttribute('data-k'), side = btn.getAttribute('data-s');
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
            var v = _spbVal(kind, side, input.value);
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
            if (_spbVal(kind, side, input.value) === null) { input.value = btn.getAttribute('data-v') || ''; finish(false); return; }
            finish(true, mods.shift, mods.alt);
        });
        // "Variables" beside the field, on a side that takes one: pressing
        // it keeps the field's focus until the click, which gives the
        // button back and opens the list.
        var vb = null;
        if (host.vars && _spbProp(kind, side)) {
            vb = document.createElement('button');
            vb.type = 'button';
            vb.className = 'sd-var-pick sd-spb-varbtn sd-spb-vb-' + side;
            vb.title = _sdT('Variables');
            vb.setAttribute('aria-label', _sdT('Choose a variable'));
            vb.innerHTML = '<span class="bi bi-braces" aria-hidden="true"></span>';
            vb.addEventListener('pointerdown', function (e) { e.preventDefault(); });
            vb.addEventListener('click', function () {
                done = true;
                vb.remove();
                if (input.parentNode) input.parentNode.replaceChild(btn, input);
                _spbVarOpen(host, kind, side);
            });
            var gone = finish;
            finish = function (write, shift, alt) {
                gone(write, shift, alt);
                // A Tab from the field reaches the button; anything else
                // takes it away.
                if (done) setTimeout(function () { if (document.activeElement !== vb) vb.remove(); }, 0);
            };
            vb.addEventListener('blur', function () {
                setTimeout(function () { if (document.activeElement !== vb) vb.remove(); }, 0);
            });
        }
        btn.parentNode.replaceChild(input, btn);
        if (vb) input.parentNode.insertBefore(vb, input.nextSibling);
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
                shown = _spbStep(kind, from, Math.round(dy / DRAG_STEP));
                btn.textContent = _spbText(shown, kind);
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
            // A bound side opens the list; Delete removes its binding.
            if (btn.hasAttribute('data-var')) {
                btn.addEventListener('click', function () { _spbVarOpen(host, btn.getAttribute('data-k'), btn.getAttribute('data-s')); });
                btn.addEventListener('keydown', function (e) {
                    if (e.key !== 'Delete' && e.key !== 'Backspace') return;
                    e.preventDefault();
                    refocus = btn.getAttribute('data-k') + '-' + btn.getAttribute('data-s');
                    host.commit(function (c) { return c; }, [_spbProp(btn.getAttribute('data-k'), btn.getAttribute('data-s'))]);
                });
                return;
            }
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
                    _spbCommit(host, btn, _spbStep(kind, v, e.key === 'ArrowUp' ? 1 : -1), e.shiftKey, e.altKey);
                } else if (e.key === 'Delete' || e.key === 'Backspace') {
                    e.preventDefault();
                    _spbCommit(host, btn, '', e.shiftKey, e.altKey);
                }
            });
        });
    }

    // The bar outlives a draw of the box: it reads the host of the last one.
    function _spbSizesBind(bar) {
        bar.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('.sd-spb-size') : null;
            var host = bar._spbHost;
            if (!b || !host) return;
            e.stopPropagation();
            var size = b.getAttribute('data-size');
            var card = bar.closest('[data-sd-card]');
            var sid = card ? card.getAttribute('data-sd-card') : '';
            // The panel is drawn again at the new size: the focus goes to
            // the same size in the new head.
            host.setSize(size);
            setTimeout(function () {
                var again = document.querySelector('#sd-properties [data-sd-card="' + sid + '"] .sd-spb-size[data-size="' + size + '"]');
                if (again) again.focus();
            }, 0);
        });
        // Enter and Space press the size, not the head.
        bar.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') e.stopPropagation(); });
    }

    // ── The corners (Style › Border and corner)

    // The drawing's radius of a value, in pixels of the 240 px panel ('' the
    // element's own default, drawn as a hint of a curve).
    var RD_PX = { '': '3px', '0': '0', '1': '4px', '2': '6px', '3': '9px', '4': '13px', '5': '18px', circle: '50%', pill: '999px' };

    // Sides, the all-corners value and the corners over all the host's
    // elements: each { value, here, mixed }.
    function _rdbRead(classes) {
        var reads = classes.map(function (c) { return R.read(c); });
        var one = function (get) {
            var first = get(reads[0]);
            return {
                value: first.value,
                here: reads.every(function (r) { return get(r).here; }),
                mixed: reads.some(function (r) { return get(r).value !== first.value; })
            };
        };
        var out = { all: one(function (r) { return r.all; }), corners: {} };
        R.SIDES.forEach(function (side) { out[side] = one(function (r) { return r[side]; }); });
        R.CORNERS.forEach(function (c) {
            out.corners[c] = one(function (r) { return { value: r.corners[c], here: true }; });
        });
        return out;
    }

    function _rdbBtnHtml(side, r, locked, host) {
        var names = { all: _sdT('Radius'), t: _sdT('Top Radius'), e: _sdT('Right Radius'), b: _sdT('Bottom Radius'), s: _sdT('Left Radius') };
        var inl = side === 'all' && host ? _spbInline(host, 'r', 'all') : null;
        if (inl) return _spbInlineBtn('r', 'all', names.all, inl, 'sd-rdb-all', locked);
        var said = r.mixed ? _sdT('— Multiple —') : _spbText(r.value, 'r');
        var why = r.mixed || r.here ? '' : (side !== 'all' && r.value !== '' ? _sdT('from all corners') : _sdT('default'));
        var tip = side === 'all'
            ? _sdT('Click and type 0 to 5, circle or pill, or drag up and down. Alt also removes the side values.')
            : _sdT('Click and type 0 to 5, or drag up and down. Shift changes the opposite side too, Alt all four corners.');
        return '<button type="button" class="sd-spb-v ' + (side === 'all' ? 'sd-rdb-all' : 'sd-spb-' + side) + (r.here && !r.mixed ? ' is-set' : '') + (r.mixed ? ' is-mixed' : '') + '"' +
            ' data-k="r" data-s="' + side + '" data-v="' + esc(r.mixed ? '' : r.value) + '"' +
            ' aria-label="' + esc(names[side] + ': ' + said + (why ? ', ' + why : '')) + '"' +
            ' title="' + esc(names[side] + ' — ' + tip) + '"' +
            (locked ? ' disabled' : '') + '>' + esc(r.mixed ? '~' : _spbText(r.value, 'r')) + '</button>';
    }

    function _rdbBoxHtml(host) {
        var r = _rdbRead(host.classes);
        var shape = R.CORNERS.map(function (c) {
            var v = r.corners[c].value;
            return '--sd-rdb-' + c + ':' + (RD_PX.hasOwnProperty(v) ? RD_PX[v] : RD_PX['']);
        }).join(';');
        var corners = R.CORNERS.map(function (c) {
            var k = r.corners[c];
            return '<span class="sd-rdb-c sd-rdb-' + c + '" aria-hidden="true">' + esc(k.mixed ? '~' : _spbText(k.value, 'r')) + '</span>';
        }).join('');
        return '<div class="sd-rdb" role="group" aria-label="' + esc(_sdT('Corners')) + '">' +
            corners +
            R.SIDES.map(function (s) { return _rdbBtnHtml(s, r[s], host.locked); }).join('') +
            '<div class="sd-rdb-shape" style="' + esc(shape) + '">' + _rdbBtnHtml('all', r.all, host.locked, host) + '</div>' +
        '</div>' + _spbVarRows(host, ['r']);
    }

    // The typed field of the side whose binding "Custom value…" removed,
    // once the box is drawn again (kind 'r' the corners, '' the spacing).
    function _spbReedit(host, kind) {
        if (!reedit || (reedit.indexOf('r-') === 0) !== (kind === 'r')) return;
        var ks = reedit.split('-');
        reedit = '';
        refocus = '';
        var b = host.el.querySelector('.sd-spb-v[data-k="' + ks[0] + '"][data-s="' + ks[1] + '"]');
        if (b && !b.disabled) _spbEdit(host, b, {});
    }

    // Draws the corners into host.el (no size chooser: radius classes have
    // no screen-size forms).
    function corners(host) {
        var el = host.el;
        if (!el || !el.isConnected) return;
        el.innerHTML = _rdbBoxHtml(host);
        _spbBind(host, el);
        _spbVarBind(host);
        _spbReedit(host, 'r');
        if (refocus.indexOf('r-') === 0) {
            var side = refocus.slice(2);
            refocus = '';
            var b = el.querySelector('.sd-spb-v[data-k="r"][data-s="' + side + '"]');
            if (b && !b.disabled) b.focus();
        }
    }

    // Draws the box into host.el and the chooser into its section's head.
    function draw(host) {
        var el = host.el;
        if (!el || !el.isConnected) return;
        el.innerHTML = _spbBoxHtml(host);
        _spbBind(host, el);
        _spbVarBind(host);
        _spbReedit(host, '');
        var bar = _spbBar(el);
        if (bar) {
            bar._spbHost = host;
            bar.innerHTML = _spbSizesHtml(host);
        }
        if (refocus && refocus.indexOf('r-') !== 0) {
            var parts = refocus.split('-');
            refocus = '';
            var b = el.querySelector('.sd-spb-v[data-k="' + parts[0] + '"][data-s="' + parts[1] + '"]');
            if (b && !b.disabled) b.focus();
        }
    }


    // ── A length per screen size (Size and display, Text › Font Size)

    // The rows' fields this part reads at a size: [field, prop].
    function _szFields(sect) {
        var out = [];
        sect.querySelectorAll('input[data-prop][data-sd-len]').forEach(function (f) {
            var key = f.getAttribute('data-prop');
            if (Z.PROPS.hasOwnProperty(key) && key !== 'fontSize') out.push([f, key]);
        });
        sect.querySelectorAll('select[data-stylegroup]').forEach(function (f) {
            if (/(^|,)fs-1(,|$)/.test(f.getAttribute('data-stylegroup') || '')) out.push([f, 'fontSize']);
        });
        return out;
    }

    function _szSizing(v) {
        if (typeof v === 'string') return v.trim();
        return (typeof v === 'number' && isFinite(v) && v !== 0) ? String(v) : '';
    }

    // An image's width and height are its attributes, not a length here.
    function _szSkips(n, key) {
        return (key === 'width' || key === 'height') && n.type === 'content' && n.props && n.props.contentType === 'image';
    }

    // The every-size value an element holds outside User Styles: the
    // inline length, or Bootstrap's fs-* class (it wins with !important).
    function _szOutside(n, key) {
        if (key === 'fontSize') {
            var f = Z.fontValue(n.props.cssClass);
            return f && f.cls.indexOf('fs-') === 0 ? f : null;
        }
        var v = _szSizing(n.props[key]);
        return v ? { cls: '', value: Z.len(v) } : null;
    }

    // An element's value per size for a row, xs from where it is held.
    function _szValues(text, n, key) {
        var cls = window.PgCustomStyle.classOf(n.props.cssClass);
        var vals = Z.read(text, cls, Z.PROPS[key]);
        var out = _szOutside(n, key);
        if (out) vals.xs = out.value;
        else if (key === 'fontSize' && !vals.xs) {
            var f = Z.fontValue(n.props.cssClass);
            if (f) vals.xs = f.value;
        }
        return vals;
    }

    // Writes `value` for the row `key` at the host's size on every element,
    // as one change: a class made where one is missing, and the every-size
    // value moved into the base rule first, so the size's rule can win.
    function _szWrite(host, key, value) {
        var css = Z.PROPS[key], size = host.size;
        var text = host.text();
        var changes = host.nodes.map(function (n) {
            if (!n || !n.props || _szSkips(n, key)) return null;
            var list = String(n.props.cssClass || '').split(/\s+/).filter(Boolean);
            var cls = window.PgCustomStyle.classOf(list);
            var ch = { props: {} };
            if (!cls) {
                if (!value) return null;
                cls = host.newClass(text);
                if (!cls) return null;
                list.push(cls);
            }
            var out = _szOutside(n, key);
            if (out) {
                text = Z.write(text, cls, css, 'xs', out.value);
                if (out.cls) list = list.filter(function (c) { return c !== out.cls; });
                else ch.props[key] = '';
            }
            text = Z.write(text, cls, css, size, value);
            ch.cssClass = list.join(' ');
            return ch;
        });
        host.commit(text, changes);
    }

    // The field in force at the size: its own value, else the value it
    // takes from a smaller size as the hint; on several elements that
    // differ, empty with "— Multiple —".
    function _szTake(host, f, key, text) {
        var nodes = host.nodes.filter(function (n) { return n && n.props && !_szSkips(n, key); });
        if (!nodes.length) return;
        var reads = nodes.map(function (n) { return Z.at(_szValues(text, n, key), host.size); });
        var first = reads[0];
        var mixed = reads.some(function (r) { return r.value !== first.value || r.here !== first.here; });
        var css = Z.PROPS[key];
        // Taken over at a size, or where a class holds this row by size
        // (the every-size value is then its base rule, not the inline one).
        var held = host.size !== 'xs' || nodes.some(function (n) {
            var cls = window.PgCustomStyle.classOf(n.props.cssClass);
            var v = Z.read(text, cls, css);
            return Z.perSize(text, cls, css) || !!v.xs;
        });
        if (!held) return;
        var at = host.size === 'xs' ? '' : _sdT('From {var} up', host.size.toUpperCase());
        var from = !mixed && !first.here && first.size ? _sdT('from {var}', first.size.toUpperCase()) : '';
        var nf = f.cloneNode(true);
        nf.classList.add('sd-sz-at');
        nf.disabled = !!host.locked;
        if (at) nf.title = at;
        if (f.tagName === 'SELECT') {
            var classes = (f.getAttribute('data-stylegroup') || '').split(',').filter(Boolean);
            var mine = mixed || !first.here ? '' : first.value;
            var pick = mine ? Z.fontClass(mine, classes) : '';
            if (mine && !pick) {
                var o = document.createElement('option');
                o.value = '=' + mine;
                o.textContent = mine;
                nf.appendChild(o);
                pick = '=' + mine;
            }
            nf.value = pick;
            if (!mine && nf.options[0]) {
                var inh = mixed ? '' : Z.fontClass(first.value, classes);
                nf.options[0].textContent = mixed ? _sdT('— Multiple —')
                    : (inh ? nf.querySelector('option[value="' + inh + '"]').textContent + ' (' + from + ')' : (first.value ? first.value + ' (' + from + ')' : nf.options[0].textContent));
            }
            nf.addEventListener('change', function () {
                var v = nf.value;
                _szWrite(host, key, v.charAt(0) === '=' ? v.slice(1) : (v ? Z.FONT[v] || '' : ''));
            });
        } else {
            nf.value = mixed || !first.here ? '' : first.value;
            if (mixed) nf.placeholder = _sdT('— Multiple —');
            else if (!first.here && first.value) nf.placeholder = first.value + ' (' + from + ')';
            var was = nf.value;
            nf.addEventListener('change', function () {
                var why = host.lenCheck(nf.value);
                nf.classList.toggle('is-invalid', !!why);
                if (why) { nf.title = why; return; }
                if (nf.value.trim() === was.trim()) return;
                _szWrite(host, key, Z.len(nf.value));
            });
        }
        f.parentNode.replaceChild(nf, f);
    }

    // ── Hide on and Sticky from (Size and display), Bootstrap's classes

    function _cssOf(n) { return n && n.props ? String(n.props.cssClass || '') : ''; }

    // Writes each element's classes through `fn(classes)` as one change.
    function _clsCommit(host, fn) {
        host.commit(host.text(), host.nodes.map(function (n) {
            if (!n || !n.props) return null;
            var next = fn(_cssOf(n));
            return next === _cssOf(n) ? null : { cssClass: next };
        }));
    }

    // "Hide on" under Display: Phone, Tablet and Desktop pressed where every
    // element is hidden at those sizes (Display None per size).
    function _hoDraw(host, sect) {
        var box = sect.querySelector('[data-sd-hideon]');
        var nodes = host.nodes.filter(function (n) { return n && n.props; });
        if (!box || !nodes.length) return;
        var reads = nodes.map(function (n) { return Z.hideOn(_cssOf(n)); });
        var names = [['phone', _sdT('Phone')], ['tablet', _sdT('Tablet')], ['desktop', _sdT('Desktop')]];
        box.className = 'sd-sp-row';
        box.innerHTML = '<span class="sd-sp-label">' + esc(_sdT('Hide on')) + '</span>' +
            '<div class="sd-seg-grp" role="group" aria-label="' + esc(_sdT('Hide on')) + '">' +
            names.map(function (g) {
                var on = reads.every(function (r) { return r[g[0]]; });
                return '<button type="button" class="sd-seg' + (on ? ' sd-seg-on' : '') + '" data-sd-hide="' + g[0] + '"' +
                    ' aria-pressed="' + (on ? 'true' : 'false') + '"' + (host.locked ? ' disabled' : '') + '>' + esc(g[1]) + '</button>';
            }).join('') + '</div>';
        box.hidden = false;
        box.querySelectorAll('[data-sd-hide]').forEach(function (b) {
            b.addEventListener('click', function () {
                var g = b.getAttribute('data-sd-hide');
                var hide = b.getAttribute('aria-pressed') !== 'true';
                _clsCommit(host, function (c) {
                    var h = Z.hideOn(c);
                    h[g] = hide;
                    return Z.hideOnWrite(c, h);
                });
            });
        });
    }

    // "Sticky from" under Position, while it is Sticky: every size
    // (position-sticky) or a size up (sticky-md-top).
    function _sfDraw(host, sect) {
        var box = sect.querySelector('[data-sd-stickyfrom]');
        var nodes = host.nodes.filter(function (n) { return n && n.props; });
        if (!box || !nodes.length) return;
        var reads = nodes.map(function (n) { return Z.stickyFrom(_cssOf(n)); });
        if (reads.some(function (r) { return !r; })) return;
        var mixed = reads.some(function (r) { return r !== reads[0]; });
        box.className = 'sd-sp-row';
        box.innerHTML = '<span class="sd-sp-label">' + esc(_sdT('Sticky from')) + '</span>' +
            '<select class="form-control form-control-sm" data-sd-sticky-from' + (host.locked ? ' disabled' : '') + '>' +
            (mixed ? '<option value="" selected>' + esc(_sdT('— Multiple —')) + '</option>' : '') +
            Z.SIZES.map(function (s) {
                return '<option value="' + s + '"' + (!mixed && s === reads[0] ? ' selected' : '') + '>' +
                    esc(s === 'xs' ? _sdT('Every screen size') : _sdT('From {var} up', s.toUpperCase())) + '</option>';
            }).join('') + '</select>';
        box.hidden = false;
        var sel = box.querySelector('select');
        sel.addEventListener('change', function () {
            if (!sel.value) return;
            _clsCommit(host, function (c) { return Z.stickyFromWrite(c, sel.value); });
        });
    }

    // The chooser in the section's head and, at a size, the rows' fields.
    // Not drawn where a custom style is off (a system widget, a shared
    // component): the rows stay for every size there. Hide on and Sticky
    // from are classes, drawn there too.
    function sizes(host) {
        var el = host.el;
        if (!el || !el.isConnected || !Z) return;
        var sect = el.closest('.sd-sect-body');
        if (!sect) return;
        _hoDraw(host, sect);
        _sfDraw(host, sect);
        if (host.off || !window.PgCustomStyle) return;
        var fields = _szFields(sect);
        if (!fields.length) return;
        var text = host.text();
        var has = {};
        host.nodes.forEach(function (n) {
            if (!n || !n.props) return;
            var cls = window.PgCustomStyle.classOf(n.props.cssClass);
            Z.held(text, cls, fields.map(function (fk) { return Z.PROPS[fk[1]]; })).forEach(function (s) { has[s] = true; });
        });
        var bar = _spbBar(el);
        if (bar) {
            bar._spbHost = host;
            bar.innerHTML = _spbChooserHtml(host.size, has);
        }
        fields.forEach(function (fk) { _szTake(host, fk[0], fk[1], text); });
    }

    return { draw: draw, corners: corners, sizes: sizes };
});
