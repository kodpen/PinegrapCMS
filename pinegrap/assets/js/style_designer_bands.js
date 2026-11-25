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
 * one undo step per drag. The geometry is window.PGCanvasBands and
 * window.PGCanvasGuides; the first-use line is kept as the tour's tips are
 * (window.PGDesignerTour).
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
    var G = window.PGCanvasGuides;
    var B = window.PGSpacingBox;
    var TIP = 'canvas-guides';
    var SIDES = ['t', 'e', 'b', 's'];
    var PROPS = { m: 'margin', p: 'padding' };
    var EDGE_CSS = { top: 'Top', right: 'Right', bottom: 'Bottom', left: 'Left' };

    // The layer, its bands and handles (made once, placed on every draw),
    // the host of the last draw and the layer's place it gave, the drag or
    // key change under way, and the listeners held while the bands are
    // shown.
    var S = { layer: null, bands: {}, handles: {}, label: null, host: null, g: null, live: null, area: null, doc: null, tip: null };

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
        return G.scale(root);
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
        var r = el.getBoundingClientRect();
        var b = { left: g.fx + r.left * g.k, top: g.fy + r.top * g.k, right: g.fx + r.right * g.k, bottom: g.fy + r.bottom * g.k };
        var cs = el.ownerDocument.defaultView.getComputedStyle(el);
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
            });
        });
    }

    // The value beside the pointer: the step in bold, its pixels, and the
    // sides Shift or Alt add.
    function _bdLabel(step, px, reach, x, y) {
        var said = { tb: _sdT('top and bottom'), se: _sdT('start and end'), all: _sdT('all four sides') }[reach] || '';
        S.label.innerHTML = '<b>' + esc(step) + '</b> ' + esc(_sdT('{var} px', Math.round(px))) + (said ? ' · ' + esc(said) : '');
        S.label.hidden = false;
        var g = S.g;
        if (!g) return;
        var at = C.labelAt(x, y, S.label.offsetWidth, S.label.offsetHeight, g.w, g.h);
        S.label.style.left = at.x + 'px';
        S.label.style.top = at.y + 'px';
    }

    // A change under way on handle `h`: the side, its value when it began,
    // and the element's inline values to give back.
    function _bdBegin(h, how) {
        var el = S.host.el;
        var kind = h.getAttribute('data-k'), side = h.getAttribute('data-s');
        var cs = el.ownerDocument.defaultView.getComputedStyle(el);
        var from = G.nearestStep(_bdPx(cs[PROPS[kind] + EDGE_CSS[h.getAttribute('data-e')]]), _bdScale(el));
        var keep = {};
        ['top', 'right', 'bottom', 'left'].forEach(function (e) {
            var p = PROPS[kind] + '-' + e;
            keep[p] = [el.style.getPropertyValue(p), el.style.getPropertyPriority(p)];
        });
        S.live = { how: how, h: h, el: el, id: S.host.id, kind: kind, side: side, edge: h.getAttribute('data-e'), from: from, step: from, reach: 'one', keep: keep, scale: _bdScale(el), rtl: _bdRtl(el) };
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
        S.live = null;
        _bdRestore(live);
        live.h.classList.remove('is-on');
        if (S.layer) S.layer.classList.remove('is-live');
        if (S.label) S.label.hidden = true;
        window.removeEventListener('keydown', _bdEsc, true);
        if (!write || live.step.step === live.from.step && live.reach === 'one' || !S.host || S.host.id !== live.id) {
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
                var step = G.nearestStep(live.from.px + grow, live.scale);
                _bdPreview(live, step, C.reach(live.side, last.shiftKey, last.altKey));
                _bdLabel(step.step, step.px, live.reach, last.clientX - lr.left, last.clientY - lr.top);
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
        var next = C.keyStep(live.step.step, dir, live.scale.length);
        _bdPreview(live, { step: next, px: live.scale[parseInt(next, 10)] }, C.reach(live.side, e.shiftKey, e.altKey));
        _bdLabel(next, live.scale[parseInt(next, 10)], live.reach, parseFloat(h.style.left) || 0, parseFloat(h.style.top) || 0);
    }

    function _bdBlur(e) {
        if (S.live && S.live.how === 'key' && S.live.h === e.currentTarget) _bdEnd(true);
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
    function _bdTip(host) {
        var T = window.PGDesignerTour;
        if (S.tip || !T || !S.g) return;
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
        if (S.live && S.live.el !== host.el) _bdEnd(false);
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
