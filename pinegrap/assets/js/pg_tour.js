/**
 * Pinegrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name Pinegrap.
 * The final LiveSite update (2019) has been integrated into Pinegrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A guided tour of a screen: everything dims, a ring lands on one control at a
// time and a bubble beside it says what that control is for.  Next moves the
// ring on; Skip ends it.  It runs once, the first time someone opens the
// screen, and after that only when they ask for it again from the menu.
//
// This file is the engine only, and knows nothing about any particular screen.
// The steps live in the screen that is being explained, because a step needs
// the screen's own language strings and, for a step that has to make something
// happen first, its own functions.  The engine handles the parts every tour
// shares: the cut-out, the ring, the bubble and where it goes, the keyboard,
// what happens when a target is not on screen, and remembering that this
// person has now watched it.
//
// Overlay technique: the dim is a single huge box-shadow spread on the cut-out
// element, so the "hole" is simply the part the shadow does not cover.  One
// element, one transition, and the ring glides from control to control instead
// of blinking between them.  A second, transparent layer sits underneath and
// swallows clicks, because the tour explains the screen rather than driving it:
// a click that reached the page could walk into a folder and leave the next
// step pointing at something that is no longer there.
//
// Registering a tour:
//
//   PgTour.register({
//       key:    'explorer_catalog.1',   // the '.1' is the tour's own version
//       seen:   false,                  // written by the server
//       labels: { next: '…', back: '…', skip: '…', done: '…', of: '{var:1} / {var:2}' },
//       steps:  [ { target: '#id', title: '…', text: '…', placement: 'right' } ]
//   });
//
//   PgTour.autoStart();       // starts the first tour not yet watched, if any
//   PgTour.start('shell.1');  // starts that one whatever has been watched
//
// More than one tour can be registered on a page.  The frame of the software
// registers its own from the header and a screen adds its own on top.  They
// queue rather than overlap: the first one nobody has watched runs, and when
// it is watched to the end the next unwatched one follows it.  Skipping ends
// the queue for this visit.  A tour may carry ready(), and is held back until
// it returns true -- a screen that fills itself in over the network is not
// ready the moment it is parsed.
//
// A step may carry:
//
//   target      css selector, an element, an array of either, or a function
//               returning any of those.  Several targets are rung together as
//               one cut-out around all of them, which is how a step shows an
//               item and the menu it produced at the same time.  Left out, the
//               bubble is centred and nothing is cut out -- which is what a
//               welcome or a closing step wants.
//   placement   'top' | 'right' | 'bottom' | 'left'.  A preference, not an
//               instruction: a side that does not fit is dropped for one that
//               does.
//   padding     how far the ring stands off the control (default 6).
//   radius      corner radius of the cut-out (default 12).
//   before      function (done).  Runs before the step is shown; call done()
//               when ready.  This is where a step opens a menu it wants to
//               point at.
//   after       function ().  Runs when the step is left, in either direction.
//   skipIf      function ().  Return true and the step is passed over -- the
//               way a step about the folder tree has to behave on a phone,
//               where there is no tree on screen.

window.PgTour = (function () {

    'use strict';

    var tours = [];
    var tour = null;
    var steps = [];
    var index = 0;
    var direction = 1;
    var running = false;

    var veil = null;
    var hole = null;
    var bubble = null;
    var parts = null;

    var frame = null;
    var lastFocus = null;

    var GAP = 14;
    var EDGE = 12;

    // ── Small helpers ───────────────────────────────────────────────────

    function reducedMotion() {
        return (window.matchMedia) && (window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // The label strings arrive from lang() and carry the same {var:1}
    // placeholders the rest of the software uses.
    function fill(template, values) {
        var output = String(template === null || template === undefined ? '' : template);

        for (var i = 0; i < values.length; i++) {
            output = output.split('{var:' + (i + 1) + '}').join(values[i]);
        }

        return output;
    }

    // A step may name one thing or several, and several are rung together as a
    // single cut-out around all of them.  That is how a step shows cause and
    // effect: the item that was right-clicked and the menu it produced belong
    // in the same pool of light, or the reader is left looking at a menu with
    // no idea what it came from.
    function resolveAll(target) {

        if (!target) { return []; }

        if (typeof target === 'function') { target = target(); }

        var list = (Object.prototype.toString.call(target) === '[object Array]') ? target : [target];
        var found = [];

        for (var i = 0; i < list.length; i++) {

            var element = list[i];

            if (typeof element === 'string') { element = document.querySelector(element); }

            if ((element) && (element.nodeType === 1) && (visible(element))) { found.push(element); }
        }

        return found;
    }

    // The rectangle that holds all of them, in viewport coordinates.
    function unionRect(elements) {

        var left = Infinity;
        var top = Infinity;
        var right = -Infinity;
        var bottom = -Infinity;

        for (var i = 0; i < elements.length; i++) {

            var rect = elements[i].getBoundingClientRect();

            if (rect.left < left) { left = rect.left; }
            if (rect.top < top) { top = rect.top; }
            if (rect.right > right) { right = rect.right; }
            if (rect.bottom > bottom) { bottom = rect.bottom; }
        }

        return { left: left, top: top, right: right, bottom: bottom, width: right - left, height: bottom - top };
    }

    // On screen and big enough to point at.  A control inside a closed pane, a
    // sidebar hidden by a media query and a button that has not been switched
    // on yet all fail this, and their step is passed over rather than leaving
    // the ring sitting in a corner around nothing.
    function visible(element) {
        if (!element) { return false; }
        if (!element.getClientRects().length) { return false; }

        var rect = element.getBoundingClientRect();

        if ((rect.width < 4) || (rect.height < 4)) { return false; }

        // Parked off screen counts as not visible.  Below the large breakpoint
        // the left menu is not hidden but pushed out with
        // translateX(-100%), and it still reports a box out there: a ring
        // drawn around it lands where nobody can see it, and the bubble is
        // shoved into a corner pointing at nothing.
        if ((rect.bottom <= 0) || (rect.right <= 0)) { return false; }
        if ((rect.top >= (window.innerHeight || document.documentElement.clientHeight))) { return false; }
        if ((rect.left >= (window.innerWidth || document.documentElement.clientWidth))) { return false; }

        var style = window.getComputedStyle(element);

        return (style.visibility !== 'hidden') && (style.display !== 'none') && (parseFloat(style.opacity) > .05);
    }

    // ── Chrome ──────────────────────────────────────────────────────────

    function build() {

        if (veil) { return; }

        veil = document.createElement('div');
        veil.className = 'pg-tour-veil';
        veil.setAttribute('role', 'presentation');

        hole = document.createElement('div');
        hole.className = 'pg-tour-hole';
        veil.appendChild(hole);

        bubble = document.createElement('div');
        bubble.className = 'pg-tour-bubble';
        bubble.setAttribute('role', 'dialog');
        bubble.setAttribute('aria-modal', 'true');
        bubble.setAttribute('aria-labelledby', 'pg_tour_title');
        bubble.innerHTML =
            '<span class="pg-tour-arrow" aria-hidden="true"></span>' +
            '<div class="pg-tour-count" data-part="count"></div>' +
            '<h2 class="pg-tour-title" id="pg_tour_title" data-part="title"></h2>' +
            '<div class="pg-tour-text" data-part="text"></div>' +
            '<div class="pg-tour-foot">' +
                '<button type="button" class="pg-tour-btn pg-tour-ghost" data-part="skip"></button>' +
                '<span class="pg-tour-dots" data-part="dots" aria-hidden="true"></span>' +
                '<button type="button" class="pg-tour-btn pg-tour-ghost" data-part="back"></button>' +
                '<button type="button" class="pg-tour-btn pg-tour-primary" data-part="next"></button>' +
            '</div>';

        parts = {};

        bubble.querySelectorAll('[data-part]').forEach(function (element) {
            parts[element.getAttribute('data-part')] = element;
        });

        parts.arrow = bubble.querySelector('.pg-tour-arrow');

        parts.skip.addEventListener('click', function () { finish(false); });
        parts.back.addEventListener('click', function () { move(-1); });
        parts.next.addEventListener('click', function () { move(1); });

        // The veil is what keeps the tour a tour: a click on the page behind it
        // could change the very thing the next step points at.
        veil.addEventListener('click', function (event) { event.preventDefault(); event.stopPropagation(); });
        veil.addEventListener('contextmenu', function (event) { event.preventDefault(); });

        document.body.appendChild(veil);
        document.body.appendChild(bubble);
    }

    function onKey(event) {

        if (!running) { return; }

        if (event.key === 'Escape') { event.preventDefault(); finish(false); return; }
        if ((event.key === 'ArrowRight') || (event.key === 'Enter')) { event.preventDefault(); move(1); return; }
        if (event.key === 'ArrowLeft') { event.preventDefault(); move(-1); return; }

        // Tab stays inside the bubble while the tour is up; there is nothing
        // else on the screen a keyboard should be able to reach.
        if (event.key === 'Tab') {
            var focusable = bubble.querySelectorAll('button:not([disabled])');

            if (!focusable.length) { return; }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if ((event.shiftKey) && (document.activeElement === first)) { event.preventDefault(); last.focus(); }
            else if ((!event.shiftKey) && (document.activeElement === last)) { event.preventDefault(); first.focus(); }
        }
    }

    function onReflow() {
        if (!running) { return; }
        if (frame !== null) { return; }

        frame = window.requestAnimationFrame(function () {
            frame = null;
            paint();
        });
    }

    // ── Placement ───────────────────────────────────────────────────────

    // Pick the first side the bubble actually fits on, preferred side first.
    // A bubble that hangs off the screen is worse than one on the "wrong" side.
    function placeBubble(rect, preferred) {

        var width = bubble.offsetWidth;
        var height = bubble.offsetHeight;
        var viewportWidth = document.documentElement.clientWidth;
        var viewportHeight = document.documentElement.clientHeight;

        var order = ['bottom', 'top', 'right', 'left'];

        if (preferred) { order = [preferred].concat(order.filter(function (side) { return side !== preferred; })); }

        var chosen = null;

        for (var i = 0; i < order.length; i++) {

            var side = order[i];
            var fits =
                (side === 'bottom') ? ((rect.bottom + GAP + height) <= (viewportHeight - EDGE)) :
                (side === 'top') ? ((rect.top - GAP - height) >= EDGE) :
                (side === 'right') ? ((rect.right + GAP + width) <= (viewportWidth - EDGE)) :
                                     ((rect.left - GAP - width) >= EDGE);

            if (fits) { chosen = side; break; }
        }

        // Nothing fits -- a control that fills the screen, or a small screen.
        // The bubble then sits over the target rather than beside it, which at
        // least keeps every word readable.
        if (!chosen) {
            bubble.setAttribute('data-side', 'none');
            bubble.style.left = Math.round((viewportWidth - width) / 2) + 'px';
            bubble.style.top = Math.round((viewportHeight - height) / 2) + 'px';
            parts.arrow.style.display = 'none';
            return;
        }

        var left;
        var top;

        if ((chosen === 'bottom') || (chosen === 'top')) {
            left = rect.left + (rect.width / 2) - (width / 2);
            top = (chosen === 'bottom') ? (rect.bottom + GAP) : (rect.top - GAP - height);
        } else {
            left = (chosen === 'right') ? (rect.right + GAP) : (rect.left - GAP - width);
            top = rect.top + (rect.height / 2) - (height / 2);
        }

        left = Math.max(EDGE, Math.min(left, viewportWidth - width - EDGE));
        top = Math.max(EDGE, Math.min(top, viewportHeight - height - EDGE));

        bubble.setAttribute('data-side', chosen);
        bubble.style.left = Math.round(left) + 'px';
        bubble.style.top = Math.round(top) + 'px';

        // The arrow points back at the middle of the target, not at the middle
        // of the bubble: the two only line up when nothing had to be clamped.
        parts.arrow.style.display = '';

        if ((chosen === 'bottom') || (chosen === 'top')) {
            var arrowX = rect.left + (rect.width / 2) - left;
            parts.arrow.style.left = Math.max(14, Math.min(arrowX, width - 14)) + 'px';
            parts.arrow.style.top = '';
        } else {
            var arrowY = rect.top + (rect.height / 2) - top;
            parts.arrow.style.top = Math.max(14, Math.min(arrowY, height - 14)) + 'px';
            parts.arrow.style.left = '';
        }
    }

    function paint() {

        var step = steps[index];

        if (!step) { return; }

        var elements = resolveAll(step.target);

        if (elements.length) {

            var padding = (step.padding === undefined) ? 6 : step.padding;
            var rect = unionRect(elements);

            var box = {
                left: Math.max(0, rect.left - padding),
                top: Math.max(0, rect.top - padding),
                width: rect.width + (padding * 2),
                height: rect.height + (padding * 2)
            };

            box.right = box.left + box.width;
            box.bottom = box.top + box.height;

            hole.style.display = '';
            hole.style.left = Math.round(box.left) + 'px';
            hole.style.top = Math.round(box.top) + 'px';
            hole.style.width = Math.round(box.width) + 'px';
            hole.style.height = Math.round(box.height) + 'px';
            hole.style.borderRadius = ((step.radius === undefined) ? 12 : step.radius) + 'px';

            placeBubble(box, step.placement);

        } else {

            // A centred step: no cut-out, the whole screen simply dims.  The
            // dim still has to be painted, and the cut-out element is what
            // paints it, so it is parked off screen rather than hidden.
            hole.style.display = '';
            hole.style.width = '0px';
            hole.style.height = '0px';
            hole.style.left = '0px';
            hole.style.top = '0px';
            hole.style.top = '0px';

            var viewportWidth = document.documentElement.clientWidth;
            var viewportHeight = document.documentElement.clientHeight;

            bubble.setAttribute('data-side', 'none');
            bubble.style.left = Math.round((viewportWidth - bubble.offsetWidth) / 2) + 'px';
            bubble.style.top = Math.round((viewportHeight - bubble.offsetHeight) / 2) + 'px';
            parts.arrow.style.display = 'none';
        }
    }

    // ── Steps ───────────────────────────────────────────────────────────

    // Two questions, asked at two different moments.  A step may rule itself
    // out before anything is done for it -- a step about the folder tree has
    // to on a phone, where there is no tree -- and that answer is cheap.
    // Whether its target is on the screen can only be asked after before() has
    // run, because a step that points at a menu is the one that opens it.
    function ruledOut(step) {

        if (!step) { return true; }

        try {
            return ((typeof step.skipIf === 'function') && (step.skipIf() === true));
        } catch (error) { return true; }
    }

    function targetReady(step) {

        if (!step) { return false; }

        // A step with no target is a centred one: nothing is cut out and the
        // whole screen simply dims.
        if (!step.target) { return true; }

        return (resolveAll(step.target).length > 0);
    }

    function show() {

        var step = steps[index];

        if (!step) { finish(true); return; }

        var labels = tour.labels || {};
        var total = steps.length;

        parts.count.textContent = fill(labels.of || '{var:1} / {var:2}', [index + 1, total]);
        parts.title.textContent = step.title || '';
        parts.text.innerHTML = step.html ? step.html : escapeHtml(step.text || '').split('\n').join('<br>');
        parts.skip.textContent = labels.skip || 'Skip';
        parts.back.textContent = labels.back || 'Back';
        parts.next.textContent = (index === (total - 1)) ? (labels.done || 'Done') : (labels.next || 'Next');

        parts.back.style.visibility = (index === 0) ? 'hidden' : '';

        var dots = '';

        for (var i = 0; i < total; i++) {
            dots += '<i class="pg-tour-dot' + ((i === index) ? ' is-on' : '') + '"></i>';
        }

        parts.dots.innerHTML = dots;

        var elements = resolveAll(step.target);

        var reveal = function () {
            paint();
            parts.next.focus();
        };

        // Bring the control into view first, then measure.  Measuring during a
        // smooth scroll puts the ring where the control used to be.  Where a
        // step names several things, the first is the one worth scrolling to:
        // it is the cause, and what it produced was put beside it.
        if (elements.length) {

            try {
                elements[0].scrollIntoView({ block: 'center', inline: 'nearest', behavior: reducedMotion() ? 'auto' : 'smooth' });
            } catch (error) {
                elements[0].scrollIntoView();
            }

            window.setTimeout(reveal, reducedMotion() ? 0 : 300);

        } else {
            reveal();
        }
    }

    function leave() {

        var step = steps[index];

        if ((step) && (typeof step.after === 'function')) {
            try { step.after(); } catch (error) { }
        }
    }

    function enter(done) {

        var step = steps[index];

        if ((step) && (typeof step.before === 'function')) {

            var called = false;
            var once = function () { if (called) { return; } called = true; done(); };

            try {
                step.before(once);
            } catch (error) { once(); }

            // A before() that never calls back must not strand the tour.
            window.setTimeout(once, 1200);

            return;
        }

        done();
    }

    function move(step) {

        if (!running) { return; }

        leave();

        direction = (step < 0) ? -1 : 1;

        goTo(index + direction);
    }

    // Land on a step, walking past anything that cannot be shown right now in
    // whichever direction we were already going.  before() runs first and its
    // work is undone by after() if the step turns out to be unshowable after
    // all, so a step that opens a menu never leaves that menu hanging open.
    function goTo(next) {

        if (next < 0) { next = 0; }

        if (next >= steps.length) { finish(true); return; }

        if (ruledOut(steps[next])) { goTo(next + direction); return; }

        index = next;

        enter(function () {

            if (!targetReady(steps[index])) {
                leave();
                goTo(index + direction);
                return;
            }

            show();
        });
    }

    // ── Start and finish ────────────────────────────────────────────────

    function finish(completed) {

        if (!running) { return; }

        leave();

        running = false;

        window.removeEventListener('keydown', onKey, true);
        window.removeEventListener('resize', onReflow);
        window.removeEventListener('scroll', onReflow, true);

        if (frame !== null) { window.cancelAnimationFrame(frame); frame = null; }

        document.body.classList.remove('pg-tour-open');

        if (veil) { veil.classList.remove('is-on'); }
        if (bubble) { bubble.classList.remove('is-on'); }

        window.setTimeout(function () {
            if (running) { return; }
            if (veil) { veil.style.display = 'none'; }
            if (bubble) { bubble.style.display = 'none'; }
        }, 200);

        if (lastFocus) { try { lastFocus.focus(); } catch (error) { } lastFocus = null; }

        // Watched to the end or skipped, it is the same answer to the only
        // question being stored: do not open this by itself again.
        remember();

        if ((tour) && (typeof tour.onEnd === 'function')) {
            try { tour.onEnd(completed); } catch (error) { }
        }

        // A page can carry more than one tour -- the frame of the software and
        // the screen sitting inside it.  They queue rather than overlap: the
        // next one this person has not watched follows the one that just
        // ended, after a pause long enough that the two do not read as one
        // long tour.
        //
        // Only after it was watched to the end, though.  Somebody who has just
        // skipped a tour did not mean "show me the next one instead", and
        // answering a skip with another tour is the surest way to teach people
        // to skip everything.
        if (completed) {

            window.setTimeout(function () { beginFirstUnseen(0); }, 900);
        }
    }

    function remember() {

        if ((!tour) || (!tour.key)) { return; }

        tour.seen = true;

        // Either name: a tour registered straight from the server carries the
        // session token as "token", one a screen assembles for itself as
        // "saveToken".  Reading only one of the two meant a whole tour silently
        // never recorded itself and greeted its reader on every visit.
        var token = tour.saveToken || tour.token;

        if (!token) { return; }

        try {
            var request = new XMLHttpRequest();
            request.open('POST', tour.saveUrl || 'api.php', true);
            request.setRequestHeader('Content-Type', 'application/json');
            request.send(JSON.stringify({ action: 'tour_seen', token: token, key: tour.key }));
        } catch (error) { }
    }

    // The first tour nobody has watched yet, in the order they were registered.
    // The frame of the software is registered before any one screen inside it,
    // so somebody seeing the software for the first time is told what the left
    // menu and the top bar are before being walked around a file manager.  Only
    // one runs per visit: two in a row is a lecture, not a tour.
    function beginFirstUnseen(waited) {

        if (running) { return; }

        var next = null;

        for (var i = 0; i < tours.length; i++) {

            if (tours[i].seen !== true) { next = tours[i]; break; }
        }

        if (!next) { return; }

        // A tour may not be worth starting until its screen has filled itself
        // in: a step that opens the menu on the first item needs there to be a
        // first item, and that arrives over the network.  Waited out rather
        // than raced, and given up on rather than waited for forever.
        if ((typeof next.ready === 'function') && (next.ready() !== true)) {

            if (waited >= 4000) { return; }

            window.setTimeout(function () { beginFirstUnseen(waited + 150); }, 150);

            return;
        }

        begin(next);
    }

    function begin(config) {

        if (running) { return; }

        tour = config || null;

        if ((!tour) || (!tour.steps) || (!tour.steps.length)) { return; }

        build();

        steps = tour.steps.slice();
        index = 0;
        direction = 1;

        // Nothing on this screen can be pointed at -- a phone, or a screen that
        // has not finished loading.  Nothing is shown and, because finish() is
        // never reached, nothing is remembered either: the tour is still there
        // the next time.
        var anything = false;

        for (var i = 0; i < steps.length; i++) {
            if (!ruledOut(steps[i])) { anything = true; break; }
        }

        if (!anything) { return; }

        running = true;
        lastFocus = document.activeElement;

        document.body.classList.add('pg-tour-open');

        veil.style.display = '';
        bubble.style.display = '';

        // One frame between display and the class, so the fade actually runs.
        window.requestAnimationFrame(function () {
            veil.classList.add('is-on');
            bubble.classList.add('is-on');
        });

        window.addEventListener('keydown', onKey, true);
        window.addEventListener('resize', onReflow);
        window.addEventListener('scroll', onReflow, true);

        goTo(0);
    }

    function find(key) {

        for (var i = 0; i < tours.length; i++) {

            if (tours[i].key === key) { return tours[i]; }
        }

        return null;
    }

    return {

        // More than one tour can be registered on the same page: the frame of
        // the software registers its own from the header, and the screen inside
        // it adds its own on top.  Registering the same key twice replaces it
        // rather than queueing it a second time.
        register: function (config) {

            if ((!config) || (!config.key)) { return; }

            for (var i = 0; i < tours.length; i++) {

                if (tours[i].key === config.key) { tours[i] = config; return; }
            }

            tours.push(config);
        },

        // The menu item: watched already or not, run this one.  Called without
        // a key it runs the first registered tour, which is the frame.
        start: function (key) {

            var wanted = key ? find(key) : (tours.length ? tours[0] : null);

            if (wanted) { begin(wanted); }
        },

        // The visit: run the first tour this person has not watched, if any.
        // Safe to call from more than one place -- the screen calls it once its
        // own content has arrived, the header calls it once the page is parsed,
        // and whichever gets there first is answering the same question.
        autoStart: function (delay) {

            window.setTimeout(function () { beginFirstUnseen(0); }, (delay === undefined) ? 400 : delay);
        },

        stop: function () {
            finish(false);
        },

        running: function () {
            return running;
        }
    };

})();
