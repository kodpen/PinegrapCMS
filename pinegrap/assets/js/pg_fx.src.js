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
 *
 * CSS transitions only. Under prefers-reduced-motion nothing moves (sticky
 * still sticks); turned on while the page is open, every node is put back.
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
 * (the Interactions section's Play button replays it). A paused canvas
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
    var EFFECTS  = ['fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right', 'scale', 'sticky', 'parallax'];
    var TRIGGERS = ['view', 'load', 'hover', 'click'];
    var EASINGS  = ['ease-out', 'ease', 'ease-in', 'ease-in-out', 'linear'];
    var NUMBERS  = {
        duration: [100, 5000, 600], delay: [0, 5000, 0], stagger: [0, 1000, 0],
        distance: [0, 400, 0], offset: [0, 1000, 0], speed: [5, 100, 20]
    };

    // The options of a node from its attributes; get(name) answers an
    // attribute's value or null. null when the effect is unknown.
    function read(get) {
        var effect = String(get('data-pg-fx') || '').toLowerCase();
        if (EFFECTS.indexOf(effect) === -1) return null;
        var num = function (name) {
            var r = NUMBERS[name], v = parseInt(get('data-pg-fx-' + name), 10);
            return isNaN(v) ? r[2] : Math.max(r[0], Math.min(r[1], v));
        };
        var o = { effect: effect, trigger: '' };
        if (effect === 'sticky') { o.offset = num('offset'); return o; }
        if (effect === 'parallax') { o.speed = num('speed'); return o; }
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

    window.pgFx = { read: read, shift: shift, transition: transition, parallax: parallax };

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

    function onSeen(entries) {
        entries.forEach(function (e) {
            var rec = e.target.pgFx;
            if (!rec || reduced()) return;
            if (e.isIntersecting) {
                play(rec);
                if (!rec.o.repeat) seen.unobserve(e.target);
            } else if (rec.o.repeat) {
                arm(rec);
            }
        });
    }

    function float() {
        queued = false;
        if (reduced()) return;
        var view = window.innerHeight || document.documentElement.clientHeight;
        floating.forEach(function (rec) {
            var r = rec.el.getBoundingClientRect();
            if (r.bottom < -view || r.top > view * 2) return;
            // The box measured without the shift it already has.
            var top = r.top - (rec.y || 0);
            rec.y = parallax(top, r.height, view, rec.o.speed);
            put(rec.el, { opacity: '', transform: 'translate3d(0,' + rec.y + 'px,0)' });
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
        if (o.effect === 'parallax') {
            floating.push(rec);
            floatSoon();
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
            if (rec.o.effect !== 'sticky') rec.targets.forEach(restore);
        });
    }

    // Plays a node's interaction once: an entrance from its start, a hover
    // or click effect there and back. The transitions are written as
    // important, so a stylesheet that stops motion (the editor's paused
    // canvas) does not cut them short; the node's own transition comes
    // back when it is over. false when the node has no moving effect.
    function replay(el) {
        if (el && !el.pgFx) bind(el.parentNode || document);
        var rec = el && el.pgFx;
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
