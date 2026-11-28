/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the first tour part: the card "New here? A one-minute
 * tour" in the window's lower right corner, offered once per person and
 * browser, and the tour it starts: a ring round each real control and a
 * bubble beside it with one sentence, Skip, Back and Next. Nothing is dimmed
 * or locked; the stops, their order, the skipping of a control that is not
 * on the screen and what was seen come from window.PGDesignerTour
 * (pg_designer_tour.js, the part's first source). Under 576 px the card and
 * the bubble are a sheet across the window's foot (or top).
 *
 * It also draws the Help window's "How do I…" rows and its "Tips" switch,
 * a task's three pointers (the tour's ring and bubble), the one-line
 * first-use tip a feature asks for, and an empty page's starts.
 *
 * Not needed to open a page: the core runs this file when the card is due
 * (_sdTourInit()), when the Help window opens, when "Take the tour" is
 * chosen, when a first-use tip is due or when a page is empty. While none
 * of them is up it holds no listener or timer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('tour', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var M = window.PGDesignerTour;

    // Under this width the card and the bubble are a sheet.
    var PHONE = 576;
    // The ring stands this far outside the control's box.
    var RING_GAP = 3;
    // The space between the ring and the bubble's arrow, and the window's
    // edge the bubble keeps clear of.
    var GAP = 12;
    var EDGE = 8;
    var BUBBLE_W = 320;
    // How often a waiting card looks for a quiet moment, and how often a
    // running tour looks whether its control moved or a dialog opened.
    var WAIT_MS = 1500;
    var FOLLOW_MS = 400;
    // After a press on a pointer's control: how long its own work is given,
    // and how often the next pointer's control is looked for.
    var PRESS_MS = 150;
    var WAIT_TRIES = 8;
    // Under this width of canvas an empty page's starts stand in a column.
    var NARROW_CANVAS = 620;

    // Each stop's sentence: its bold first words, then the rest.
    function sentence(id) {
        switch (id) {
            case 'add': return [_sdT('Add something.'), _sdT('Everything you can put on a page is here. Drag one onto the page, or click it.')];
            case 'shape': return [_sdT('Shape it.'), _sdT('Click anything on the page, then change how it looks here.')];
            case 'see': return [_sdT('See it.'), _sdT('Look at the page as a visitor will, on a phone or a wide screen.')];
            case 'publish': return [_sdT('Put it on the site.'), _sdT('Nothing reaches your site until you press Publish.')];
            case 'find': return [_sdT('Find anything.'), _sdT('Ctrl+Shift+P finds every command by its name. Help lives under this button.')];
            case 'change': return [_sdT('What you may change.'), _sdT('The framed parts of this page are yours. Click one.')];
            case 'edit': return [_sdT('How.'), _sdT('Change its text, picture or link here, or type right on the page.')];
            case 'send': return [_sdT('How it reaches the site.'), _sdT('Press this when you are done. If your site asks for approval, a designer looks first.')];
        }
        return ['', ''];
    }

    // A "How do I…" task's name, as its row and its pointers' bold words
    // say it, and its row's icon.
    function taskName(id) {
        switch (id) {
            case 'section': return _sdT('Add a section');
            case 'text': return _sdT('Change a text or a picture');
            case 'colours': return _sdT('Change the colours');
            case 'phone': return _sdT('Make it fit a phone');
            case 'page': return _sdT('Add a page');
            case 'list': return _sdT('Show a list of records');
            case 'form': return _sdT('Add a form');
            case 'shared': return _sdT('Use one part on every page');
            case 'history': return _sdT('Go back to an earlier version');
            case 'publish': return _sdT('Publish');
        }
        return '';
    }

    var TASK_ICONS = {
        section: 'bi-plus-square', text: 'bi-fonts', colours: 'bi-palette', phone: 'bi-phone',
        page: 'bi-file-earmark-plus', list: 'bi-list-ul', form: 'bi-ui-checks', shared: 'bi-puzzle',
        history: 'bi-clock-history', publish: 'bi-rocket-takeoff'
    };

    // A pointer's sentence: one short sentence on its control.
    function pointerText(id) {
        switch (id) {
            case 'section.1': return _sdT('Open “Blocks” here.');
            case 'section.2': return _sdT('Drag a block onto the page, or click it.');
            case 'section.3': return _sdT('It lands where the line shows. Click its words to write your own.');
            case 'text.1': return _sdT('Click the words or the picture you want to change.');
            case 'text.2': return _sdT('Its words and its link are under “Content”.');
            case 'text.3': return _sdT('Pick another picture here, or upload one.');
            case 'colours.1': return _sdT('Open “Design” beside Options.');
            case 'colours.2': return _sdT('“Themes” holds the colours of the whole design.');
            case 'colours.3': return _sdT('Every page shows the new colours at once.');
            case 'phone.1': return _sdT('Pick a phone’s width here.');
            case 'phone.2': return _sdT('The page shows as a phone shows it. Change what does not fit.');
            case 'phone.3': return _sdT('Look at it on a real phone from here.');
            case 'page.1': return _sdT('Press “Add Page”, then “New Page”.');
            case 'page.2': return _sdT('The new page opens in a tab of its own.');
            case 'page.3': return _sdT('Rename, copy or set it up from the tab’s menu.');
            case 'list.1': return _sdT('Open “System” here.');
            case 'list.2': return _sdT('Drag “Form List View” onto the page.');
            case 'list.3': return _sdT('Choose here which records it shows.');
            case 'form.1': return _sdT('Open “System” here.');
            case 'form.2': return _sdT('Drag “Custom Form” onto the page.');
            case 'form.3': return _sdT('Choose its fields and where the answers go here.');
            case 'shared.1': return _sdT('Right-click its row here, then “Convert to Shared Component”.');
            case 'shared.2': return _sdT('Every shared component waits here.');
            case 'shared.3': return _sdT('Drag it onto any page. A change to it shows on every page.');
            case 'history.1': return _sdT('Undo takes back the last change.');
            case 'history.2': return _sdT('Open “History” for every save and snapshot.');
            case 'history.3': return _sdT('Open one to look at it, or bring it back.');
            case 'publish.1': return _sdT('Look at the page as a visitor will first.');
            case 'publish.2': return _sdT('Press this to put the page on the site.');
            case 'publish.3': return _sdT('The page’s tab says whether it is still a draft.');
        }
        return '';
    }

    // card: the card's element while it is up; waitTimer: a card waiting
    // for a quiet moment. tour: the running tour, else null.
    var S = { card: null, waitTimer: 0, held: false, tour: null, docs: [], tip: null, empty: null };

    function access() {
        return K._sdAccess();
    }

    function storageKey() {
        return M.key(K.design && K.design.userId);
    }

    function load() {
        var raw = null;
        try { raw = window.localStorage.getItem(storageKey()); } catch (e) { raw = null; }
        return M.read(raw);
    }

    // Writes the state; false when the browser refused it (storage full,
    // writes blocked), as nothing then remembers it at the next visit.
    function save(state) {
        var k = storageKey();
        if (!k) return false;
        try { window.localStorage.setItem(k, M.write(state)); } catch (e) { return false; }
        return true;
    }

    function reduced() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function phone() {
        return window.innerWidth < PHONE;
    }

    // The control `sel` as a person sees it: { el, box, scroll } (the model's
    // visible()), null when it is not drawn or has no size. Its ancestors
    // that cut what overflows them (a panel, its scrolling list) cut it too.
    function sight(sel) {
        var el = document.querySelector(sel);
        if (!el || !el.getClientRects().length) return null;
        var r = el.getBoundingClientRect();
        if (!(r.width > 0 && r.height > 0)) return null;
        var clips = [];
        for (var p = el.parentElement; p && p !== document.body; p = p.parentElement) {
            var cs = window.getComputedStyle(p);
            var ox = cs.overflowX, oy = cs.overflowY;
            if (ox === 'visible' && oy === 'visible') continue;
            var pr = p.getBoundingClientRect();
            clips.push({
                left: pr.left, top: pr.top, right: pr.right, bottom: pr.bottom,
                scroll: /auto|scroll/.test(ox + ' ' + oy)
            });
        }
        var v = M.visible({ left: r.left, top: r.top, right: r.right, bottom: r.bottom }, clips, window.innerWidth, window.innerHeight);
        return { el: el, box: v.box, scroll: v.scroll };
    }

    // Whether the control `sel` is on the screen now, or below the fold of
    // a list a scroll brings it in from.
    function has(sel) {
        var v = sight(sel);
        return !!(v && (v.box || v.scroll));
    }

    function own(el) {
        return !!(el && el.closest && el.closest('.sd-tour-card, .sd-tour-bubble'));
    }

    // A dialog of the editor is open (never the tour's own bubble).
    function dialogOpen() {
        var list = document.querySelectorAll('dialog[open], [aria-modal="true"]');
        for (var i = 0; i < list.length; i++) {
            if (!own(list[i]) && list[i].getClientRects().length) return true;
        }
        return false;
    }

    function typingIn(doc) {
        var a = doc && doc.activeElement;
        if (!a || own(a)) return false;
        if (a.isContentEditable) return true;
        var tag = String(a.tagName || '').toLowerCase();
        if (tag === 'textarea' || tag === 'select') return true;
        if (tag !== 'input') return false;
        return !/^(button|submit|reset|checkbox|radio|range|color|file|image)$/i.test(a.type || '');
    }

    // A menu is open (the Preview menu, a button whose list is open). A
    // listbox is not one: many stay drawn in the panels.
    function menuOpen() {
        var list = document.querySelectorAll('[aria-haspopup][aria-expanded="true"], [role="menu"]:not([hidden])');
        for (var i = 0; i < list.length; i++) {
            if (!own(list[i]) && list[i].getClientRects().length) return true;
        }
        return false;
    }

    function busy() {
        return { dialog: dialogOpen(), typing: typingIn(document) || typingIn(K.canvasDoc), dragging: S.held };
    }

    // Escape is the tour's only when no dialog, menu or typed field takes it.
    function escapeIsOurs() {
        var b = busy();
        b.menu = menuOpen();
        return M.escapes(b);
    }

    // While the card waits: a held pointer or a drag counts as dragging.
    function onDown() { S.held = true; }
    function onUp() { S.held = false; }

    function holdWatch(on) {
        var docs = on ? [document, K.canvasDoc].filter(Boolean) : S.docs;
        docs.forEach(function (d) {
            var f = on ? 'addEventListener' : 'removeEventListener';
            d[f]('pointerdown', onDown, true);
            d[f]('pointerup', onUp, true);
            d[f]('pointercancel', onUp, true);
            d[f]('dragstart', onDown, true);
            d[f]('dragend', onUp, true);
            d[f]('drop', onUp, true);
        });
        S.docs = on ? docs : [];
        if (!on) S.held = false;
    }

    // The element the card and the bubble follow in the keyboard order: the
    // page bar, so Tab reaches them after it.
    function placeAfterBar(el) {
        var bar = document.querySelector('.sd-wrapper > .sd-toolbar');
        if (bar && bar.parentNode) bar.parentNode.insertBefore(el, bar.nextSibling);
        else document.body.appendChild(el);
    }

    // The bottom edge the card stands on: 16 px above the status bar.
    function footInset() {
        var bar = document.getElementById('sd-statusbar');
        var top = bar && bar.getClientRects().length ? bar.getBoundingClientRect().top : window.innerHeight;
        return Math.max(0, window.innerHeight - top);
    }

    // ── The first card (board T1) ─────────────────────────────────────────

    function offer() {
        if (S.card || S.tour || S.waitTimer) return false;
        var state = load();
        if (!storageKey()) return false;
        if (state.offered || state.seen[access() === 'content' ? 'content' : 'full']) return false;
        holdWatch(true);
        tryShow();
        return true;
    }

    function tryShow() {
        S.waitTimer = 0;
        if (S.card || S.tour) { holdWatch(false); return; }
        var state = load();
        if (M.offer(state, access(), busy())) {
            holdWatch(false);
            // A browser that keeps nothing would bring the card back at
            // every visit: without the state kept, no card.
            if (save(M.offered(state))) showCard();
            return;
        }
        // Offered or seen in another tab meanwhile: nothing to wait for.
        if (!M.offer(state, access(), {})) { holdWatch(false); return; }
        S.waitTimer = setTimeout(tryShow, WAIT_MS);
    }

    function showCard() {
        var content = access() === 'content';
        // A narrow window has no palette on the screen: the tour skips its
        // stop, and the card promises the four the tour will show.
        var fewer = !content && M.shown(M.stops('full'), has).length < 5;
        var card = document.createElement('div');
        card.className = 'sd-tour-card' + (phone() ? ' is-sheet' : '') + (reduced() ? '' : ' is-fading');
        card.setAttribute('role', 'region');
        card.setAttribute('aria-label', _sdT('New here? A one-minute tour'));
        card.innerHTML =
            '<span class="sd-tour-card-ic bi bi-signpost-2" aria-hidden="true"></span>' +
            '<div class="sd-tour-card-body">' +
                '<div class="sd-tour-card-title" role="status">' + esc(_sdT('New here? A one-minute tour')) + '</div>' +
                '<div class="sd-tour-card-line">' + esc(content ? _sdT('Three stops on what you can change.') : (fewer ? _sdT('Four stops on the real buttons.') : _sdT('Five stops on the real buttons.'))) + '</div>' +
                '<div class="sd-tour-btns">' +
                    '<button type="button" class="sd-btn sd-btn-primary" data-sd-tour="start">' + esc(_sdT('Start')) + '</button>' +
                    '<button type="button" class="sd-btn" data-sd-tour="later">' + esc(_sdT('Not now')) + '</button>' +
                '</div>' +
            '</div>';
        if (!phone()) card.style.bottom = (footInset() + 16) + 'px';
        card.addEventListener('click', function (e) {
            var b = e.target.closest('[data-sd-tour]');
            if (!b) return;
            if (b.getAttribute('data-sd-tour') === 'start') start();
            else closeCard();
        });
        placeAfterBar(card);
        S.card = card;
        document.addEventListener('keydown', onCardKey, true);
        window.addEventListener('resize', onCardResize);
        if (card.classList.contains('is-fading')) {
            requestAnimationFrame(function () { requestAnimationFrame(function () { card.classList.add('is-in'); }); });
        }
    }

    // The Escape the card or the tour takes is theirs alone: the editor's
    // own Escape (clear the selection, close the Prototype view) would
    // otherwise run on the same press.
    function ownEscape(e) {
        e.preventDefault();
        if (e.stopPropagation) e.stopPropagation();
    }

    function onCardKey(e) {
        if (e.key === 'Escape' && escapeIsOurs()) { ownEscape(e); closeCard(); }
    }

    function onCardResize() {
        if (!S.card) return;
        S.card.classList.toggle('is-sheet', phone());
        S.card.style.bottom = phone() ? '' : (footInset() + 16) + 'px';
    }

    function closeCard() {
        if (S.waitTimer) { clearTimeout(S.waitTimer); S.waitTimer = 0; }
        holdWatch(false);
        if (!S.card) return;
        document.removeEventListener('keydown', onCardKey, true);
        window.removeEventListener('resize', onCardResize);
        if (S.card.parentNode) S.card.parentNode.removeChild(S.card);
        S.card = null;
    }

    // ── The tour (boards T2, T3, T7) ──────────────────────────────────────

    // The tour from its first stop, or with `taskId` a "How do I…" task's
    // three pointers on the same ring and bubble. A task started while
    // another runs takes its place; the tour asked again while it runs
    // stays where it is.
    function start(taskId) {
        var back = document.activeElement;
        if (own(back)) back = null;
        closeCard();
        var how = '';
        var list;
        if (typeof taskId === 'string' && taskId) {
            var task = M.tasks(access()).filter(function (x) { return x.id === taskId; })[0];
            if (!task) return false;
            how = task.id;
            list = task.pointers;
        } else {
            list = M.stops(access());
        }
        if (S.tour) {
            if (!how && !S.tour.how) return true;
            if (!back) back = S.tour.back;
            teardown();
        }
        tipHide();
        var at = M.first(list, has);
        if (!at) { if (!how) finish(); return false; }
        var t = S.tour = {
            list: list, at: at, back: back, hidden: false, timer: 0, key: '', how: how, docs: [],
            sig: '', wait: -1, scrolled: -1, pending: false, spoken: '',
            ring: document.createElement('div'), bubble: document.createElement('div')
        };
        t.ring.className = 'sd-tour-ring' + (reduced() ? '' : ' is-gliding');
        t.ring.setAttribute('aria-hidden', 'true');
        document.body.appendChild(t.ring);
        t.bubble.className = 'sd-tour-bubble';
        t.bubble.setAttribute('role', 'dialog');
        t.bubble.setAttribute('aria-modal', 'false');
        t.bubble.setAttribute('aria-label', how ? _sdT('How do I') : _sdT('Tour'));
        t.bubble.addEventListener('click', onBubbleClick);
        t.bubble.addEventListener('keydown', onBubbleKey);
        placeAfterBar(t.bubble);
        document.addEventListener('keydown', onTourKey, true);
        window.addEventListener('resize', follow);
        t.timer = setInterval(follow, FOLLOW_MS);
        if (how) pressWatch(t, true);
        draw(true);
        return true;
    }

    // A pointer shows where and never does the step: pressing the ringed
    // control (a click, or the start of a drag from it) moves to the next
    // pointer once the press has done what it does.
    function pressWatch(t, on) {
        var docs = on ? [document, K.canvasDoc].filter(Boolean) : t.docs;
        docs.forEach(function (d) {
            var f = on ? 'addEventListener' : 'removeEventListener';
            d[f]('click', onPress, true);
            d[f]('dragstart', onPress, true);
        });
        t.docs = on ? docs : [];
    }

    function onPress(e) {
        var t = S.tour;
        if (!t || !t.how || t.hidden || own(e.target)) return;
        var el = document.querySelector(t.at.anchor);
        var doc = e.target && e.target.ownerDocument;
        if (!el) return;
        var inside = doc === document
            ? el.contains(e.target)
            : (String(el.tagName).toLowerCase() === 'iframe' && el.contentDocument === doc);
        if (!inside) return;
        var i = t.at.i;
        // Until goSoon() moves on, follow() leaves the pressed pointer in
        // place though its control went away (the press opened something
        // over it).
        t.wait = i;
        setTimeout(function () { if (S.tour === t && t.at.i === i) goSoon(t); }, PRESS_MS);
    }

    // Next after a press: the next pointer's control may come a moment
    // later (a list the press opened, rows read from the server), so it is
    // looked for a few times before it is skipped.
    function goSoon(t) {
        var tries = 0;
        var i = t.at.i;
        (function look() {
            // Moved on meanwhile (Next, Back): this press is answered.
            if (S.tour !== t || t.at.i !== i) { if (t.wait === i) t.wait = -1; return; }
            var next = t.list[i + 1];
            if (next && !M.anchor(next, has) && tries++ < WAIT_TRIES) {
                setTimeout(look, PRESS_MS);
                return;
            }
            t.wait = -1;
            go(1);
        })();
    }

    function onBubbleClick(e) {
        var b = e.target.closest('[data-sd-tour]');
        if (!b) return;
        var what = b.getAttribute('data-sd-tour');
        if (what === 'next') go(1);
        else if (what === 'back') go(-1);
        else finish();
    }

    // Inside the bubble: the right arrow is Next, the left arrow Back
    // (Enter presses the focused button itself).
    function onBubbleKey(e) {
        if (e.key === 'ArrowRight') { e.preventDefault(); go(1); }
        else if (e.key === 'ArrowLeft') { e.preventDefault(); go(-1); }
    }

    function onTourKey(e) {
        if (e.key === 'Escape' && S.tour && escapeIsOurs()) { ownEscape(e); finish(); }
    }

    function go(dir) {
        var t = S.tour;
        if (!t) return;
        var to = M.move(t.list, t.at.i, dir, has);
        if (!to) {
            if (dir > 0) finish();
            return;
        }
        t.at = to;
        t.wait = -1;
        draw(true);
    }

    // Ends the tour as seen: by its last stop, Skip or Escape alike. A
    // task's pointers end the same way and leave the tour as it was.
    function finish() {
        var t = S.tour;
        if (!t || !t.how) save(M.seen(load(), access()));
        if (!t) return;
        teardown();
        var back = t.back;
        if (back && back.isConnected && typeof back.focus === 'function') {
            try { back.focus({ preventScroll: true }); } catch (e) { back.focus(); }
        }
    }

    // Takes the ring, the bubble and their listeners away.
    function teardown() {
        var t = S.tour;
        S.tour = null;
        if (!t) return;
        clearInterval(t.timer);
        pressWatch(t, false);
        document.removeEventListener('keydown', onTourKey, true);
        window.removeEventListener('resize', follow);
        [t.ring, t.bubble].forEach(function (el) { if (el.parentNode) el.parentNode.removeChild(el); });
    }

    // Draws the bubble's parts for the current stop; `moved` gives the Next
    // button the focus and announces the stop.
    function draw(moved) {
        var t = S.tour;
        var how = t.how;
        var shown = M.shown(t.list, has);
        t.sig = signature(shown);
        var place = 0;
        shown.forEach(function (s, n) { if (s.i === t.at.i) place = n; });
        var total = shown.length || 1;
        // A task counts its three pointers as drawn ("1 of 3"): a later
        // pointer's control often comes only with the press before it.
        if (how) {
            place = t.at.i;
            total = t.list.length;
        }
        // Back before the first stop answers the stop itself.
        var prev = M.move(t.list, t.at.i, -1, has);
        var first = !prev || prev.i === t.at.i;
        var last = how ? t.at.i === t.list.length - 1 : !M.move(t.list, t.at.i, 1, has);
        var words = how ? [taskName(how) + '.', pointerText(t.at.id)] : sentence(t.at.id);
        var count = (how ? _sdT('How do I · {n} of {total}') : _sdT('Tour · {n} of {total}')).replace('{n}', String(place + 1)).replace('{total}', String(total));
        // A redraw takes the focused button away with the old markup: the
        // focus comes back to the same button (Next and Done are one: the
        // last stop's Next is Done), else to Next or Done.
        var MAIN = '[data-sd-tour="next"], [data-sd-tour="done"]';
        var had = t.bubble.contains(document.activeElement) && document.activeElement.getAttribute
            ? document.activeElement.getAttribute('data-sd-tour') || 'next' : '';
        if (had === 'done') had = 'next';
        var dots = '';
        for (var d = 0; d < total; d++) dots += '<i' + (d === place ? ' class="is-on"' : '') + '></i>';
        t.bubble.innerHTML =
            '<span class="sd-tour-arrow" aria-hidden="true"></span>' +
            '<div class="sd-tour-count"><span>' + esc(count) + '</span><span class="sd-tour-dots" aria-hidden="true">' + dots + '</span></div>' +
            '<p class="sd-tour-text"><b>' + esc(words[0]) + '</b> ' + esc(words[1]) + '</p>' +
            '<div class="sd-tour-btns">' +
                (last && !how ? '' : '<button type="button" class="sd-btn sd-tour-skip" data-sd-tour="skip">' + esc(how ? _sdT('Close') : _sdT('Skip')) + '</button>') +
                '<span class="sd-tour-space"></span>' +
                (first ? '' : '<button type="button" class="sd-btn" data-sd-tour="back">' + esc(_sdT('Back')) + '</button>') +
                (last
                    ? '<button type="button" class="sd-btn sd-btn-primary" data-sd-tour="done">' + esc(_sdT('Done')) + '</button>'
                    : '<button type="button" class="sd-btn sd-btn-primary" data-sd-tour="next">' + esc(_sdT('Next')) + '</button>') +
            '</div>' +
            '<span class="sd-tour-said" aria-live="polite"></span>';
        t.key = '';
        if (had && !moved) {
            var again = (had !== 'next' && t.bubble.querySelector('[data-sd-tour="' + had + '"]')) || t.bubble.querySelector(MAIN);
            if (again) {
                try { again.focus({ preventScroll: true }); } catch (e) { again.focus(); }
            }
        }
        if (moved) {
            // Read out whole: "Tour, 3 of 5. See it. Look at the page…"
            t.spoken = (how ? _sdT('How do I, {n} of {total}.') : _sdT('Tour, {n} of {total}.')).replace('{n}', String(place + 1)).replace('{total}', String(total)) + ' ' + words[0] + ' ' + words[1];
            t.pending = true;
        }
        follow();
        // While a dialog is open (the Help window a task was chosen in is
        // still closing) the bubble is hidden and can take no focus:
        // follow() presents the stop once it shows.
        if (moved && !t.hidden) present(t);
    }

    // Gives the stop's Next (or Done) the focus and reads the stop out.
    function present(t) {
        t.pending = false;
        var main = t.bubble.querySelector('[data-sd-tour="next"], [data-sd-tour="done"]');
        if (main) {
            try { main.focus({ preventScroll: true }); } catch (e) { main.focus(); }
        }
        var said = t.bubble.querySelector('.sd-tour-said');
        var spoken = t.spoken;
        setTimeout(function () { if (said.isConnected) said.textContent = spoken; }, 50);
    }

    // Keeps the ring on its control and the bubble beside it: on resize,
    // when a panel opens or closes, and while a dialog is open (hidden).
    function follow() {
        var t = S.tour;
        if (!t) return;
        var wait = dialogOpen();
        if (wait !== t.hidden) {
            t.hidden = wait;
            t.ring.hidden = wait;
            t.bubble.hidden = wait;
            t.key = '';
            // Shown again with its stop not yet presented (it was drawn
            // while a dialog was closing): the focus the tour started from
            // was inside that dialog, so the end goes back to where the
            // dialog gave it instead.
            if (!wait && t.pending) {
                var a = document.activeElement;
                if (t.back && t.back.closest && t.back.closest('dialog, [aria-modal="true"]') && a && a !== document.body && !own(a)) t.back = a;
                present(t);
            }
        }
        if (wait) return;
        // The control went away: its stop's other control, else the next
        // stop; a pressed pointer's control waits for goSoon().
        var sel = M.anchor(t.list[t.at.i], has);
        if (!sel) {
            if (t.wait !== t.at.i) go(1);
            return;
        }
        if (sel !== t.at.anchor) t.at = { i: t.at.i, id: t.at.id, anchor: sel };
        // Another stop came or went (a narrower window, a panel closed):
        // the count, the dots, Back and Done are drawn again.
        if (!t.how && signature(M.shown(t.list, has)) !== t.sig) { draw(false); return; }
        var v = sight(sel);
        // Below the fold of its list, or cut by it: scrolled into view once
        // per stop, then the part in sight is ringed.
        if (v.scroll && t.scrolled !== t.at.i) {
            t.scrolled = t.at.i;
            try { v.el.scrollIntoView({ block: 'nearest', inline: 'nearest' }); } catch (e) { v.el.scrollIntoView(false); }
            v = sight(sel) || v;
        }
        if (!v.box) return;
        var r = v.box;
        r.width = r.right - r.left;
        r.height = r.bottom - r.top;
        var W = window.innerWidth, H = window.innerHeight;
        var key = [r.left, r.top, r.width, r.height, W, H].map(Math.round).join(',');
        if (key === t.key) return;
        t.key = key;
        var ring = {
            left: Math.max(2, r.left - RING_GAP), top: Math.max(2, r.top - RING_GAP),
            right: Math.min(W - 2, r.right + RING_GAP), bottom: Math.min(H - 2, r.bottom + RING_GAP)
        };
        var rs = t.ring.style;
        rs.left = ring.left + 'px';
        rs.top = ring.top + 'px';
        rs.width = (ring.right - ring.left) + 'px';
        rs.height = (ring.bottom - ring.top) + 'px';
        placeBubble(t.bubble, ring, W, H);
    }

    function placeBubble(b, ring, W, H) {
        var bs = b.style;
        var arrow = b.querySelector('.sd-tour-arrow');
        b.classList.remove('is-sheet', 'is-top', 'at-left', 'at-right', 'at-top', 'at-bottom', 'is-inside');
        bs.left = bs.top = bs.right = bs.bottom = bs.width = '';
        if (W < PHONE) {
            // A sheet across the foot, or under the bars when the control
            // is in the window's lower half.
            b.classList.add('is-sheet');
            if ((ring.top + ring.bottom) / 2 > H / 2) {
                var bar = document.querySelector('.sd-wrapper > .sd-toolbar');
                b.classList.add('is-top');
                bs.top = (bar && bar.getClientRects().length ? Math.round(bar.getBoundingClientRect().bottom) : 0) + 'px';
            }
            return;
        }
        var bw = Math.min(BUBBLE_W, W - 2 * EDGE);
        var bh = b.offsetHeight || 150;
        var room = {
            right: W - ring.right - GAP - EDGE,
            left: ring.left - GAP - EDGE,
            bottom: H - ring.bottom - GAP - EDGE,
            top: ring.top - GAP - EDGE
        };
        var fits = {
            right: room.right >= bw, left: room.left >= bw,
            bottom: room.bottom >= bh, top: room.top >= bh
        };
        var cx = (ring.left + ring.right) / 2, cy = (ring.top + ring.bottom) / 2;
        var side = '';
        // A button of a bar (short): under it in the page bar, above it in
        // the status bar. A column: beside it, where there is most room.
        var bar = (ring.bottom - ring.top) < 64 ? (cy < H / 2 ? 'bottom' : 'top') : '';
        if (bar && fits[bar]) side = bar;
        else {
            ['right', 'left', 'bottom', 'top'].forEach(function (s) {
                if (fits[s] && (!side || room[s] > room[side])) side = s;
            });
        }
        var left, top;
        if (!side) {
            // A control as large as the window (the whole canvas): the bubble
            // stands inside the ring, at its foot.
            b.classList.add('is-inside');
            left = clamp(cx - bw / 2, EDGE, W - bw - EDGE);
            top = clamp(ring.bottom - bh - 16, EDGE, H - bh - EDGE);
        } else if (side === 'right' || side === 'left') {
            left = side === 'right' ? ring.right + GAP : ring.left - GAP - bw;
            top = clamp(cy - 34, EDGE, H - bh - EDGE);
            arrow.style.left = '';
            arrow.style.top = clamp(cy - top - 6, 14, bh - 26) + 'px';
        } else {
            top = side === 'bottom' ? ring.bottom + GAP : ring.top - GAP - bh;
            left = clamp(cx - bw + 46, EDGE, W - bw - EDGE);
            arrow.style.top = '';
            arrow.style.left = clamp(cx - left - 6, 14, bw - 26) + 'px';
        }
        // The bubble's own side faces the ring: right of it is "at-left".
        if (side) b.classList.add({ right: 'at-left', left: 'at-right', bottom: 'at-top', top: 'at-bottom' }[side]);
        bs.width = bw + 'px';
        bs.left = Math.round(left) + 'px';
        bs.top = Math.round(top) + 'px';
    }

    // The stops on the screen, as their places in the list.
    function signature(shown) {
        return shown.map(function (s) { return s.i; }).join(',');
    }

    function clamp(v, lo, hi) {
        return Math.max(lo, Math.min(hi, v));
    }

    // ── "How do I…" and "Tips" in the Help window (board T4) ─────────────

    // Fills the Help window's "How do I…" part and the "Tips" switch at its
    // foot. host: { how, foot, close } (close() shuts the window). Drawn
    // again at every opening, so the rows follow the access and the switch
    // what is stored now.
    function help(host) {
        if (!host || !host.how) return false;
        var rows = '<button type="button" class="sd-help-row is-tour" data-sd-help="tour">' +
            '<span class="bi bi-signpost-2" aria-hidden="true"></span><span class="sd-help-row-txt">' + esc(_sdT('Take the tour')) + '</span>' +
            '<span class="bi bi-chevron-right sd-help-row-go" aria-hidden="true"></span></button>' +
            '<div class="sd-help-sep" role="presentation"></div>';
        M.tasks(access()).forEach(function (t) {
            rows += '<button type="button" class="sd-help-row" data-sd-help="' + esc(t.id) + '">' +
                '<span class="bi ' + (TASK_ICONS[t.id] || 'bi-dot') + '" aria-hidden="true"></span>' +
                '<span class="sd-help-row-txt">' + esc(taskName(t.id)) + '</span>' +
                '<span class="bi bi-chevron-right sd-help-row-go" aria-hidden="true"></span></button>';
        });
        host.how.innerHTML = '<div class="sd-help-rows">' + rows + '</div>';
        if (!host.how.getAttribute('data-sd-help-wired')) {
            host.how.setAttribute('data-sd-help-wired', '1');
            host.how.addEventListener('click', function (e) {
                var b = e.target.closest('[data-sd-help]');
                if (!b) return;
                var what = b.getAttribute('data-sd-help');
                if (typeof host.close === 'function') host.close();
                // Once the window is gone: the tour waits while a dialog is open.
                setTimeout(function () { start(what === 'tour' ? undefined : what); }, 60);
            });
            // The arrows move over the rows; Enter presses the focused one.
            host.how.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
                var list = Array.prototype.slice.call(host.how.querySelectorAll('.sd-help-row'));
                if (!list.length) return;
                e.preventDefault();
                var at = list.indexOf(document.activeElement);
                var to = at === -1 ? 0 : (at + (e.key === 'ArrowDown' ? 1 : list.length - 1)) % list.length;
                list[to].focus();
            });
        }
        if (host.foot) tipsSwitch(host.foot);
        return true;
    }

    function tipsSwitch(foot) {
        var on = !load().tipsOff;
        foot.innerHTML = '<button type="button" class="sd-help-tips' + (on ? ' is-on' : '') + '" role="switch" aria-checked="' + (on ? 'true' : 'false') + '">' +
            '<span class="sd-help-tips-txt"><b>' + esc(_sdT('Tips')) + '</b><span>' + esc(_sdT('One line the first time you open a feature')) + '</span></span>' +
            '<span class="sd-help-tips-knob" aria-hidden="true"></span></button>';
        foot.querySelector('.sd-help-tips').addEventListener('click', function () {
            // Off, no first-use line is shown; the tips seen stay seen.
            var now = load();
            save(M.tipsOn(now, now.tipsOff));
            if (!now.tipsOff) tipHide();
            tipsSwitch(foot);
            var again = foot.querySelector('.sd-help-tips');
            if (again) again.focus();
        });
    }

    // ── A first-use tip (board T5) ────────────────────────────────────────

    // One line under a window's or a panel's head, the first time a
    // feature opens: cfg { name, text, head, box }. `head` is the element
    // the line goes under; `box` the feature (the head's parent when not
    // given): a press inside it, outside the line, counts as the feature
    // used and puts the line away for good. Never two at once, never while
    // the tour or a pointer is up, never with "Tips" off. Asked again by a
    // feature that drew its head anew, the line shown goes under the new one.
    function tip(cfg) {
        if (!cfg || !cfg.head || !cfg.head.parentNode || !cfg.text) return false;
        var name = String(cfg.name || '');
        if (S.tip && S.tip.name === name) {
            if (!S.tip.line.isConnected || S.tip.line.previousElementSibling !== cfg.head) {
                cfg.head.insertAdjacentElement('afterend', S.tip.line);
                tipBox(cfg.box || cfg.head.parentNode);
            }
            return true;
        }
        if (S.tip && !S.tip.line.isConnected) tipHide();
        if (S.tip || S.tour || !storageKey() || !M.tipDue(load(), name)) return false;
        var line = document.createElement('div');
        line.className = 'sd-tip-line';
        line.setAttribute('role', 'status');
        line.innerHTML = '<span class="sd-tip-text">' + esc(cfg.text) + '</span>' +
            '<button type="button" class="sd-tip-got">' + esc(_sdT('Got it')) + '</button>';
        line.querySelector('.sd-tip-got').addEventListener('click', function () { tipDone(); });
        cfg.head.insertAdjacentElement('afterend', line);
        S.tip = { name: name, line: line, box: null };
        tipBox(cfg.box || cfg.head.parentNode);
        return true;
    }

    function tipBox(box) {
        if (!S.tip) return;
        if (S.tip.box) S.tip.box.removeEventListener('click', onTipUse, true);
        S.tip.box = box || null;
        if (box) box.addEventListener('click', onTipUse, true);
    }

    // A press inside the feature, outside the line and its head: used.
    function onTipUse(e) {
        var tp = S.tip;
        if (!tp || tp.line.contains(e.target)) return;
        var head = tp.line.previousElementSibling;
        if (head && head.contains(e.target)) return;
        tipDone();
    }

    // "Got it", or the feature used: seen for good.
    function tipDone() {
        if (!S.tip) return;
        save(M.tipSeen(load(), S.tip.name));
        tipHide();
    }

    // Takes the line away; it is due again on the feature's next opening.
    function tipHide() {
        var tp = S.tip;
        S.tip = null;
        if (!tp) return;
        if (tp.box) tp.box.removeEventListener('click', onTipUse, true);
        if (tp.line.parentNode) tp.line.parentNode.removeChild(tp.line);
    }

    // ── An empty page's starts (board T6) ─────────────────────────────────

    // host: { area, starts: { section, sketch, document }, run(id) } to show
    // them over `area` (the canvas's frame area), or null to take them away.
    // Drawn over the canvas, never in the page.
    function empty(host) {
        var list = host ? M.starts(access(), host.starts) : [];
        if (!host || !host.area || !list.length) { emptyHide(); return false; }
        var sig = list.join(',');
        if (S.empty && S.empty.area === host.area && S.empty.sig === sig && S.empty.el.isConnected) {
            S.empty.run = host.run;
            emptyNarrow();
            return true;
        }
        emptyHide();
        var words = {
            section: [_sdT('Add a section'), _sdT('Pick a ready block and make it yours.'), 'bi-plus-square'],
            sketch: [_sdT('Sketch it'), _sdT('Draw boxes, get a layout.'), 'bi-vector-pen'],
            document: [_sdT('From a document'), _sdT('Turn a file into a page.'), 'bi-file-earmark-text']
        };
        var ask = list.length > 1 ? _sdT('This page is empty. How do you want to start?') : _sdT('This page is empty.');
        var el = document.createElement('div');
        el.className = 'sd-empty-starts';
        el.setAttribute('role', 'group');
        el.setAttribute('aria-label', ask);
        var cards = list.map(function (id) {
            var w = words[id];
            return '<button type="button" class="sd-empty-card" data-sd-start="' + id + '">' +
                '<span class="sd-empty-card-ic bi ' + w[2] + '" aria-hidden="true"></span>' +
                '<span class="sd-empty-card-name">' + esc(w[0]) + '</span>' +
                '<span class="sd-empty-card-line">' + esc(w[1]) + '</span></button>';
        }).join('');
        var said = ask + (list.length > 1 ? ' ' + _sdT('{n} choices.').replace('{n}', String(list.length)) : '');
        el.innerHTML = '<p class="sd-empty-ask" aria-hidden="true">' + esc(ask) + '</p><div class="sd-empty-cards">' + cards + '</div>' +
            '<span class="sd-tour-said" role="status"></span>';
        el.addEventListener('click', function (e) {
            var b = e.target.closest('[data-sd-start]');
            if (b && S.empty && typeof S.empty.run === 'function') S.empty.run(b.getAttribute('data-sd-start'));
        });
        host.area.appendChild(el);
        S.empty = { el: el, area: host.area, sig: sig, run: host.run, docs: [] };
        emptyDragWatch(true);
        emptyNarrow();
        // Read out once, after the live region is in the page.
        setTimeout(function () {
            var live = el.querySelector('.sd-tour-said');
            if (live && el.isConnected) live.textContent = said;
        }, 50);
        return true;
    }

    // While something is dragged the cards fade and let the drop through.
    function onEmptyDrag(e) {
        if (S.empty) S.empty.el.classList.toggle('is-faded', e.type === 'dragstart' || e.type === 'dragenter');
    }

    function emptyDragWatch(on) {
        var em = S.empty;
        if (!em) return;
        var docs = on ? [document, K.canvasDoc].filter(Boolean) : em.docs;
        docs.forEach(function (d) {
            var f = on ? 'addEventListener' : 'removeEventListener';
            d[f]('dragstart', onEmptyDrag, true);
            d[f]('dragenter', onEmptyDrag, true);
            d[f]('dragend', onEmptyDrag, true);
            d[f]('drop', onEmptyDrag, true);
        });
        em.docs = on ? docs : [];
        window[on ? 'addEventListener' : 'removeEventListener']('resize', emptyNarrow);
    }

    // Under 620 px of canvas the cards stand one under the other.
    function emptyNarrow() {
        if (!S.empty) return;
        var w = S.empty.area.getBoundingClientRect().width;
        S.empty.el.classList.toggle('is-narrow', w > 0 && w < NARROW_CANVAS);
    }

    function emptyHide() {
        if (!S.empty) return;
        emptyDragWatch(false);
        if (S.empty.el.parentNode) S.empty.el.parentNode.removeChild(S.empty.el);
        S.empty = null;
    }

    return {
        // The core's first quiet look found the card due: show it now, or at
        // the next quiet moment of this visit.
        offer: offer,
        // "Take the tour": the tour from its first stop, card or no card;
        // with a task's id, that "How do I…" task's pointers.
        start: start,
        // The Help window's "How do I…" rows and its "Tips" switch.
        help: help,
        // A feature's first-use line under its head.
        tip: tip,
        // An empty page's starts over the canvas, or none (null).
        empty: empty,
        // The model alone (window.PGDesignerTour), for a part that keeps a
        // first-use tip: running this part defines it.
        model: function () { return M; }
    };
});
