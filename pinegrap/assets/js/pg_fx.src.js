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
 *     strip) gets there at the page's end. A rise starts no lower than
 *     the page's end, so it never makes the page longer. Played with
 *     parallax, once per frame; without the script the node simply shows
 *     as it is.
 *   • count: the first number in the node's text shows as zero until the
 *     node is seen, then counts up to the number the markup holds over the
 *     duration, after the delay, keeping its group and decimal separators,
 *     its decimals and the words around it; at the end the text is the
 *     markup's again. Without the script the number simply shows.
 *   • autoplay: a <video> plays by itself, muted and looping. A button
 *     with data-pg-video-toggle (aria-controls naming the video, else the
 *     nearest such video around it) pauses and plays it again and tells
 *     its state with aria-pressed (true while paused). Without the script
 *     or under reduced motion the video stays still on its first frame or
 *     poster; the block rule pg-video-toggle hides the button then.
 *   • chapter: while the node holds the window's middle, the page's body
 *     takes the node's background and text colour, changing over the
 *     duration; it takes its own back once no chapter holds the middle.
 *     A chapter in the page's last strip that cannot reach the middle
 *     takes the page at its end. Under reduced motion the colours change
 *     at once; without the script, and on the editor's canvas, the page
 *     keeps its own colours.
 *   • scene: a section with two or more layers (elements under it marked
 *     data-pg-scene-layer). The script writes the number of layers as
 *     --pg-scene-steps on the section, then, each frame, the step its scroll
 *     has reached as data-pg-scene-step, and marks that step's layer with
 *     data-pg-scene-on. The block's CSS (pg-scene) makes the section that
 *     many windows tall only while data-pg-scene-step is there, pins its
 *     stage and shows the marked layer alone. Without the script, under
 *     reduced motion and on the editor's canvas the layers stand one under
 *     the other.
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
        'scroll-zoom', 'scroll-fade', 'scroll-rise', 'count', 'autoplay', 'chapter', 'scene'];
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
        if (effect === 'autoplay') { o.video = true; return o; }
        if (effect === 'sticky') { o.offset = num('offset'); return o; }
        if (effect === 'parallax') { o.speed = num('speed'); return o; }
        if (FROM[effect]) { o.from = num('from', FROM[effect]); o.scroll = true; return o; }
        if (effect === 'scene') { o.scene = true; return o; }
        if (effect === 'chapter') { o.chapter = true; o.duration = num('duration'); return o; }
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

    // The distance a scroll-rise starts from: its own, or one px less than
    // the `room` the page has left under the node when that is less. A box
    // moved below everything else counts into the page's height, so a rise
    // from further down would make the page that much longer and move its
    // end while the visitor scrolls to it.
    function riseFrom(from, room) {
        return Math.max(0, Math.min(from, Math.floor(room) - 1));
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

    // The chapter the page wears: of the boxes [top, bottom] (in page
    // order) in a window `view` high, the index of the last one that holds
    // the window's middle, or -1 when none does. At the page's end (`end`)
    // a box whose top is in the window's lower half counts too: it cannot
    // scroll any higher.
    function chapterAt(boxes, view, end) {
        var mid = view / 2, at = -1;
        boxes.forEach(function (b, i) {
            if ((b[0] <= mid && b[1] > mid) || (end && b[0] > mid && b[0] < view)) at = i;
        });
        return at;
    }

    // The colours a chapter gives the page, from its computed background
    // and text colour: '' where it has none of its own (a transparent
    // background, the text colour the page already has), so the page
    // keeps its own there.
    function chapterColours(background, color, pageColor) {
        var clear = !background || background === 'transparent' || /^rgba\([^)]*,\s*0(\.0+)?\)$/.test(background);
        return { background: clear ? '' : background, color: color && color !== pageColor ? color : '' };
    }

    // The step (1 to n) a scene with n layers has reached: the scroll
    // through it, from its top at the window's top to its bottom at the
    // window's bottom, cut into n equal parts. A box no taller than the
    // window has only its first step.
    function sceneStep(top, height, view, n) {
        var run = height - view;
        if (n < 2 || run <= 0) return 1;
        var p = Math.max(0, Math.min(1, -top / run));
        return Math.min(n, Math.floor(p * n) + 1);
    }

    window.pgFx = {
        read: read, shift: shift, transition: transition, parallax: parallax,
        progress: progress, scrollState: scrollState, riseFrom: riseFrom, number: number, countText: countText,
        chapterAt: chapterAt, chapterColours: chapterColours, sceneStep: sceneStep
    };

    if (typeof document === 'undefined') {
        return;
    }

    var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var bound = [];
    var floating = [];
    // The chapters, the one the page wears (null: its own colours; false:
    // unknown, the one it wore was drawn again) and the body's own
    // inline colours and transition, kept the first time one is written.
    var chapters = [];
    var painted = null;
    var pageOwn = null;
    var paintMs = 600;
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

    // How far the page is scrolled, and its height.
    function scrolled() {
        return window.pageYOffset || document.documentElement.scrollTop || 0;
    }

    function pageHeight() {
        var root = document.documentElement;
        return Math.max(root.scrollHeight || 0, document.body ? document.body.scrollHeight : 0);
    }

    // How much further the page scrolls: a box's top at the page's end is
    // its top now less that.
    function scrollLeft(view) {
        return Math.max(0, pageHeight() - view - scrolled());
    }

    // The layers of a scene, in page order.
    function sceneLayers(el) {
        return el.querySelectorAll ? Array.prototype.slice.call(el.querySelectorAll('[data-pg-scene-layer]')) : [];
    }

    // The scene at `step`: the number on the section, the mark on that
    // step's layer alone.
    function sceneSet(rec, step) {
        if (rec.step === step) return;
        rec.step = step;
        rec.el.setAttribute('data-pg-scene-step', String(step));
        rec.layers.forEach(function (layer, i) {
            if (i === step - 1) layer.setAttribute('data-pg-scene-on', '');
            else layer.removeAttribute('data-pg-scene-on');
        });
    }

    // The scene back to its layers one under the other.
    function sceneStop(rec) {
        rec.step = 0;
        rec.el.removeAttribute('data-pg-scene-step');
        (rec.layers || []).forEach(function (layer) { layer.removeAttribute('data-pg-scene-on'); });
    }

    // A chapter's colours, read from the page when it is bound.
    function chapterRead(el) {
        var cs = window.getComputedStyle ? window.getComputedStyle(el) : null;
        var page = cs && document.body ? window.getComputedStyle(document.body) : null;
        return cs ? chapterColours(cs.backgroundColor, cs.color, page ? page.color : '') : { background: '', color: '' };
    }

    // The page's body in the chapter's colours, or its own (rec null). The
    // change runs over the coming chapter's duration (the leaving one's
    // when the page takes its own back); at once under reduced motion.
    function paint(rec) {
        var body = document.body;
        if (rec === painted || !body) return;
        var s = body.style;
        if (!pageOwn) pageOwn = { background: s.backgroundColor || '', color: s.color || '', transition: s.transition || '' };
        if (rec) paintMs = rec.o.duration;
        if (!rec && !chapters.length) {
            s.transition = pageOwn.transition;
        } else {
            s.transition = reduced() ? 'none' : 'background-color ' + paintMs + 'ms ease,color ' + paintMs + 'ms ease';
        }
        s.backgroundColor = rec && rec.colours.background ? rec.colours.background : pageOwn.background;
        s.color = rec && rec.colours.color ? rec.colours.color : pageOwn.color;
        painted = rec;
    }

    // The chapter that holds the window's middle paints the page.
    function chapter() {
        var root = document.documentElement;
        var view = window.innerHeight || root.clientHeight;
        var boxes = chapters.map(function (rec) {
            var r = rec.el.getBoundingClientRect();
            return [r.top, r.bottom];
        });
        var at = chapterAt(boxes, view, scrollLeft(view) < 1);
        paint(at === -1 ? null : chapters[at]);
    }

    // One frame of the chapters, parallax and the scroll-linked effects.
    // Every box is measured before any is written, so the page is laid out
    // once. Chapters change colour under reduced motion too, at once.
    function float() {
        queued = false;
        if (chapters.length || painted !== null) chapter();
        if (reduced()) return;
        var root = document.documentElement;
        var view = window.innerHeight || root.clientHeight;
        var at = scrolled();
        var tall = pageHeight();
        var rects = floating.map(function (rec) { return rec.el.getBoundingClientRect(); });
        // A risen node that reaches the page's end may be what makes the
        // page that tall (the page grew shorter under it, or the window
        // changed): it is put back and the page measured again, so `tall`
        // is the page's own height. The one case of a second layout.
        var low = [];
        floating.forEach(function (rec, i) {
            if (rec.o.effect === 'scroll-rise' && rec.y && rects[i].bottom + at > tall - 0.5) low.push(i);
        });
        if (low.length) {
            low.forEach(function (i) {
                put(floating[i].el, { opacity: '', transform: '' });
                floating[i].y = 0;
                floating[i].last = null;
            });
            tall = pageHeight();
            low.forEach(function (i) { rects[i] = floating[i].el.getBoundingClientRect(); });
        }
        var down = Math.max(0, tall - view - at);
        var writes = [];
        floating.forEach(function (rec, i) {
            var r = rects[i];
            // A scene is measured wherever it is, so a fast scroll past it
            // leaves it at its last step.
            if (rec.o.scene) {
                writes.push([rec, 'scene', sceneStep(r.top, r.height, view, rec.layers.length)]);
                return;
            }
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
            // A rise has the room under the node at most, so the page
            // keeps its length.
            var o = rec.o;
            if (o.effect === 'scroll-rise') {
                var from = riseFrom(o.from, tall - at - top - h);
                if (from !== o.from) o = { effect: o.effect, from: from };
            }
            var s = scrollState(o, p);
            // A fade ends at the node's own opacity, so it starts from a
            // part of that.
            var own = parseFloat(base(rec.el).opacity);
            if (s.opacity !== '' && !isNaN(own)) s.opacity = String(Math.round(parseFloat(s.opacity) * own * 1000) / 1000);
            if (rec.last === s.opacity + '|' + s.transform) return;
            rec.last = s.opacity + '|' + s.transform;
            writes.push([rec, o.effect === 'scroll-rise' ? rise(o, p) : 0, s]);
        });
        writes.forEach(function (w) {
            if (w[1] === 'scene') {
                sceneSet(w[0], w[2]);
                return;
            }
            w[0].y = w[1];
            put(w[0].el, w[2]);
            // At its own state the node has its own transition back.
            if (w[0].o.scroll && !w[2].opacity && !w[2].transform) w[0].el.style.transition = base(w[0].el).transition;
        });
    }

    function floatSoon() {
        if (queued || (!floating.length && !chapters.length && painted === null)) return;
        queued = true;
        (window.requestAnimationFrame || window.setTimeout)(float);
    }

    // ── Autoplay video ──────────────────────────────────────────────────
    // The video a pause button works: the one its aria-controls names,
    // else the nearest autoplay video in the boxes around the button.
    function videoOf(btn) {
        var id = btn.getAttribute('aria-controls');
        var v = id ? document.getElementById(id) : null;
        if (v && v.tagName === 'VIDEO') return v;
        for (var box = btn.parentNode; box && box.querySelector; box = box.parentNode) {
            v = box.querySelector('video[data-pg-fx="autoplay"]');
            if (v) return v;
        }
        return null;
    }

    // Every pause button of the video says whether it is paused.
    function videoPressed(v, paused) {
        var list = document.querySelectorAll('[data-pg-video-toggle]');
        for (var i = 0; i < list.length; i++) {
            if (videoOf(list[i]) === v) list[i].setAttribute('aria-pressed', paused ? 'true' : 'false');
        }
    }

    // Plays the video; a browser that refuses (no muted autoplay) leaves
    // it paused, and its button says so.
    function videoPlay(v) {
        videoPressed(v, false);
        var p;
        try { p = v.play(); } catch (e) { videoPressed(v, true); return; }
        if (p && typeof p.then === 'function') p.then(null, function () { videoPressed(v, true); });
    }

    function videoPause(v) {
        if (typeof v.pause === 'function') v.pause();
        videoPressed(v, true);
    }

    function videoStart(rec) {
        var v = rec.el;
        if (v.tagName !== 'VIDEO' || typeof v.play !== 'function') return;
        // Muted and looping whatever the markup says: only such a video
        // may start by itself.
        v.muted = true;
        v.loop = true;
        v.playsInline = true;
        v.setAttribute('playsinline', '');
        if (reduced()) videoPause(v); else videoPlay(v);
    }

    // A press on a pause button, caught before the editor's canvas or a
    // link around it takes the click. A visitor's own press plays the
    // video under reduced motion too.
    function videoToggle(e) {
        var t = e.target, btn = t && t.closest ? t.closest('[data-pg-video-toggle]') : null;
        var v = btn ? videoOf(btn) : null;
        if (!v) return;
        e.preventDefault();
        if (v.paused) videoPlay(v); else videoPause(v);
    }

    function start(rec, later) {
        var el = rec.el, o = rec.o;
        if (o.video) {
            videoStart(rec);
            return;
        }
        if (o.scene) {
            // The canvas keeps the layers one under the other, each one
            // there to edit; the Preview plays the scene.
            if (window.pgFxEditing) return;
            rec.layers = sceneLayers(el);
            if (rec.layers.length < 2) return;
            el.style.setProperty('--pg-scene-steps', String(rec.layers.length));
            if (reduced()) return;
            // The first step makes the section tall before it is measured.
            sceneSet(rec, 1);
            floating.push(rec);
            return;
        }
        if (o.chapter) {
            // The editor's canvas writes its body's style on every render
            // and keeps the page in its own colours while it is edited;
            // the Preview plays the chapters.
            if (window.pgFxEditing) return;
            rec.colours = chapterRead(el);
            chapters.push(rec);
            return;
        }
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
        chapters = chapters.filter(function (rec) { return bound.indexOf(rec) !== -1; });
        // The chapter the page wore was drawn again (the editor's canvas):
        // the next frame finds the one it wears now, with no flash of the
        // page's own colours between.
        if (painted && chapters.indexOf(painted) === -1) painted = false;
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
        floatSoon();
    }

    // Reduced motion turned on while the page is open: every node back to
    // its own state, entrances shown.
    function still() {
        if (!reduced()) return;
        bound.forEach(function (rec) {
            if (rec.o.video) {
                if (rec.el.tagName === 'VIDEO') videoPause(rec.el);
                return;
            }
            if (rec.o.chapter) return;
            if (rec.o.scene) {
                sceneStop(rec);
                return;
            }
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
    document.addEventListener('click', videoToggle, true);
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
