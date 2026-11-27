/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Focus control part: "Focus" in an Image's
 * Options (Content › Image, under the fit) and in a background picture's
 * (Style › Color and background). The whole picture across the control
 * with one point on what must stay in view, and under it the cut on a
 * phone, a tablet and a desktop, large enough to judge (one frame when the
 * three would be the same picture). A press on the picture puts the point there, a drag moves it;
 * the canvas and the three frames follow, and the whole move is one undo
 * step. With the point focused the arrows move it by 1 % and Home puts it
 * in the middle. "Reset" removes the focus; a focus set while the canvas
 * bar shows a smaller screen is that screen's own, and "Same on every
 * screen" keeps the widest one. What a value is and how it is written are
 * the core's twins (_sdFocus*()); what the control shows is
 * window.PGImageFocus's (the first source of this part).
 *
 * Not needed to open a page: the core runs this file the first time a
 * Focus control is shown (_sdFocusBind(), pg_designer_parts()) and hands it
 * a host: the picture, the values, the screen, live() and commit().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('focus', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    // The control drawn again after a commit gives the focus back to its
    // point (the panel is drawn anew by every change).
    var refocus = false;
    var ICONS = { phone: 'bi-phone', tablet: 'bi-tablet', desktop: 'bi-laptop', every: 'bi-aspect-ratio' };
    // The control drawn last (its element and its show()), which the mode
    // moves with the picture; the mode on now, ended by a second start.
    var shown = null;
    var active = null;
    // The canvas element watched for its width (the canvas bar's size).
    var watched = null;

    function names() {
        return { phone: _sdT('Phone'), tablet: _sdT('Tablet'), desktop: _sdT('Desktop'), every: _sdT('Every screen') };
    }

    function valueText(xy) {
        return _sdT('{var:1}% · {var:2}%', [Math.round(xy[0]), Math.round(xy[1])]);
    }

    function cssUrl(src) {
        return 'url("' + String(src).replace(/["\\\n\r]/g, function (c) { return '\\' + c.charCodeAt(0).toString(16) + ' '; }) + '")';
    }

    // One frame under the picture: the cut at its screen's point, then its
    // icon and name. 'every' stands for the three when they are one picture.
    function frameHtml(f, v, src, nm) {
        var s = f.s, fr = v.frames[s === 'every' ? 'desktop' : s];
        return '<div class="sd-fc-fr' + (s === v.screen ? ' is-cur' : '') + (fr.own ? ' is-own' : '') + '" data-fc-frame="' + s + '">' +
            '<div class="sd-fc-fr-pic" style="width:' + f.w + 'px;height:' + f.h + 'px;background-image:' + esc(cssUrl(src)) +
                ';background-position:' + fr.xy[0] + '% ' + fr.xy[1] + '%"></div>' +
            '<span class="sd-fc-fr-name"><span class="bi ' + ICONS[s] + '" aria-hidden="true"></span>' + esc(nm[s]) + '</span>' +
        '</div>';
    }

    function html(host, M, v, width) {
        var nm = names();
        var lay = M.sizes(width, M.shapes(host.ratio, v.screen, host.measured), v.frames);
        var head = '<div class="sd-fc-head">' +
                '<span class="sd-fc-title">' + esc(_sdT('Focus')) + '</span>' +
                '<span class="sd-fc-val" aria-live="polite">' + (v.set ? esc(valueText(v.xy)) : '') + '</span>' +
                (v.own ? '<span class="sd-fc-chip">' + esc(nm[v.screen]) + '</span>'
                       : (v.any ? '<button type="button" class="sd-fc-reset" data-fc-reset>' + esc(_sdT('Reset')) + '</button>' : '')) +
            '</div>';
        var pic = '<div class="sd-fc-pic' + (v.set ? '' : ' is-none') + '" data-fc-pic>' +
                '<img src="' + esc(host.src) + '" alt="" draggable="false">' +
                '<span class="sd-fc-pt" data-fc-pt tabindex="0" role="slider" aria-label="' + esc(_sdT('Focus')) + '"' +
                    ' aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.round(v.xy[0]) + '"' +
                    ' aria-valuetext="' + esc(v.set ? valueText(v.xy) : _sdT('Not set')) + '"' +
                    (host.locked ? ' aria-disabled="true"' : '') +
                    ' style="left:' + v.xy[0] + '%;top:' + v.xy[1] + '%"></span>' +
            '</div>';
        var frames = '<p class="sd-fc-cap">' + esc(lay.one ? _sdT('On every screen') : _sdT('On each screen')) + '</p>' +
            '<div class="sd-fc-frames">' + lay.rows.map(function (frs) {
                return '<div class="sd-fc-frames-row">' + frs.map(function (f) { return frameHtml(f, v, host.src, nm); }).join('') + '</div>';
            }).join('') + '</div>';
        var foot = '';
        if (v.own) {
            var keep = v.wider ? valueText(v.wider) : _sdT('the middle');
            foot += '<div class="sd-fc-foot">' + esc(v.screen === 'phone'
                    ? _sdT('This focus is the phone\'s own. Wider screens keep {var}.', keep)
                    : _sdT('This focus is the tablet\'s own. Wider screens keep {var}.', keep)) + '</div>' +
                '<button type="button" class="btn btn-sm btn-outline theme-secondary sd-fc-same" data-fc-same>' + esc(_sdT('Same on every screen')) + '</button>';
        } else if (v.typed) {
            foot += '<div class="sd-fc-foot">' + esc(_sdT('Typed before as {var}. It is kept as typed until the point is moved.', '\u0001'))
                .replace('\u0001', '<strong>' + esc(v.typed) + '</strong>') + '</div>';
        } else if (!v.set) {
            foot += '<div class="sd-fc-foot">' + esc(_sdT('Drag the point onto what must stay in view.')) + '</div>';
        }
        if (host.bound) {
            foot += '<div class="sd-fc-info"><span class="bi bi-info-circle" aria-hidden="true"></span>' +
                esc(_sdT('This card is drawn once for every record. The focus set here is the same for each record\'s picture.')) + '</div>';
        }
        return head + '<div class="sd-fc-body">' + pic + frames + '</div>' + foot;
    }

    // A move writes the screen the canvas bar shows (host.screen, read when
    // the core made the host). Choosing a width on the bar draws no panel,
    // so a change of the canvas's width draws the shown controls again
    // through the core, with the screen and the shape of that moment; not
    // while a drag or the keys of a control or the mode are writing.
    function watch() {
        var wrap = document.getElementById('sd-iframe-wrap');
        if (!wrap || watched === wrap || typeof ResizeObserver !== 'function') return;
        watched = wrap;
        var width = wrap.offsetWidth, timer = 0;
        new ResizeObserver(function () {
            if (wrap.offsetWidth === width) return;
            width = wrap.offsetWidth;
            clearTimeout(timer);
            timer = setTimeout(function () {
                if (!shown || !shown.el.isConnected || shown.busy() || active || !K.selectedNode) return;
                K._sdFocusBind(K.selectedNode);
            }, 150);
        }).observe(wrap);
    }

    function draw(host) {
        var el = host.el;
        if (!el || !el.isConnected) return;
        watch();
        var M = window.PGImageFocus(host.F);
        var values = host.values;
        var v = M.view(values, host.screen);
        var width = el.clientWidth;
        el.innerHTML = html(host, M, v, width);
        var pic = el.querySelector('[data-fc-pic]');
        // The whole picture across the control, at most 200 px tall and
        // centred, once its size is known; the point sits on it.
        var img = pic.querySelector('img');
        var fit = function () {
            var size = M.whole(width || el.clientWidth, img.naturalWidth, img.naturalHeight);
            if (size) { pic.style.width = size.w + 'px'; pic.style.height = size.h + 'px'; }
        };
        if (img.complete) fit(); else img.addEventListener('load', fit, { once: true });
        var pt = el.querySelector('[data-fc-pt]');
        var val = el.querySelector('.sd-fc-val');
        var pending = null, raf = 0, keyTimer = 0, keyed = null;
        shown = { id: host.id, kind: host.kind, el: el, show: function (next) { show(next); }, redraw: function () { draw(host); },
            busy: function () { return !!keyed || pic.classList.contains('is-drag'); } };

        // Shows `next` in the control and on the canvas without writing it.
        function show(next) {
            pending = next;
            if (raf) return;
            raf = window.requestAnimationFrame(function () {
                raf = 0;
                var w = M.view(pending, host.screen);
                pt.style.left = w.xy[0] + '%';
                pt.style.top = w.xy[1] + '%';
                pt.setAttribute('aria-valuenow', String(Math.round(w.xy[0])));
                pt.setAttribute('aria-valuetext', valueText(w.xy));
                if (val) val.textContent = valueText(w.xy);
                pic.classList.remove('is-none');
                ['phone', 'tablet', 'desktop', 'every'].forEach(function (s) {
                    var fr = el.querySelector('[data-fc-frame="' + s + '"] .sd-fc-fr-pic');
                    var xy = w.frames[s === 'every' ? 'desktop' : s].xy;
                    if (fr) fr.style.backgroundPosition = xy[0] + '% ' + xy[1] + '%';
                });
                host.live(pending);
            });
        }

        function commit(next) {
            if (raf) { window.cancelAnimationFrame(raf); raf = 0; }
            pending = null;
            host.commit(next);
        }

        if (refocus) {
            refocus = false;
            try { pt.focus({ preventScroll: true }); } catch (e) { pt.focus(); }
        }
        if (host.locked) return;

        pic.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) return;
            e.preventDefault();
            var rect = pic.getBoundingClientRect();
            var at = function (ev) { var xy = M.fromPointer(rect, ev.clientX, ev.clientY); return M.move(values, host.screen, xy[0], xy[1]); };
            var last = at(e);
            pic.classList.add('is-drag');
            try { pic.setPointerCapture(e.pointerId); } catch (err) {}
            show(last);
            var onMove = function (ev) { last = at(ev); show(last); };
            var onUp = function () {
                pic.removeEventListener('pointermove', onMove);
                pic.removeEventListener('pointerup', onUp);
                pic.removeEventListener('pointercancel', onUp);
                pic.classList.remove('is-drag');
                refocus = true;
                commit(last);
            };
            pic.addEventListener('pointermove', onMove);
            pic.addEventListener('pointerup', onUp);
            pic.addEventListener('pointercancel', onUp);
        });

        // Keys move the point at once; the value is written once the hand
        // rests, as one undo step.
        pt.addEventListener('keydown', function (e) {
            var from = M.view(keyed || values, host.screen).xy;
            var xy = M.key(from, e.key);
            if (!xy) return;
            e.preventDefault();
            e.stopPropagation();
            keyed = M.move(values, host.screen, xy[0], xy[1]);
            show(keyed);
            clearTimeout(keyTimer);
            keyTimer = setTimeout(function () {
                var next = keyed;
                keyed = null;
                refocus = document.activeElement === pt;
                commit(next);
            }, 300);
        });

        var reset = el.querySelector('[data-fc-reset]');
        if (reset) reset.addEventListener('click', function () { commit(M.reset()); });
        var same = el.querySelector('[data-fc-same]');
        if (same) same.addEventListener('click', function () { commit(M.same(values)); });
    }

    // ── "Set focus" on the canvas
    // The picture shows whole, dimmed outside its frame, and is dragged
    // under the frame; the arrows move it by 1 %, Enter or a press outside
    // keeps, Escape puts it back. Nothing else on the canvas can be pressed
    // while it is on; every listener goes with it. Drawn in the canvas
    // document over the element, in fixed position against its viewport.

    var Z = 2147483000;

    function css(el, text) { el.style.cssText = text; return el; }

    function lineButton(doc, label, main) {
        var b = doc.createElement('button');
        b.type = 'button';
        b.textContent = label;
        css(b, 'height:26px;padding:0 10px;border:0;border-radius:6px;font:inherit;font-size:12px;cursor:pointer;' +
            (main ? 'background:#2563eb;color:#fff;' : 'background:transparent;color:#c9d1d9;box-shadow:inset 0 0 0 1px #3d444d;'));
        return b;
    }

    function natural(host) {
        var pic = host.pic;
        if (host.kind === 'image' && pic && pic.complete && pic.naturalWidth > 0) {
            return Promise.resolve([pic.naturalWidth, pic.naturalHeight]);
        }
        return new Promise(function (resolve) {
            var img = new Image();
            img.onload = function () { resolve([img.naturalWidth, img.naturalHeight]); };
            img.onerror = function () { resolve([0, 0]); };
            img.src = host.src;
        });
    }

    function mode(host) {
        if (active) active.end(false);
        var M = window.PGImageFocus(host.F);
        var doc = host.doc, win = doc && doc.defaultView, pic = host.pic;
        if (!win || !pic || !pic.isConnected) return Promise.resolve();
        return natural(host).then(function (nat) {
            if (!pic.isConnected) return;
            open(host, M, doc, win, pic, nat);
        });
    }

    function open(host, M, doc, win, pic, nat) {
        var values = host.values;
        var screen = host.screen;
        var drawnAs = host.drawnAs();
        var from = M.start(values, screen, drawnAs.position);
        var xy = from.slice();
        var moved = false;
        var size = null, box = null;
        // The element's own inline position, put back by Escape.
        var prop = host.kind === 'background' ? 'background-position' : 'object-position';
        var before = [pic.style.getPropertyValue(prop), pic.style.getPropertyPriority(prop)];

        var layer = css(doc.createElement('div'), 'position:fixed;inset:0;z-index:' + Z + ';touch-action:none;cursor:default;background:rgba(238,240,242,.55);');
        layer.setAttribute('data-sd-focus-mode', '');
        layer.tabIndex = -1;
        var whole = css(doc.createElement('img'), 'position:fixed;max-width:none;opacity:.34;filter:grayscale(.5);cursor:grab;user-select:none;-webkit-user-drag:none;');
        whole.alt = '';
        whole.draggable = false;
        whole.src = host.src;
        var win2 = css(doc.createElement('div'), 'position:fixed;overflow:hidden;cursor:grab;box-shadow:0 0 0 2px #0d6efd;');
        var inner = css(doc.createElement('img'), 'position:absolute;max-width:none;user-select:none;-webkit-user-drag:none;');
        inner.alt = '';
        inner.draggable = false;
        inner.src = host.src;
        win2.appendChild(inner);
        var grab = css(doc.createElement('span'), 'position:fixed;width:34px;height:34px;margin:-17px 0 0 -17px;display:grid;place-items:center;border-radius:50%;' +
            'background:rgba(13,17,23,.72);color:#fff;font-size:15px;box-shadow:0 0 0 1.5px rgba(255,255,255,.75);pointer-events:none;');
        grab.className = 'bi bi-arrows-move';
        grab.setAttribute('aria-hidden', 'true');
        var line = css(doc.createElement('div'), 'position:fixed;display:flex;flex-wrap:wrap;align-items:center;gap:10px;box-sizing:border-box;padding:7px 8px 7px 12px;border-radius:9px;' +
            'background:#1f242c;color:#e6edf3;font:12.5px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif;box-shadow:inset 0 0 0 1px #343c48,0 10px 26px rgba(0,0,0,.5);');
        line.setAttribute('role', 'group');
        line.setAttribute('aria-label', _sdT('Set focus'));
        var say = doc.createElement('span');
        var val = css(doc.createElement('em'), 'font-style:normal;color:#8c96a4;font-variant-numeric:tabular-nums;');
        val.setAttribute('aria-live', 'polite');
        var cancel = lineButton(doc, _sdT('Cancel'), false);
        var keep = lineButton(doc, _sdT('Keep'), true);
        line.appendChild(say);
        line.appendChild(val);
        line.appendChild(cancel);
        line.appendChild(keep);
        layer.appendChild(whole);
        layer.appendChild(win2);
        layer.appendChild(grab);
        layer.appendChild(line);

        function values2() { return M.move(values, screen, xy[0], xy[1]); }

        // Measures the frame and lays the parts out on it.
        function place() {
            var r = pic.getBoundingClientRect();
            box = { left: r.left, top: r.top, width: r.width, height: r.height };
            size = M.drawn(drawnAs.fit, r.width, r.height, nat[0], nat[1]);
            var cut = size.cut;
            say.textContent = cut ? _sdT('Drag the picture under its frame. Arrows move it by 1%.') : _sdT('This screen shows the whole picture.');
            whole.style.display = cut ? '' : 'none';
            grab.style.display = cut ? '' : 'none';
            win2.style.display = cut ? '' : 'none';
            val.style.display = cut ? '' : 'none';
            win2.style.left = r.left + 'px';
            win2.style.top = r.top + 'px';
            win2.style.width = r.width + 'px';
            win2.style.height = r.height + 'px';
            [whole, inner].forEach(function (im) {
                im.style.width = size.w + 'px';
                im.style.height = size.h + 'px';
            });
            grab.style.left = (r.left + r.width / 2) + 'px';
            grab.style.top = (r.top + r.height / 2) + 'px';
            var vw = doc.documentElement.clientWidth || win.innerWidth;
            var vh = doc.documentElement.clientHeight || win.innerHeight;
            var lw = Math.max(240, Math.min(r.width, vw - 16));
            line.style.maxWidth = lw + 'px';
            line.style.left = Math.max(8, Math.min(r.left, vw - lw - 8)) + 'px';
            var below = r.top + r.height + 10;
            line.style.top = (below + 90 < vh ? below : Math.max(8, Math.min(vh - 90, r.top + 8))) + 'px';
            paint();
        }

        // Draws the picture at the point, on the canvas element too, and in
        // the Options panel's control when it shows this element.
        function paint() {
            var o = M.offset(xy, size);
            whole.style.left = (box.left + o.left) + 'px';
            whole.style.top = (box.top + o.top) + 'px';
            inner.style.left = o.left + 'px';
            inner.style.top = o.top + 'px';
            val.textContent = _sdT('{var:1}% · {var:2}%', [Math.round(xy[0]), Math.round(xy[1])]);
            if (!moved) return;
            var next = values2();
            host.live(next);
            if (shown && shown.id === host.id && shown.kind === host.kind && shown.el.isConnected) shown.show(next);
        }

        var drag = null, raf = 0;
        function onDown(e) {
            e.stopPropagation();
            if (e.target === layer) {
                // The click that follows the press selects nothing.
                e.preventDefault();
                var swallow = function (ev) { ev.stopPropagation(); ev.preventDefault(); };
                doc.addEventListener('click', swallow, true);
                win.setTimeout(function () { doc.removeEventListener('click', swallow, true); }, 400);
                end(true);
                return;
            }
            if ((e.target !== whole && e.target !== win2 && e.target !== inner) || e.button > 0 || !size.cut) return;
            e.preventDefault();
            drag = { x: e.clientX, y: e.clientY, from: xy.slice(), id: e.pointerId };
            try { layer.setPointerCapture(e.pointerId); } catch (err) {}
            whole.style.cursor = win2.style.cursor = 'grabbing';
        }
        function onMove(e) {
            if (!drag || e.pointerId !== drag.id) return;
            xy = M.drag(drag.from, size, e.clientX - drag.x, e.clientY - drag.y);
            moved = true;
            if (raf) return;
            raf = win.requestAnimationFrame(function () { raf = 0; paint(); });
        }
        function onUp(e) {
            if (!drag || e.pointerId !== drag.id) return;
            drag = null;
            whole.style.cursor = win2.style.cursor = 'grab';
        }
        // Keys reach the mode first, in both documents, and go no further.
        function onKey(e) {
            if (e.key === 'Escape') { e.preventDefault(); e.stopImmediatePropagation(); end(false); return; }
            if (e.key === 'Enter') {
                if (e.target === cancel) return;
                e.preventDefault(); e.stopImmediatePropagation(); end(true); return;
            }
            if (e.key === 'Tab' || e.key === ' ') return;
            var next = size && size.cut ? M.nudge(xy, e.key) : null;
            e.stopImmediatePropagation();
            if (!next) return;
            e.preventDefault();
            xy = next;
            moved = true;
            paint();
        }
        function onWheel(e) { e.preventDefault(); }
        // A press anywhere in the designer outside the canvas keeps.
        function onOutside(e) {
            if (e.target && e.target.nodeType === 1 && e.target.tagName === 'IFRAME') return;
            end(true);
        }
        function onLost() { if (!pic.isConnected) end(false); else place(); }

        var top = window;
        function end(keepIt) {
            if (!active || active.layer !== layer) return;
            active = null;
            if (raf) win.cancelAnimationFrame(raf);
            layer.removeEventListener('pointerdown', onDown);
            layer.removeEventListener('pointermove', onMove);
            layer.removeEventListener('pointerup', onUp);
            layer.removeEventListener('pointercancel', onUp);
            layer.removeEventListener('wheel', onWheel);
            win.removeEventListener('keydown', onKey, true);
            win.removeEventListener('resize', onLost);
            if (top !== win) {
                top.removeEventListener('keydown', onKey, true);
                top.document.removeEventListener('pointerdown', onOutside, true);
            }
            if (layer.parentNode) layer.parentNode.removeChild(layer);
            if (keepIt && moved && pic.isConnected) {
                host.commit(values2());
            } else if (moved) {
                // Put back: the canvas and the panel show the stored values.
                if (pic.isConnected) pic.style.setProperty(prop, before[0], before[1]);
                if (shown && shown.id === host.id && shown.kind === host.kind && shown.el.isConnected) shown.redraw();
            }
        }

        // The canvas's own handlers never see a press in the mode.
        ['click', 'mousedown', 'mouseup', 'dblclick', 'contextmenu'].forEach(function (t) {
            layer.addEventListener(t, function (e) { e.stopPropagation(); if (t === 'contextmenu') e.preventDefault(); });
        });
        cancel.addEventListener('click', function () { end(false); });
        keep.addEventListener('click', function () { end(true); });
        layer.addEventListener('pointerdown', onDown);
        layer.addEventListener('pointermove', onMove);
        layer.addEventListener('pointerup', onUp);
        layer.addEventListener('pointercancel', onUp);
        layer.addEventListener('wheel', onWheel, { passive: false });
        win.addEventListener('keydown', onKey, true);
        win.addEventListener('resize', onLost);
        if (top !== win) {
            top.addEventListener('keydown', onKey, true);
            top.document.addEventListener('pointerdown', onOutside, true);
        }
        active = { layer: layer, end: end };
        doc.body.appendChild(layer);
        place();
        try { layer.focus({ preventScroll: true }); } catch (e) { layer.focus(); }
    }

    return { draw: draw, mode: mode };
});
