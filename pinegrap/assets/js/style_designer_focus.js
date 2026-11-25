/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Focus control part: "Focus" in an Image's
 * Options (Content › Image, under the fit) and in a background picture's
 * (Style › Color and background). The whole picture small with one point
 * on what must stay in view, and beside it the cut on a phone, a tablet and
 * a desktop. A press on the picture puts the point there, a drag moves it;
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
    var ICONS = { phone: 'bi-phone', tablet: 'bi-tablet', desktop: 'bi-laptop' };

    function names() {
        return { phone: _sdT('Phone'), tablet: _sdT('Tablet'), desktop: _sdT('Desktop') };
    }

    function valueText(xy) {
        return _sdT('{var:1}% · {var:2}%', [Math.round(xy[0]), Math.round(xy[1])]);
    }

    function cssUrl(src) {
        return 'url("' + String(src).replace(/["\\\n\r]/g, function (c) { return '\\' + c.charCodeAt(0).toString(16) + ' '; }) + '")';
    }

    function frameHtml(s, v, shape, src, screen, nm) {
        var f = v.frames[s];
        var h = s === 'desktop' ? 34 : 40;
        return '<div class="sd-fc-fr sd-fc-fr-' + s + (s === screen ? ' is-cur' : '') + (f.own ? ' is-own' : '') + '" data-fc-frame="' + s + '" title="' + esc(nm[s]) + '">' +
            '<div class="sd-fc-fr-pic" style="height:' + h + 'px;width:' + Math.round(h * shape[s]) + 'px;background-image:' + esc(cssUrl(src)) +
                ';background-position:' + f.xy[0] + '% ' + f.xy[1] + '%"></div>' +
            '<span class="bi ' + ICONS[s] + ' sd-fc-fr-ic" aria-hidden="true"></span>' +
        '</div>';
    }

    function html(host, M, v) {
        var nm = names();
        var shape = M.shapes(host.ratio, v.screen, host.measured);
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
        var frames = '<div class="sd-fc-frames">' +
                '<div class="sd-fc-frames-row">' + frameHtml('phone', v, shape, host.src, v.screen, nm) + frameHtml('tablet', v, shape, host.src, v.screen, nm) + '</div>' +
                frameHtml('desktop', v, shape, host.src, v.screen, nm) +
            '</div>';
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

    function draw(host) {
        var el = host.el;
        if (!el || !el.isConnected) return;
        var M = window.PGImageFocus(host.F);
        var values = host.values;
        var v = M.view(values, host.screen);
        el.innerHTML = html(host, M, v);
        var pic = el.querySelector('[data-fc-pic]');
        var pt = el.querySelector('[data-fc-pt]');
        var val = el.querySelector('.sd-fc-val');
        var pending = null, raf = 0, keyTimer = 0;

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
                ['phone', 'tablet', 'desktop'].forEach(function (s) {
                    var fr = el.querySelector('[data-fc-frame="' + s + '"] .sd-fc-fr-pic');
                    if (fr) fr.style.backgroundPosition = w.frames[s].xy[0] + '% ' + w.frames[s].xy[1] + '%';
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
        var keyed = null;
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

    return { draw: draw };
});
