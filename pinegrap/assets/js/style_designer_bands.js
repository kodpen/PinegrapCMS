/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the spacing bands part: the selected element's outer
 * spacing (margin) drawn as a hatched band outside its frame and its inner
 * spacing (padding) as a plain band inside, over the canvas in a layer of
 * the editor's own. A band's edge is a handle: dragging it steps that side
 * through the spacing scale with the value beside the pointer; Shift takes
 * the opposite side with it, Alt all four, as the Spacing box does. A
 * handle takes the focus with Tab: the arrow keys step it, Enter or leaving
 * it writes, Escape puts the value back. What a change writes is the
 * Spacing box's (window.PGSpacingBox) for the screen size the canvas shows,
 * one undo step per drag. A selected column of a row has one more handle
 * on its end edge: dragging it steps the column's width over the row's
 * twelve columns, drawn over the row while it is held, and writes the
 * Column options' width for that screen size. While a margin handle moves,
 * a sibling gap as wide as the one being changed is marked with the same
 * number and the handle holds there a moment (equal gaps). The geometry is
 * window.PGCanvasBands; the first-use line is kept as the tour's tips are
 * (window.PGDesignerTour, from the part 'tour').
 *
 * Nothing of it enters the page: while a side is dragged the element on the
 * canvas carries the value inline, taken off again before the change is
 * written. Not needed to open a page: the core runs this file the first
 * time a selection can show the bands (_sdBandsDraw(), pg_designer_parts())
 * and hands it a host (_sdBandsHost()). While the bands are hidden it holds
 * no listener.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('bands', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var C = window.PGCanvasBands;
    var B = window.PGSpacingBox;
    var TIP = 'canvas-guides';
    var SIDES = ['t', 'e', 'b', 's'];
    var PROPS = { m: 'margin', p: 'padding' };
    var EDGE_CSS = { top: 'Top', right: 'Right', bottom: 'Bottom', left: 'Left' };

    // The layer, its bands and handles (made once, placed on every draw),
    // the host of the last draw and the layer's place it gave, the drag or
    // key change under way, and the listeners held while the bands are
    // shown.
    var S = { layer: null, bands: {}, handles: {}, col: null, grid: null, gaps: null, label: null, host: null, g: null, live: null, area: null, doc: null, tip: null, tipAsked: false };
    var COLS = 12;
    // How far past the next step (screen pixels) the pointer goes before a
    // step with an equal gap lets go.
    var HOLD = 8;

    function _bdSideName(kind, side) {
        var names = kind === 'm'
            ? { t: _sdT('Margin Top'), e: _sdT('Margin End'), b: _sdT('Margin Bottom'), s: _sdT('Margin Start') }
            : { t: _sdT('Padding Top'), e: _sdT('Padding End'), b: _sdT('Padding Bottom'), s: _sdT('Padding Start') };
        return names[side];
    }

    function _bdMake() {
        var layer = document.createElement('div');
        layer.className = 'sd-cgb-layer';
        layer.setAttribute('aria-label', _sdT('Spacing'));
        var tip = _sdT('Drag to change the spacing. Shift takes the opposite side with it, Alt all four. Arrow keys step it, Escape puts it back.');
        ['m', 'p'].forEach(function (kind) {
            SIDES.forEach(function (side) {
                var band = document.createElement('div');
                band.className = 'sd-cgb-band sd-cgb-' + kind;
                band.setAttribute('aria-hidden', 'true');
                layer.appendChild(band);
                S.bands[kind + side] = band;
            });
        });
        ['m', 'p'].forEach(function (kind) {
            SIDES.forEach(function (side) {
                var h = document.createElement('button');
                h.type = 'button';
                h.className = 'sd-cgb-h';
                h.setAttribute('data-k', kind);
                h.setAttribute('data-s', side);
                h.setAttribute('aria-label', _bdSideName(kind, side));
                h.title = _bdSideName(kind, side) + ' — ' + tip;
                h.addEventListener('pointerdown', _bdDown);
                h.addEventListener('keydown', _bdKey);
                h.addEventListener('blur', _bdBlur);
                layer.appendChild(h);
                S.handles[kind + side] = h;
            });
        });
        var col = document.createElement('button');
        col.type = 'button';
        col.className = 'sd-cgb-h is-v sd-cgb-col';
        col.setAttribute('data-k', 'c');
        col.setAttribute('aria-label', _sdT('Column width'));
        col.title = _sdT('Column width') + ' — ' + _sdT('Drag to change the width over the twelve columns. Arrow keys step it, Escape puts it back.');
        col.hidden = true;
        col.addEventListener('pointerdown', _bdColDown);
        col.addEventListener('keydown', _bdColKey);
        col.addEventListener('blur', _bdBlur);
        S.col = col;
        S.grid = document.createElement('div');
        S.grid.className = 'sd-cgb-grid';
        S.grid.setAttribute('aria-hidden', 'true');
        S.grid.hidden = true;
        for (var i = 0; i < COLS; i++) {
            var gc = document.createElement('div');
            gc.className = 'sd-cgb-gc';
            S.grid.appendChild(gc);
        }
        layer.insertBefore(S.grid, layer.firstChild);
        S.gaps = [0, 1].map(function () {
            var line = document.createElement('div');
            line.className = 'sd-cgb-gl';
            line.setAttribute('aria-hidden', 'true');
            line.hidden = true;
            var n = document.createElement('div');
            n.className = 'sd-cgb-gn';
            n.setAttribute('aria-hidden', 'true');
            n.hidden = true;
            layer.appendChild(line);
            layer.appendChild(n);
            return { line: line, num: n };
        });
        layer.appendChild(col);
        S.label = document.createElement('div');
        S.label.className = 'sd-cgb-val';
        S.label.setAttribute('role', 'status');
        S.label.hidden = true;
        layer.appendChild(S.label);
        S.layer = layer;
    }

    function _bdPx(v) {
        var n = parseFloat(v);
        return isFinite(n) ? n : 0;
    }

    function _bdRtl(el) {
        try { return el.ownerDocument.defaultView.getComputedStyle(el).direction === 'rtl'; } catch (e) { return false; }
    }

    // The spacing scale of the page on the canvas, from its root font size.
    function _bdScale(el) {
        var root = 16;
        try { root = _bdPx(el.ownerDocument.defaultView.getComputedStyle(el.ownerDocument.documentElement).fontSize) || 16; } catch (e) { root = 16; }
        return C.scale(root);
    }

    function _bdBox(el, b) {
        el.style.left = b.left + 'px';
        el.style.top = b.top + 'px';
        el.style.width = Math.max(0, b.right - b.left) + 'px';
        el.style.height = Math.max(0, b.bottom - b.top) + 'px';
        el.hidden = !(b.right - b.left > 0 && b.bottom - b.top > 0);
    }

    // Places the layer over the canvas in view and the bands and handles
    // around the element as the browser lays it out now.
    function _bdPlace() {
        var host = S.host, g = host.geo(), el = host.el;
        S.g = g;
        if (!g || g.w <= 0 || g.h <= 0 || !el.isConnected) { S.layer.hidden = true; return; }
        if (S.layer.parentNode !== g.wrapper) g.wrapper.appendChild(S.layer);
        S.layer.hidden = false;
        S.layer.style.left = g.left + 'px';
        S.layer.style.top = g.top + 'px';
        S.layer.style.width = g.w + 'px';
        S.layer.style.height = g.h + 'px';
        var b = _bdRect(el);
        var cs = el.ownerDocument.defaultView.getComputedStyle(el);
        var colOn = !!(host.col && host.col.el.isConnected);
        var read = function (p) {
            return { top: _bdPx(cs[p + 'Top']), right: _bdPx(cs[p + 'Right']), bottom: _bdPx(cs[p + 'Bottom']), left: _bdPx(cs[p + 'Left']) };
        };
        var L = C.layout(b, read('margin'), read('padding'), g.k, _bdRtl(el));
        [['m', L.outer], ['p', L.inner]].forEach(function (pair) {
            pair[1].forEach(function (it) {
                _bdBox(S.bands[pair[0] + it.side], it.band);
                var h = S.handles[pair[0] + it.side];
                h.style.left = it.handle.x + 'px';
                h.style.top = it.handle.y + 'px';
                h.classList.toggle('is-v', it.handle.axis === 'y');
                h.classList.toggle('is-faint', it.handle.faint);
                h.setAttribute('data-e', it.edge);
                // A column's side edges are its width's: the column handle.
                h.hidden = colOn && pair[0] === 'm' && it.handle.axis === 'y';
            });
        });
        _bdColPlace(b, colOn);
    }

    // A canvas element's box in the layer's pixels.
    function _bdRect(el) {
        var g = S.g, r = el.getBoundingClientRect();
        return { left: g.fx + r.left * g.k, top: g.fy + r.top * g.k, right: g.fx + r.right * g.k, bottom: g.fy + r.bottom * g.k };
    }

    // The column handle on the end edge of the column's frame `b`, and the
    // row's twelve columns while it is held.
    function _bdColPlace(b, on) {
        var live = S.live && S.live.kind === 'c' ? S.live : null;
        S.col.hidden = !on;
        if (on) {
            var rtl = _bdRtl(S.host.col.el);
            S.col.style.left = (rtl ? b.left : b.right) + 'px';
            S.col.style.top = ((b.top + b.bottom) / 2) + 'px';
        }
        S.grid.hidden = !(on && live);
        if (S.grid.hidden) return;
        var row = _bdRect(S.host.col.row);
        C.colGrid(row, COLS, live.gutter * S.g.k).forEach(function (box, i) {
            _bdBox(S.grid.children[i], box);
        });
    }

    // The value beside the pointer: the step in bold, its pixels, and the
    // sides Shift or Alt add.
    function _bdLabel(step, px, reach, x, y, match) {
        var said = { tb: _sdT('top and bottom'), se: _sdT('start and end'), all: _sdT('all four sides') }[reach] || '';
        var same = match ? { above: _sdT('the gap above'), below: _sdT('the gap below'), left: _sdT('the gap on the left'), right: _sdT('the gap on the right') }[match.where] : '';
        var name = step === 'auto' ? _sdT('auto') : step;
        S.label.innerHTML = (name ? '<b>' + esc(name) + '</b> ' : '') + esc(_sdT('{var} px', Math.round(px))) + (said ? ' · ' + esc(said) : '') +
            (same ? ' <i>= ' + esc(same) + '</i>' : '');
        _bdLabelAt(x, y);
    }

    function _bdLabelAt(x, y) {
        S.label.hidden = false;
        var g = S.g;
        if (!g) return;
        var at = C.labelAt(x, y, S.label.offsetWidth, S.label.offsetHeight, g.w, g.h);
        S.label.style.left = at.x + 'px';
        S.label.style.top = at.y + 'px';
    }

    // Whether the element's classes set `side`'s margin to auto for the
    // screen size the canvas shows (mx-auto, ms-lg-auto…): the browser
    // reports its width in pixels, which is no step of the scale.
    function _bdAuto(kind, side) {
        if (kind !== 'm' || !S.host.classes || S.host.classes.length !== 1) return false;
        var r = B.read(S.host.classes[0], 'm', C.sizeFor(S.host.width));
        return !!(r[side] && r[side].value === 'auto');
    }

    // A change under way on handle `h`: the side, its value when it began
    // (as the band shows it: an automatic margin or a value off the scale
    // stays itself until a scale step is nearer), and the element's inline
    // values to give back.
    function _bdBegin(h, how) {
        var el = S.host.el;
        var kind = h.getAttribute('data-k'), side = h.getAttribute('data-s');
        var cs = el.ownerDocument.defaultView.getComputedStyle(el);
        var from = C.startStep(_bdPx(cs[PROPS[kind] + EDGE_CSS[h.getAttribute('data-e')]]), _bdScale(el), _bdAuto(kind, side));
        var keep = {};
        ['top', 'right', 'bottom', 'left'].forEach(function (e) {
            var p = PROPS[kind] + '-' + e;
            keep[p] = [el.style.getPropertyValue(p), el.style.getPropertyPriority(p)];
        });
        S.live = { how: how, h: h, el: el, at: el, id: S.host.id, held: null, sibs: null, kind: kind, side: side, edge: h.getAttribute('data-e'), from: from, step: from, reach: 'one', keep: keep, scale: _bdScale(el), rtl: _bdRtl(el) };
        S.layer.classList.add('is-live');
        h.classList.add('is-on');
        return S.live;
    }

    // Shows `step` on the canvas element for the sides `reach` names.
    function _bdPreview(live, step, reach) {
        live.step = step;
        live.reach = reach;
        _bdRestore(live);
        var to = B.sides(live.side, reach === 'tb' || reach === 'se', reach === 'all');
        to.sides.forEach(function (s) {
            live.el.style.setProperty(PROPS[live.kind] + '-' + C.edge(s, live.rtl), step.px + 'px', 'important');
        });
        _bdPlace();
    }

    function _bdRestore(live) {
        Object.keys(live.keep).forEach(function (p) {
            var v = live.keep[p];
            if (v[0]) live.el.style.setProperty(p, v[0], v[1]); else live.el.style.removeProperty(p);
        });
    }

    // Ends the change: the inline values given back, then, when `write`,
    // the step written as the Spacing box writes it (one undo step).
    function _bdEnd(write) {
        var live = S.live;
        if (!live) return;
        if (live.kind === 'c') { _bdColEnd(write); return; }
        S.live = null;
        _bdRestore(live);
        _bdGapsShow(null);
        live.h.classList.remove('is-on');
        if (S.layer) S.layer.classList.remove('is-live');
        if (S.label) S.label.hidden = true;
        window.removeEventListener('keydown', _bdEsc, true);
        // A start off the scale that was not moved to a step writes nothing.
        if (!write || live.step.step === '' || live.step.step === live.from.step && live.reach === 'one' || !S.host || S.host.id !== live.id) {
            if (S.host && S.host.el.isConnected) _bdPlace();
            return;
        }
        var host = S.host, size = C.sizeFor(host.width);
        var to = B.sides(live.side, live.reach === 'tb' || live.reach === 'se', live.reach === 'all');
        host.commit(function (list) { return B.write(list, live.kind, size, to.sides, live.step.step, to.all); });
    }

    function _bdEsc(e) {
        if (e.key !== 'Escape' || !S.live) return;
        e.preventDefault();
        e.stopPropagation();
        _bdEnd(false);
    }

    function _bdDown(e) {
        if (e.button !== 0 || S.live || !S.host) return;
        e.preventDefault();
        var h = e.currentTarget;
        var live = _bdBegin(h, 'drag');
        var k = S.g ? S.g.k : 1;
        var lr = S.layer.getBoundingClientRect();
        live.x = e.clientX;
        live.y = e.clientY;
        try { h.setPointerCapture(e.pointerId); } catch (err) { /* the drag still follows the handle */ }
        var raf = 0, last = e;
        var move = function (ev) {
            last = ev;
            if (raf) return;
            raf = requestAnimationFrame(function () {
                raf = 0;
                if (S.live !== live) return;
                var grow = C.growth(live.kind, live.edge, last.clientX - live.x, last.clientY - live.y, k);
                var raw = live.from.px + grow;
                var step = C.hold(raw, live.held, C.nearestStep(raw, live.scale, live.from), HOLD / k);
                _bdPreview(live, step, C.reach(live.side, last.shiftKey, last.altKey));
                var match = _bdGaps(live);
                _bdLabel(step.step, step.px, live.reach, last.clientX - lr.left, last.clientY - lr.top, match);
            });
        };
        var up = function (ev) {
            h.removeEventListener('pointermove', move);
            h.removeEventListener('pointerup', up);
            h.removeEventListener('pointercancel', up);
            if (raf) cancelAnimationFrame(raf);
            if (S.live === live) _bdEnd(ev.type === 'pointerup');
        };
        h.addEventListener('pointermove', move);
        h.addEventListener('pointerup', up);
        h.addEventListener('pointercancel', up);
        window.addEventListener('keydown', _bdEsc, true);
        _bdLabel(live.from.step, live.from.px, 'one', e.clientX - lr.left, e.clientY - lr.top);
    }

    // The arrows that move a handle along its own axis step its side;
    // Enter writes, Escape puts the value back.
    function _bdKey(e) {
        var h = e.currentTarget;
        var live = S.live;
        if (e.key === 'Escape') {
            if (live && live.h === h) { e.preventDefault(); e.stopPropagation(); _bdEnd(false); }
            return;
        }
        if (e.key === 'Enter') {
            if (live && live.h === h) { e.preventDefault(); _bdEnd(true); }
            return;
        }
        var d = { ArrowUp: [0, -1], ArrowDown: [0, 1], ArrowLeft: [-1, 0], ArrowRight: [1, 0] }[e.key];
        if (!d || !S.host) return;
        var dir = C.growth(h.getAttribute('data-k'), h.getAttribute('data-e'), d[0], d[1], 1);
        if (!dir) return;
        e.preventDefault();
        e.stopPropagation();
        if (live && live.h !== h) _bdEnd(true);
        live = S.live || _bdBegin(h, 'key');
        var next = C.keyStepFrom(live.step, dir, live.scale);
        _bdPreview(live, next, C.reach(live.side, e.shiftKey, e.altKey));
        _bdLabel(next.step, next.px, live.reach, parseFloat(h.style.left) || 0, parseFloat(h.style.top) || 0, _bdGaps(live));
    }

    function _bdBlur(e) {
        if (S.live && S.live.how === 'key' && S.live.h === e.currentTarget) _bdEnd(true);
    }

    // Equal gaps while a margin handle moves: the gap being changed and a
    // sibling gap as wide, both marked with their number; the step shown is
    // held there a moment (C.hold()). Only the selection's siblings count,
    // on the axis being dragged; they are asked for once per change.
    function _bdGaps(live) {
        var m = null;
        if (live.kind === 'm' && S.host && S.host.siblings && S.g) {
            if (!live.sibs) live.sibs = S.host.siblings();
            var others = live.sibs.filter(function (el) { return el.isConnected; }).map(_bdRect);
            m = C.equalGap(_bdRect(live.el), others, live.edge, S.g.k);
        }
        live.held = m ? live.step : null;
        _bdGapsShow(m);
        return m;
    }

    function _bdGapsShow(m) {
        if (!S.gaps) return;
        [m && m.gap, m && m.match].forEach(function (line, i) {
            var mark = S.gaps[i];
            mark.line.hidden = mark.num.hidden = !line;
            if (!line) return;
            var y = line.axis === 'y', mid = (line.from + line.to) / 2;
            // Beside the boxes, but inside the layer with room for the number.
            var at = S.g ? Math.max(0, Math.min(line.at, (y ? S.g.w : S.g.h) - 44)) : line.at;
            line = { axis: line.axis, from: line.from, to: line.to, at: at, value: line.value };
            mark.line.classList.toggle('is-v', y);
            mark.line.style.left = (y ? line.at : line.from) + 'px';
            mark.line.style.top = (y ? line.from : line.at) + 'px';
            mark.line.style.width = (y ? 1 : line.to - line.from) + 'px';
            mark.line.style.height = (y ? line.to - line.from : 1) + 'px';
            mark.num.textContent = String(line.value);
            mark.num.style.left = (y ? line.at : mid) + 'px';
            mark.num.style.top = (y ? mid : line.at) + 'px';
        });
    }

    // ── The column handle: a column's width over the row's twelve columns.
    function _bdColBegin(how) {
        var col = S.host.col, el = col.el;
        var cs = el.ownerDocument.defaultView.getComputedStyle(el);
        var from = C.colSpanOf(el.getBoundingClientRect().width, col.row.getBoundingClientRect().width, COLS);
        var keep = {};
        ['flex', 'width', 'max-width'].forEach(function (p) {
            keep[p] = [el.style.getPropertyValue(p), el.style.getPropertyPriority(p)];
        });
        S.live = {
            how: how, h: S.col, el: el, at: S.host.el, id: S.host.id, kind: 'c', from: from, span: from, keep: keep,
            gutter: _bdPx(cs.paddingLeft) + _bdPx(cs.paddingRight), rtl: _bdRtl(el), size: C.sizeFor(S.host.width),
            box: S.g ? _bdRect(S.host.el) : null
        };
        S.layer.classList.add('is-live');
        S.col.classList.add('is-on');
        return S.live;
    }

    // Shows `span` columns on the canvas: the width Bootstrap's col-<n>
    // gives, inline and !important as the utilities are, taken off again.
    function _bdColPreview(live, span) {
        live.span = span;
        live.el.style.setProperty('flex', '0 0 auto', 'important');
        live.el.style.setProperty('width', (span / COLS * 100) + '%', 'important');
        live.el.style.setProperty('max-width', '100%', 'important');
        _bdPlace();
    }

    function _bdColLabel(span, x, y) {
        S.label.innerHTML = '<b>' + esc(_sdT('{var} of 12', span)) + '</b> ' + esc(String(S.live.size).toUpperCase());
        _bdLabelAt(x, y);
    }

    function _bdColEnd(write) {
        var live = S.live;
        S.live = null;
        _bdRestore(live);
        S.col.classList.remove('is-on');
        S.grid.hidden = true;
        if (S.layer) S.layer.classList.remove('is-live');
        if (S.label) S.label.hidden = true;
        window.removeEventListener('keydown', _bdEsc, true);
        if (!write || live.span === live.from || !S.host || S.host.id !== live.id || !S.host.col) {
            if (S.host && S.host.el.isConnected) _bdPlace();
            return;
        }
        S.host.col.commit(live.size, live.span);
    }

    function _bdColDown(e) {
        if (e.button !== 0 || S.live || !S.host || !S.host.col) return;
        e.preventDefault();
        var h = e.currentTarget;
        var live = _bdColBegin('drag');
        if (!live.box) { _bdColEnd(false); return; }
        var lr = S.layer.getBoundingClientRect();
        try { h.setPointerCapture(e.pointerId); } catch (err) { /* the drag still follows the handle */ }
        var raf = 0, last = e;
        var move = function (ev) {
            last = ev;
            if (raf) return;
            raf = requestAnimationFrame(function () {
                raf = 0;
                if (S.live !== live || !S.g) return;
                var x = last.clientX - lr.left;
                var span = C.colSpan(live.box, x, _bdRect(S.host.col.row), COLS, live.gutter * S.g.k, live.rtl);
                if (span && span !== live.span) _bdColPreview(live, span);
                _bdColLabel(live.span, x, last.clientY - lr.top);
            });
        };
        var up = function (ev) {
            h.removeEventListener('pointermove', move);
            h.removeEventListener('pointerup', up);
            h.removeEventListener('pointercancel', up);
            if (raf) cancelAnimationFrame(raf);
            if (S.live === live) _bdEnd(ev.type === 'pointerup');
        };
        h.addEventListener('pointermove', move);
        h.addEventListener('pointerup', up);
        h.addEventListener('pointercancel', up);
        window.addEventListener('keydown', _bdEsc, true);
        _bdPlace();
        _bdColLabel(live.from, e.clientX - lr.left, e.clientY - lr.top);
    }

    // Left and right step the width a column at a time (mirrored right to
    // left); Enter or leaving the handle writes, Escape puts it back.
    function _bdColKey(e) {
        var live = S.live;
        if (e.key === 'Escape' || e.key === 'Enter') {
            if (live && live.kind === 'c') { e.preventDefault(); e.stopPropagation(); _bdEnd(e.key === 'Enter'); }
            return;
        }
        var d = { ArrowLeft: -1, ArrowRight: 1 }[e.key];
        if (!d || !S.host || !S.host.col) return;
        e.preventDefault();
        e.stopPropagation();
        if (live && live.kind !== 'c') _bdEnd(true);
        live = S.live || _bdColBegin('key');
        var span = Math.max(1, Math.min(COLS, live.span + (live.rtl ? -d : d)));
        _bdColPreview(live, span);
        _bdColLabel(span, parseFloat(S.col.style.left) || 0, parseFloat(S.col.style.top) || 0);
    }

    // An element dragged to another place takes the bands away; the draw
    // after the drop brings them back.
    function _bdDragStart() {
        hide();
    }

    function _bdAreaScroll() {
        if (S.host && S.host.el.isConnected) _bdPlace();
    }

    function _bdListen(host) {
        var area = S.g ? S.g.area : null;
        if (area !== S.area) {
            if (S.area) S.area.removeEventListener('scroll', _bdAreaScroll);
            S.area = area;
            if (area) area.addEventListener('scroll', _bdAreaScroll, { passive: true });
        }
        var doc = host.el.ownerDocument;
        if (doc !== S.doc) {
            if (S.doc) S.doc.removeEventListener('dragstart', _bdDragStart, true);
            S.doc = doc;
            doc.addEventListener('dragstart', _bdDragStart, true);
        }
    }

    function _bdUnlisten() {
        if (S.area) S.area.removeEventListener('scroll', _bdAreaScroll);
        if (S.doc) S.doc.removeEventListener('dragstart', _bdDragStart, true);
        S.area = null;
        S.doc = null;
    }

    // The first-use line under the canvas bar, once per person.
    // The tips' state is kept by the tour's model, which comes with the part
    // 'tour'; its first use here runs that part (nothing of it is shown).
    function _bdTip(host) {
        if (S.tip || !S.g) return;
        if (!window.PGDesignerTour) {
            if (S.tipAsked) return;
            S.tipAsked = true;
            K._sdPartCall('tour', 'model', []).then(function () {
                if (S.host === host) _bdTip(host);
            }).catch(function () { /* the line waits for the next visit */ });
            return;
        }
        var T = window.PGDesignerTour;
        var key = T.key(host.userId), state = null;
        if (!key) return;
        try { state = T.read(window.localStorage.getItem(key)); } catch (e) { return; }
        if (!T.tipDue(state, TIP)) return;
        var bar = S.g.wrapper.querySelector('#sd-bp-bar');
        if (!bar) return;
        var line = document.createElement('div');
        line.className = 'sd-cgb-first';
        line.innerHTML = '<span class="bi bi-info-circle" aria-hidden="true"></span>' +
            '<span>' + esc(_sdT('Drag the edge of a tinted band to change the spacing. Hold Alt and point at another element to measure.')) + '</span>' +
            '<button type="button" class="sd-cgb-got">' + esc(_sdT('Got it')) + '</button>';
        line.querySelector('button').addEventListener('click', function () {
            try { window.localStorage.setItem(key, T.write(T.tipSeen(T.read(window.localStorage.getItem(key)), TIP))); } catch (e) { /* shown again next time */ }
            line.remove();
        });
        bar.insertAdjacentElement('afterend', line);
        S.tip = line;
    }

    // Draws the bands of the host's element; a change under way on another
    // element is written first.
    function draw(host) {
        if (!host || !host.el || host.locked) { hide(); return; }
        if (S.live && S.live.id !== host.id) _bdEnd(S.live.how === 'key');
        if (!S.layer) _bdMake();
        if (S.live && S.live.at !== host.el) _bdEnd(false);
        S.host = host;
        _bdPlace();
        if (!S.g) { hide(); return; }
        _bdListen(host);
        _bdTip(host);
    }

    function hide() {
        if (S.live) _bdEnd(false);
        S.host = null;
        _bdUnlisten();
        if (S.layer) S.layer.hidden = true;
    }

    return { draw: draw, hide: hide };
});
