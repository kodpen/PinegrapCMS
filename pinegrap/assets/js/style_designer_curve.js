/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the curve part: the easing curve editor that opens
 * under an Easing row (Transition in Shadow and effect, Interactions). A
 * square graph, time across and the change up, whose two handles are
 * dragged or moved with the arrow keys; the bead's track under it; the four
 * numbers in one field; "Play", "Save as…" and, for a stored curve that was
 * changed, "Update". The element takes the curve when a handle is let go,
 * the keys rest or the numbers are entered. A stored curve is one of the
 * design's own variables, written by the part 'variables' (host.store()).
 *
 * The core (_sdEaseOpen()) hands the host: the row, the easing written, the
 * look's easing, the stored curves, write(), play(), store(), closed(). A
 * panel draw removes the editor and the core opens it again; what the
 * editor showed is kept per row here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
StyleDesigner.definePart('curve', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var C = window.PGCurve;
    // The graph's drawing room: 200 units square, 8 of margin; time runs
    // across 0 to 1, the change up -0.5 to 1.5.
    var SIZE = 200, PAD = 8, SPAN = SIZE - 2 * PAD;
    var LABELS = null;

    // The open editor: { el, host, p, held, timer }.
    var E = null;
    // Per row ('trans:<id>', 'fx:<id>'): the stored curve a change came
    // from, and what had the focus, kept across a panel draw.
    var memo = {};

    function gx(x) { return PAD + x * SPAN; }
    function gy(y) { return PAD + (C.HIGH - y) / (C.HIGH - C.LOW) * SPAN; }

    function labels() {
        return LABELS || (LABELS = [_sdT('First handle'), _sdT('Second handle')]);
    }
    function handleText(i, p) {
        return _sdT('{var:1}, {var:2} across, {var:3} up', [labels()[i], p[i * 2], p[i * 2 + 1]]);
    }

    function graphHtml() {
        var line = function (y, cls) { return '<line class="' + cls + '" x1="' + PAD + '" x2="' + (SIZE - PAD) + '" y1="' + gy(y) + '" y2="' + gy(y) + '"/>'; };
        var h = '<svg class="sd-curve-graph" viewBox="0 0 ' + SIZE + ' ' + SIZE + '" role="group" aria-label="' + esc(_sdT('Easing curve')) + '">' +
            line(0, 'sd-curve-edge') + line(1, 'sd-curve-edge') +
            '<text class="sd-curve-word" x="' + (PAD + 2) + '" y="' + (gy(1) - 4) + '">' + esc(_sdT('end')) + '</text>' +
            '<text class="sd-curve-word" x="' + (PAD + 2) + '" y="' + (gy(0) + 12) + '">' + esc(_sdT('start')) + '</text>' +
            '<text class="sd-curve-word" x="' + (SIZE - PAD - 2) + '" y="' + (gy(0) + 12) + '" text-anchor="end">' + esc(_sdT('time')) + '</text>' +
            '<line class="sd-curve-linear" x1="' + gx(0) + '" y1="' + gy(0) + '" x2="' + gx(1) + '" y2="' + gy(1) + '"/>' +
            '<line class="sd-curve-arm" data-arm="0"/><line class="sd-curve-arm" data-arm="1"/>' +
            '<path class="sd-curve-path" fill="none"/>' +
            '<text class="sd-curve-tip" hidden></text>';
        [0, 1].forEach(function (i) {
            h += '<circle class="sd-curve-handle" r="6" tabindex="0" role="slider" data-handle="' + i + '" aria-label="' + esc(labels()[i]) + '"/>';
        });
        return h + '</svg>';
    }

    function editorHtml() {
        var marks = '';
        for (var i = 0; i <= 10; i++) marks += '<span class="sd-curve-mark"></span>';
        return graphHtml() +
            '<div class="sd-curve-track" aria-hidden="true">' + marks + '<span class="sd-curve-bead"></span></div>' +
            '<input type="text" class="form-control form-control-sm sd-curve-nums" spellcheck="false" aria-label="' + esc(_sdT('The four numbers of the curve')) + '">' +
            '<p class="sd-curve-line is-bad" data-line="bad" hidden>' + esc(_sdT('Four numbers. The first and the third stay between 0 and 1.')) + '</p>' +
            '<p class="sd-curve-line" data-line="over" hidden>' + esc(_sdT('Goes past the end and comes back.')) + '</p>' +
            '<p class="sd-curve-line" data-line="from" hidden></p>' +
            '<div class="sd-curve-acts" data-acts></div>' +
            '<p class="sd-curve-line" data-line="name-bad" hidden></p>' +
            (E.host.reduced ? '<p class="sd-curve-line">' + esc(_sdT('Your system asks for less motion.')) + '</p>' : '') +
            '<span class="visually-hidden" aria-live="polite" data-live></span>';
    }

    // The buttons under the numbers, or the name asked in place, or the
    // question before an update.
    function actsHtml(mode) {
        var b = function (act, label, fill) {
            return '<button type="button" class="btn btn-sm ' + (fill ? 'btn-outline theme-primary' : 'btn-outline theme-secondary') + '" data-act="' + act + '">' + esc(label) + '</button>';
        };
        if (mode === 'name') {
            return '<input type="text" class="form-control form-control-sm sd-curve-name" maxlength="40" aria-label="' + esc(_sdT('Name of the curve')) + '">' +
                b('save', _sdT('Save'), true) + b('cancel', _sdT('Cancel'));
        }
        var from = memo[E.host.at] && memo[E.host.at].from;
        if (mode === 'sure') {
            return '<span class="sd-curve-ask">' + esc(_sdT('Every element that uses “{var}” changes.', from.name)) + '</span>' +
                b('update-sure', _sdT('Update'), true) + b('cancel', _sdT('Cancel'));
        }
        var store = E.host.mayStore;
        return b('play', _sdT('Play')) + (from && store ? b('update', _sdT('Update “{var}”', from.name)) : '') + (store ? b('name', _sdT('Save as…')) : '');
    }

    // The graph, the field and the lines for the curve of the moment.
    function draw(keepField) {
        var el = E.el, p = E.p;
        var x1 = gx(p[0]), y1 = gy(p[1]), x2 = gx(p[2]), y2 = gy(p[3]);
        el.querySelector('.sd-curve-path').setAttribute('d', 'M' + gx(0) + ' ' + gy(0) + 'C' + x1 + ' ' + y1 + ' ' + x2 + ' ' + y2 + ' ' + gx(1) + ' ' + gy(1));
        var arms = el.querySelectorAll('.sd-curve-arm');
        [[gx(0), gy(0), x1, y1], [gx(1), gy(1), x2, y2]].forEach(function (a, i) {
            arms[i].setAttribute('x1', a[0]); arms[i].setAttribute('y1', a[1]);
            arms[i].setAttribute('x2', a[2]); arms[i].setAttribute('y2', a[3]);
        });
        el.querySelectorAll('.sd-curve-handle').forEach(function (c, i) {
            c.setAttribute('cx', i ? x2 : x1);
            c.setAttribute('cy', i ? y2 : y1);
            c.setAttribute('aria-valuetext', handleText(i, p));
        });
        if (!keepField) {
            var f = el.querySelector('.sd-curve-nums');
            f.value = C.text(p);
            f.classList.remove('is-invalid');
            el.querySelector('[data-line="bad"]').hidden = true;
        }
        el.querySelector('[data-line="over"]').hidden = !C.overshoots(p);
        var marks = el.querySelectorAll('.sd-curve-mark');
        for (var i = 0; i < marks.length; i++) marks[i].style.left = (Math.max(0, Math.min(1, C.at(p, i / 10))) * 100) + '%';
        var from = memo[E.host.at] && memo[E.host.at].from;
        var fromLine = el.querySelector('[data-line="from"]');
        fromLine.hidden = !from;
        if (from) fromLine.textContent = _sdT('Changed from “{var}”. The other elements keep it until it is updated.', from.name);
    }

    // The bead runs its track once with the curve.
    function run() {
        if (!E || E.host.reduced) return;
        var bead = E.el.querySelector('.sd-curve-bead');
        bead.style.transition = 'none';
        bead.style.left = '0%';
        void bead.offsetWidth;
        bead.style.transition = 'left 900ms ' + C.css(E.p);
        bead.style.left = '100%';
    }

    function setActs(mode) {
        var box = E.el.querySelector('[data-acts]');
        box.innerHTML = actsHtml(mode);
        E.el.querySelector('[data-line="name-bad"]').hidden = true;
        var first = box.querySelector('input, [data-act="cancel"]');
        if (first && mode) first.focus();
    }

    function say(text) {
        var live = E.el.querySelector('[data-live]');
        live.textContent = '';
        setTimeout(function () { if (live.isConnected) live.textContent = text; }, 30);
    }

    // The curve of the moment written to the element as its own; a stored
    // one it came from is named in the line.
    function commit(focus, force) {
        clearTimeout(E.timer);
        var m = memo[E.host.at] || (memo[E.host.at] = {});
        if (E.host.stored && !m.from) m.from = E.host.stored;
        m.focus = focus || '';
        var css = C.css(E.p);
        if (force || E.host.stored || css !== C.css(C.points(E.host.value) || [])) E.host.write(css);
    }

    function bindHandle(c) {
        var i = +c.getAttribute('data-handle');
        var tip = E.el.querySelector('.sd-curve-tip');
        var svg = E.el.querySelector('.sd-curve-graph');
        var frame = 0;
        c.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            c.setPointerCapture(e.pointerId);
            E.held = true;
            c.classList.add('is-held');
        });
        c.addEventListener('pointermove', function (e) {
            if (!E.held || !c.hasPointerCapture(e.pointerId)) return;
            var r = svg.getBoundingClientRect();
            var x = ((e.clientX - r.left) / r.width * SIZE - PAD) / SPAN;
            var y = C.HIGH - ((e.clientY - r.top) / r.height * SIZE - PAD) / SPAN * (C.HIGH - C.LOW);
            E.p = C.move(E.p, i, x, y);
            if (frame) return;
            frame = requestAnimationFrame(function () {
                frame = 0;
                if (!E) return;
                draw(false);
                tip.hidden = false;
                tip.textContent = E.p[i * 2] + ', ' + E.p[i * 2 + 1];
                tip.setAttribute('x', Math.min(gx(E.p[i * 2]) + 10, SIZE - 60));
                tip.setAttribute('y', Math.max(gy(E.p[i * 2 + 1]) - 10, 12));
            });
        });
        var up = function () {
            if (!E || !E.held) return;
            E.held = false;
            c.classList.remove('is-held');
            tip.hidden = true;
            run();
            commit('h' + i);
        };
        c.addEventListener('pointerup', up);
        c.addEventListener('pointercancel', up);
        c.addEventListener('keydown', function (e) {
            var d = e.shiftKey ? 0.1 : 0.01;
            var dx = e.key === 'ArrowLeft' ? -d : e.key === 'ArrowRight' ? d : 0;
            var dy = e.key === 'ArrowDown' ? -d : e.key === 'ArrowUp' ? d : 0;
            if (!dx && !dy) return;
            e.preventDefault();
            E.p = C.move(E.p, i, E.p[i * 2] + dx, E.p[i * 2 + 1] + dy);
            draw(false);
            // Written once the keys rest: one undo step.
            clearTimeout(E.timer);
            E.timer = setTimeout(function () { run(); commit('h' + i); }, 500);
        });
    }

    function bind() {
        var el = E.el;
        el.querySelectorAll('.sd-curve-handle').forEach(bindHandle);
        var nums = el.querySelector('.sd-curve-nums');
        var take = function () {
            var p = C.parse(nums.value);
            nums.classList.toggle('is-invalid', !p);
            el.querySelector('[data-line="bad"]').hidden = !!p;
            if (!p) return;
            E.p = p;
            draw(false);
            run();
            commit('nums');
        };
        nums.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); take(); } });
        nums.addEventListener('change', take);
        el.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            e.stopPropagation();
            close();
        });
        el.addEventListener('click', function (e) {
            var b = e.target.closest('[data-act]');
            if (b) act(b.getAttribute('data-act'));
        });
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target.classList.contains('sd-curve-name')) { e.preventDefault(); act('save'); }
        });
    }

    function act(what) {
        var host = E.host, m = memo[host.at] || (memo[host.at] = {});
        if (what === 'play') { if (!host.reduced && host.play) host.play(); return; }
        if (what === 'name') { setActs('name'); return; }
        if (what === 'cancel') { setActs(''); var nb = E.el.querySelector('[data-act="name"]'); if (nb) nb.focus(); return; }
        if (what === 'update') { setActs('sure'); return; }
        var name = what === 'save' ? E.el.querySelector('.sd-curve-name').value.trim() : '';
        var c = what === 'save' ? { name: name, value: C.text(E.p) } : { key: m.from.key, value: C.text(E.p) };
        var at = host.at;
        host.store(what === 'save' ? 'add' : 'update', c).then(function (res) {
            if (!E || E.host.at !== at) return;
            if (!res || res.refused) {
                var bad = E.el.querySelector('[data-line="name-bad"]');
                bad.textContent = res ? res.refused : '';
                bad.hidden = false;
                return;
            }
            var saved = what === 'save' ? name : m.from.name;
            m.from = null;
            m.focus = 'name';
            m.said = _sdT('{var} saved.', saved);
            host.write(host.varOf({ key: res.key, value: C.text(E.p) }));
        });
    }

    function close() {
        if (!E) return;
        var host = E.host;
        clearTimeout(E.timer);
        if (E.el.parentNode) E.el.parentNode.removeChild(E.el);
        E = null;
        if (host.closed) host.closed();
    }

    // Opens the editor under host.row; a second open replaces the first.
    function open(host) {
        if (E && E.el.parentNode) E.el.parentNode.removeChild(E.el);
        var m = memo[host.at] || (memo[host.at] = {});
        // A stored curve chosen again from the list ends its "Changed from".
        if (host.stored || !/^cubic-bezier/.test(host.value)) m.from = null;
        var p = C.points(host.value) || C.points(host.look) || C.NAMED['ease-in-out'].slice();
        var el = document.createElement('div');
        el.className = 'sd-curve';
        E = { el: el, host: host, p: p, held: false, timer: 0 };
        el.innerHTML = editorHtml();
        host.row.parentNode.insertBefore(el, host.row.nextSibling);
        setActs('');
        draw(false);
        bind();
        if (host.custom) commit('nums', true);
        if (m.said) { say(m.said); m.said = ''; }
        var f = m.focus === 'h0' || m.focus === 'h1' ? el.querySelectorAll('.sd-curve-handle')[+m.focus.charAt(1)]
            : m.focus === 'nums' ? el.querySelector('.sd-curve-nums') : m.focus === 'name' ? el.querySelector('[data-act="name"]') : null;
        m.focus = '';
        if (f) f.focus({ preventScroll: true });
        run();
    }

    return { open: open, close: close };
});
