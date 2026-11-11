/**
 * Pinegrap - Enterprise Website Platform
 *
 * Interactions of visual editor nodes. A node carries data-pg-fx="<effect>"
 * and data-pg-fx-* values (pg_fx_attrs() in includes/fn/designer_fx.php),
 * no script; the server adds this file (served as pg_fx.min.js) to a page
 * that holds one (pg_fx_inject()), the editor's Preview does the same.
 *
 *   • load / view: the node (or, with a stagger, each of its children one
 *     after another) comes in from the effect's start: faded out, moved by
 *     the distance or scaled down. view waits until it scrolls into sight
 *     (IntersectionObserver); data-pg-fx-repeat plays it on every entry.
 *   • hover / click: the node goes to the effect's state while pointed at
 *     or focused, or toggles it on each click (class pg-fx-on).
 *   • sticky: position sticky (the head style), top at the offset.
 *   • parallax: the node moves against the scroll by speed percent of its
 *     distance from the middle of the window, while in sight.
 *   • scroll-zoom / scroll-fade / scroll-rise: in step with the scroll, the
 *     node goes from its start (data-pg-fx-from: a scale or an opacity in
 *     percent, a distance in px below its place) while it is below the
 *     window to its own state once its middle reaches the window's middle,
 *     and stays there; a node that cannot get that far (the page's last
 *     strip) gets there at the page's end. Played with parallax, once per
 *     frame; without the script the node simply shows as it is.
 *   • count: the first number in the node's text shows as zero until the
 *     node is seen, then counts up to the number the markup holds over the
 *     duration, after the delay, keeping its group and decimal separators,
 *     its decimals and the words around it; at the end the text is the
 *     markup's again. Without the script the number simply shows.
 *
 * CSS transitions only. Under prefers-reduced-motion nothing moves (sticky
 * still sticks, a scroll effect shows its own state, a number does not
 * count); turned on while the page is open, every node is put back.
 * The node's own opacity, transform and transition are kept and restored.
 *
 * window.pgFxBind(root) binds the nodes under root that are not bound yet
 * and forgets the ones no longer in the document; window.pgFx holds the
 * pure helpers the tests run and replay(el), which plays a node's
 * interaction once more whatever the motion setting.
 *
 * The editor's canvas loads the same file with window.pgFxEditing set: it
 * redraws its body on every change, and only the nodes there when the file
 * starts play their entrance; a node drawn again later shows in place
 * (the Interactions section's Play button replays it), and a press on a
 * counting number shows it at once, so an edit never takes a number half
 * counted. A paused canvas
 * answers prefers-reduced-motion with "reduce", so nothing moves there. Plain ES5 and no jQuery:
 * it has to work whatever the design loads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Loaded twice: the first copy already binds.
    if (typeof window.pgFxBind === 'function') {
        return;
    }

    // The same names, ranges and defaults as pg_fx_normalize().
    var EFFECTS  = ['fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'scale', 'sticky', 'parallax',
        'scroll-zoom', 'scroll-fade', 'scroll-rise', 'count'];
    var TRIGGERS = ['view', 'load', 'hover', 'click'];
    var EASINGS  = ['ease-out', 'ease', 'ease-in', 'ease-in-out', 'linear'];
    var NUMBERS  = {
        duration: [100, 5000, 600], delay: [0, 5000, 0], stagger: [0, 1000, 0],
        distance: [0, 400, 0], offset: [0, 1000, 0], speed: [5, 100, 20]
    };
    // The start of a scroll-linked effect, as pg_fx_scroll_from().
    var FROM = { 'scroll-zoom': [25, 200, 80], 'scroll-fade': [0, 100, 0], 'scroll-rise': [0, 400, 80] };
    // The duration and delay of a count, as pg_fx_count_numbers().
    var COUNT = { duration: [100, 5000, 2000], delay: [0, 5000, 0] };

    // The options of a node from its attributes; get(name) answers an
    // attribute's value or null. null when the effect is unknown.
    function read(get) {
        var effect = String(get('data-pg-fx') || '').toLowerCase();
        if (EFFECTS.indexOf(effect) === -1) return null;
        var num = function (name, range) {
            var r = range || NUMBERS[name], v = parseInt(get('data-pg-fx-' + name), 10);
            return isNaN(v) ? r[2] : Math.max(r[0], Math.min(r[1], v));
        };
        var o = { effect: effect, trigger: '' };
        if (effect === 'sticky') { o.offset = num('offset'); return o; }
        if (effect === 'parallax') { o.speed = num('speed'); return o; }
        if (FROM[effect]) { o.from = num('from', FROM[effect]); o.scroll = true; return o; }
        if (effect === 'count') {
            o.count = true;
            o.duration = num('duration', COUNT.duration);
            o.delay = num('delay', COUNT.delay);
            return o;
        }
        var trigger = String(get('data-pg-fx-trigger') || '').toLowerCase();
        var easing = String(get('data-pg-fx-easing') || '').toLowerCase();
        o.trigger  = TRIGGERS.indexOf(trigger) !== -1 ? trigger : TRIGGERS[0];
        o.entrance = o.trigger === 'load' || o.trigger === 'view';
        o.duration = num('duration');
        o.delay    = num('delay');
        o.easing   = EASINGS.indexOf(easing) !== -1 ? easing : EASINGS[0];
        o.stagger  = o.entrance ? num('stagger') : 0;
        o.distance = num('distance') || (o.entrance ? 24 : 6);
        o.repeat   = o.trigger === 'view' && get('data-pg-fx-repeat') !== null;
        return o;
    }

    // The opacity and transform an entrance starts from, or a hover or
    // click goes to; '' leaves the node's own value.
    function shift(o) {
        var d = o.distance, x = 0, y = 0;
        if (o.effect === 'slide-up')    y = d;
        if (o.effect === 'slide-down')  y = -d;
        if (o.effect === 'slide-left')  x = d;
        if (o.effect === 'slide-right') x = -d;
        // A hover or click moves the other way: slide-up lifts the node.
        if (!o.entrance) { x = -x; y = -y; }
        var transform = '';
        if (x || y) transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
        if (o.effect === 'scale') transform = o.entrance ? 'scale(0.9)' : 'scale(1.05)';
        var opacity = '';
        if (o.entrance) opacity = '0';
        else if (o.effect === 'fade') opacity = '0.6';
        return { opacity: opacity, transform: transform };
    }

    // The transition of a move that waits `delay` ms.
    function transition(o, delay) {
        var t = o.duration + 'ms ' + o.easing + ' ' + delay + 'ms';
        return 'opacity ' + t + ',transform ' + t;
    }

    // The parallax offset (px) of a box whose top is `top` and height
    // `height` in a window `view` high: speed percent of the distance
    // between the box's middle and the window's, against the scroll.
    function parallax(top, height, view, speed) {
        var y = -((top + height / 2) - view / 2) * speed / 100;
        return Math.round(y * 10) / 10 || 0;
    }

    // How far (0 to 1) a scroll-linked effect has played for a box whose
    // top is `top` and height `height` in a window `view` high: 0 while the
    // top is below the window, 1 once the box's middle reaches the window's
    // middle. `floor` is the box's top with the page scrolled to its end: a
    // box that cannot reach the middle ends there, so it never stays half
    // played; one that never comes into the window is left whole.
    function progress(top, height, view, floor) {
        var end = Math.max(view / 2 - height / 2, floor);
        if (end >= view) return 1;
        var p = (view - top) / (view - end);
        return Math.max(0, Math.min(1, Math.round(p * 1000) / 1000));
    }

    // The px a scroll-rise node sits below its place at progress p.
    function rise(o, p) {
        return p >= 1 ? 0 : Math.round(o.from * (1 - p) * 10) / 10;
    }

    // The opacity and transform of a scroll-linked effect at progress p;
    // '' leaves the node's own value, as every one is at the end.
    function scrollState(o, p) {
        var s = { opacity: '', transform: '' };
        if (p >= 1) return s;
        var part = Math.round((o.from + (100 - o.from) * p) * 10) / 1000;
        if (o.effect === 'scroll-zoom') s.transform = 'scale(' + part + ')';
        if (o.effect === 'scroll-fade') s.opacity = String(part);
        if (o.effect === 'scroll-rise' && rise(o, p)) s.transform = 'translate3d(0,' + rise(o, p) + 'px,0)';
        return s;
    }

    // The first number in a text as its figures show it, or null: the run
    // of digits with the separators between them, which tell a group mark
    // from a decimal mark. Two kinds of mark: the last is the decimal one.
    // One kind more than once, a space or an apostrophe: groups. One point
    // or comma once: a decimal mark, unless three digits follow it and one
    // to three digits but a lone 0 come before it (12,500 but 4.9, 0.125
    // and 1234.567). Groups are three digits, or two above the last three
    // (12,50,000); separators that make no number (1.2.3) give none.
    // `text` is the whole text, kept for the end of the count.
    function number(text) {
        var m = /\d(?:[\d.,\u00a0\u202f'\u2019]*\d)?/.exec(text);
        if (!m) return null;
        var raw = m[0], marks = raw.replace(/\d+/g, ''), parts = raw.split(/\D/);
        var point = '', group = '', last = marks.charAt(marks.length - 1);
        if (parts.indexOf('') !== -1) return null;
        var kinds = marks.split('').filter(function (c, i) { return marks.indexOf(c) === i; });
        if (kinds.length > 2) return null;
        if (kinds.length === 2) {
            if (marks.indexOf(last) !== marks.length - 1 || /[\u00a0\u202f'\u2019]/.test(last)) return null;
            point = last;
            group = kinds[0] === last ? kinds[1] : kinds[0];
        } else if (kinds.length === 1) {
            if (marks.length > 1 || /[\u00a0\u202f'\u2019]/.test(last) || (parts[1].length === 3 && parts[0] !== '0' && parts[0].length <= 3)) group = last;
            else point = last;
        }
        var fraction = point ? parts.pop() : '';
        var groups = parts, sizes = [3, 3];
        if (group) {
            if (groups.length > 2) sizes[1] = groups[1].length;
            if (sizes[1] < 2 || sizes[1] > 3 || groups[groups.length - 1].length !== 3 || groups[0].length > sizes[1]) return null;
            for (var i = 1; i < groups.length - 1; i++) {
                if (groups[i].length !== sizes[1]) return null;
            }
        }
        var whole = groups.join('');
        return {
            text: text, prefix: text.slice(0, m.index), suffix: text.slice(m.index + raw.length),
            value: parseFloat(whole + '.' + (fraction || '0')), decimals: fraction.length,
            point: point, group: group, sizes: sizes,
            // A number written with leading zeros (007) keeps its width.
            width: whole.length > 1 && whole.charAt(0) === '0' ? whole.length : 0
        };
    }

    // The text of a counting number at p (0 to 1) of its duration: its value
    // eased out (fast, then slowing to the end), written as the number is;
    // the text itself once p reaches 1.
    function countText(n, p) {
        if (p >= 1) return n.text;
        var eased = 1 - Math.pow(1 - Math.max(0, p), 3);
        var digits = (n.value * eased).toFixed(n.decimals).split('.');
        var whole = digits[0], out = '', size = n.sizes[0];
        while (whole.length < n.width) whole = '0' + whole;
        if (n.group) {
            while (whole.length > size) {
                out = n.group + whole.slice(-size) + out;
                whole = whole.slice(0, -size);
                size = n.sizes[1];
            }
        }
        return n.prefix + whole + out + (n.decimals ? n.point + digits[1] : '') + n.suffix;
    }

    window.pgFx = {
        read: read, shift: shift, transition: transition, parallax: parallax,
        progress: progress, scrollState: scrollState, number: number, countText: countText
    };

    if (typeof document === 'undefined') {
        return;
    }

    var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var bound = [];
    var floating = [];
    var seen = null;
    var queued = false;
    var binds = 0;

    function reduced() {
        return !!(reduce && reduce.matches);
    }

    // The elements an entrance moves: the node, or with a stagger its
    // children. A child without a box of its own (display: contents, as the
    // editor's canvas wraps every element) stands for its first child that
    // has one.
    function targets(el, o) {
        if (!o.stagger) return [el];
        var out = [];
        for (var i = 0; i < el.children.length; i++) {
            var c = el.children[i];
            while (c && window.getComputedStyle && window.getComputedStyle(c).display === 'contents') c = c.firstElementChild;
            if (c) out.push(c);
        }
        return out.length ? out : [el];
    }

    // The node's own values, kept the first time it is touched.
    function base(t) {
        if (!t.pgFxBase) t.pgFxBase = { opacity: t.style.opacity, transform: t.style.transform, transition: t.style.transition };
        return t.pgFxBase;
    }

    function put(t, s, move) {
        var b = base(t);
        t.style.transition = move || 'none';
        t.style.opacity = s.opacity !== '' ? s.opacity : b.opacity;
        t.style.transform = s.transform ? (b.transform ? b.transform + ' ' : '') + s.transform : b.transform;
    }

    function restore(t) {
        if (!t.pgFxBase) return;
        t.style.opacity = t.pgFxBase.opacity;
        t.style.transform = t.pgFxBase.transform;
        t.style.transition = t.pgFxBase.transition;
    }

    function arm(rec) {
        var o = rec.o, size = 0;
        // The observer measures the moved box, and a box moved off the
        // screen is never seen. Without a stagger the moved node is the
        // observed one: its slide stays below its own width (left, right)
        // or height (up, down), so it keeps a part of the place it lands in.
        if (!o.stagger && o.effect.indexOf('slide-') === 0) {
            size = /left|right/.test(o.effect) ? rec.el.offsetWidth : rec.el.offsetHeight;
        }
        if (size && o.distance >= size) {
            var near = {};
            for (var k in o) near[k] = o[k];
            near.distance = size - 1;
            o = near;
        }
        rec.targets.forEach(function (t) { put(t, shift(o)); });
        // Read once so the start state is drawn before the move begins.
        void rec.el.offsetWidth;
        rec.el.classList.add('pg-fx-armed');
    }

    function play(rec) {
        rec.targets.forEach(function (t, i) {
            put(t, { opacity: '', transform: '' }, transition(rec.o, rec.o.delay + i * rec.o.stagger));
        });
    }

    // The first number in the text under a node, in document order, with
    // the text node that holds it (count), or null.
    function firstNumber(node) {
        for (var c = node.firstChild; c; c = c.nextSibling) {
            if (c.nodeType === 3) {
                var n = number(c.data);
                if (n) { n.node = c; return n; }
            } else if (c.nodeType === 1 && !/^(script|style|template)$/i.test(c.nodeName)) {
                var inner = firstNumber(c);
                if (inner) return inner;
            }
        }
        return null;
    }

    function write(node, text) {
        if (node.data !== text) node.data = text;
    }

    // The number back as the markup holds it; a frame still queued stops.
    function finish(rec) {
        if (!rec.count) return;
        rec.run = (rec.run || 0) + 1;
        write(rec.count.node, rec.count.text);
    }

    // On the canvas a press or focus shows a counting number at once, so an
    // edit begun on it never takes a number half counted. Once per node:
    // the first bind and the Play button both ask. Not on the page, where a
    // finger that scrolls over the number would end its count.
    function holdOnPress(rec) {
        if (!window.pgFxEditing || rec.held) return;
        rec.held = true;
        rec.el.addEventListener('pointerdown', function () { finish(rec); });
        rec.el.addEventListener('focusin', function () { finish(rec); });
    }

    // Counts a node's number up from zero, one write per frame, after its
    // delay. `force` counts whatever the motion setting (replay()); else
    // reduced motion turned on midway shows the number at once.
    function countUp(rec, force) {
        var c = rec.count, o = rec.o, run = rec.run = (rec.run || 0) + 1, from = Date.now() + o.delay;
        var frame = window.requestAnimationFrame || window.setTimeout;
        var step = function () {
            if (rec.run !== run) return;
            if (!force && reduced()) { finish(rec); return; }
            var p = (Date.now() - from) / o.duration;
            if (p >= 1) { finish(rec); return; }
            write(c.node, countText(c, p));
            frame(step);
        };
        write(c.node, countText(c, 0));
        frame(step);
    }

    function onSeen(entries) {
        entries.forEach(function (e) {
            var rec = e.target.pgFx;
            if (!rec || reduced()) return;
            if (e.isIntersecting && rec.o.count) {
                countUp(rec);
                seen.unobserve(e.target);
            } else if (e.isIntersecting) {
                play(rec);
                if (!rec.o.repeat) seen.unobserve(e.target);
            } else if (rec.o.repeat) {
                arm(rec);
            }
        });
    }

    // One frame of parallax and the scroll-linked effects. Every box is
    // measured before any is written, so the page is laid out once.
    function float() {
        queued = false;
        if (reduced()) return;
        var root = document.documentElement;
        var view = window.innerHeight || root.clientHeight;
        // How much further the page scrolls: a box's top at the page's end
        // is its top now less that.
        var height = Math.max(root.scrollHeight || 0, document.body ? document.body.scrollHeight : 0);
        var down = Math.max(0, height - view - (window.pageYOffset || root.scrollTop || 0));
        var writes = [];
        floating.forEach(function (rec) {
            var r = rec.el.getBoundingClientRect();
            if (r.bottom < -view || r.top > view * 2) return;
            if (!rec.o.scroll) {
                // The box measured without the shift it already has.
                var y = parallax(r.top - (rec.y || 0), r.height, view, rec.o.speed);
                writes.push([rec, y, { opacity: '', transform: 'translate3d(0,' + y + 'px,0)' }]);
                return;
            }
            // The box without the scale (about its middle) and the rise it
            // already has: its layout height, its middle less the rise.
            var h = rec.el.offsetHeight || r.height;
            var top = r.top + r.height / 2 - (rec.y || 0) - h / 2;
            var p = progress(top, h, view, top - down);
            var s = scrollState(rec.o, p);
            // A fade ends at the node's own opacity, so it starts from a
            // part of that.
            var own = parseFloat(base(rec.el).opacity);
            if (s.opacity !== '' && !isNaN(own)) s.opacity = String(Math.round(parseFloat(s.opacity) * own * 1000) / 1000);
            if (rec.last === s.opacity + '|' + s.transform) return;
            rec.last = s.opacity + '|' + s.transform;
            writes.push([rec, rec.o.effect === 'scroll-rise' ? rise(rec.o, p) : 0, s]);
        });
        writes.forEach(function (w) {
            w[0].y = w[1];
            put(w[0].el, w[2]);
            // At its own state the node has its own transition back.
            if (w[0].o.scroll && !w[2].opacity && !w[2].transform) w[0].el.style.transition = base(w[0].el).transition;
        });
    }

    function floatSoon() {
        if (queued || !floating.length) return;
        queued = true;
        (window.requestAnimationFrame || window.setTimeout)(float);
    }

    function start(rec, later) {
        var el = rec.el, o = rec.o;
        if (o.effect === 'sticky') {
            // The page's head style makes it sticky; a page without it (the
            // editor's canvas) gets it here.
            if (window.getComputedStyle && window.getComputedStyle(el).position === 'static') el.style.position = 'sticky';
            if (o.offset) el.style.top = o.offset + 'px';
            return;
        }
        if (reduced()) {
            el.classList.add('pg-fx-armed');
            return;
        }
        if (o.effect === 'parallax' || o.scroll) {
            floating.push(rec);
            floatSoon();
            return;
        }
        if (o.count) {
            // A node drawn again by a later render in the editor shows its
            // number; the Play button counts it.
            if (later) return;
            rec.count = firstNumber(el);
            if (!rec.count) return;
            holdOnPress(rec);
            // Zero until seen: the frame that first shows it never shows
            // the number before the count.
            write(rec.count.node, countText(rec.count, 0));
            if (seen) seen.observe(el); else countUp(rec);
            return;
        }
        if (o.entrance && later) {
            el.classList.add('pg-fx-armed');
            return;
        }
        if (o.entrance) {
            arm(rec);
            if (o.trigger === 'view' && seen) {
                seen.observe(el);
            } else {
                (window.requestAnimationFrame || window.setTimeout)(function () { play(rec); });
            }
            return;
        }
        var on = function () { if (!reduced()) put(el, shift(o), transition(o, o.delay)); };
        var off = function () { put(el, { opacity: '', transform: '' }, transition(o, 0)); };
        if (o.trigger === 'hover') {
            el.addEventListener('mouseenter', on);
            el.addEventListener('mouseleave', off);
            el.addEventListener('focusin', on);
            el.addEventListener('focusout', off);
        } else {
            el.addEventListener('click', function () {
                if (el.classList.toggle('pg-fx-on')) on(); else off();
            });
        }
    }

    // Binds the nodes under root (a document or an element) not bound yet.
    // Nodes taken out of the document (the editor's canvas redraws its
    // body) are forgotten first, so nothing keeps them.
    function bind(root) {
        root = root || document;
        var later = !!window.pgFxEditing && binds++ > 0;
        if (!seen && window.IntersectionObserver) {
            seen = new window.IntersectionObserver(onSeen, { threshold: 0 });
        }
        bound = bound.filter(function (rec) {
            if (document.documentElement.contains(rec.el)) return true;
            if (seen) seen.unobserve(rec.el);
            return false;
        });
        floating = floating.filter(function (rec) { return bound.indexOf(rec) !== -1; });
        var list = root.querySelectorAll ? root.querySelectorAll('[data-pg-fx]') : [];
        for (var i = 0; i < list.length; i++) {
            var el = list[i];
            if (el.pgFx) continue;
            var o = read(function (name) { return el.getAttribute(name); });
            if (!o) continue;
            var rec = { el: el, o: o, targets: o.entrance ? targets(el, o) : [el] };
            el.pgFx = rec;
            bound.push(rec);
            start(rec, later);
        }
    }

    // Reduced motion turned on while the page is open: every node back to
    // its own state, entrances shown.
    function still() {
        if (!reduced()) return;
        bound.forEach(function (rec) {
            rec.el.classList.add('pg-fx-armed');
            rec.el.classList.remove('pg-fx-on');
            rec.y = 0;
            rec.last = null;
            finish(rec);
            if (rec.o.effect !== 'sticky') rec.targets.forEach(restore);
        });
    }

    // Plays a node's interaction once: an entrance from its start, a hover
    // or click effect there and back. The transitions are written as
    // important, so a stylesheet that stops motion (the editor's paused
    // canvas) does not cut them short; the node's own transition comes
    // back when it is over. A count counts from zero once more. false when
    // the node has no moving effect or no number to count.
    function replay(el) {
        if (el && !el.pgFx) bind(el.parentNode || document);
        var rec = el && el.pgFx;
        if (rec && rec.o.count) {
            // A node bound by a later render has not read its number yet;
            // its text is the markup's.
            if (!rec.count) rec.count = firstNumber(el);
            if (!rec.count) return false;
            // A node bound under the paused canvas or by a later render
            // has no press to stop it yet.
            holdOnPress(rec);
            countUp(rec, true);
            return true;
        }
        if (!rec || !rec.o.trigger) return false;
        var o = rec.o, ts = o.entrance ? rec.targets : [el];
        var still = { opacity: '', transform: '' };
        var go = function (s, wait) {
            ts.forEach(function (t, i) {
                put(t, s);
                t.style.setProperty('transition', transition(o, wait + i * (o.stagger || 0)), 'important');
            });
        };
        ts.forEach(function (t) { put(t, o.entrance ? shift(o) : still); });
        void el.offsetWidth;
        go(o.entrance ? still : shift(o), o.delay);
        var total = o.delay + (ts.length - 1) * (o.stagger || 0) + o.duration;
        clearTimeout(rec.replay);
        rec.replay = setTimeout(function () {
            if (!o.entrance) go(still, 0);
            rec.replay = setTimeout(function () {
                ts.forEach(function (t) { t.style.removeProperty('transition'); restore(t); });
            }, o.entrance ? 50 : o.duration + 50);
        }, total + (o.entrance ? 0 : 300));
        return true;
    }

    window.pgFx.replay = replay;
    window.pgFxBind = bind;
    window.addEventListener('scroll', floatSoon, { passive: true });
    window.addEventListener('resize', floatSoon);
    // Pictures that arrive late move the boxes under them.
    window.addEventListener('load', floatSoon);
    if (reduce) {
        if (reduce.addEventListener) reduce.addEventListener('change', still);
        else if (reduce.addListener) reduce.addListener(still);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { bind(document); });
    } else {
        bind(document);
    }
})();
