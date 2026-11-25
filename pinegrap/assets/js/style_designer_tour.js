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
 * Not needed to open a page: the core runs this file when the card is due
 * (_sdTourInit()) or when "Take the tour" is chosen in the command palette.
 * While neither the card nor the tour is up it holds no listener or timer.
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

    // card: the card's element while it is up; waitTimer: a card waiting
    // for a quiet moment. tour: the running tour, else null.
    var S = { card: null, waitTimer: 0, held: false, tour: null, docs: [] };

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

    function save(state) {
        var k = storageKey();
        if (!k) return;
        try { window.localStorage.setItem(k, M.write(state)); } catch (e) { /* kept for this visit only */ }
    }

    function reduced() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    function phone() {
        return window.innerWidth < PHONE;
    }

    // Whether the control `sel` is on the screen now: drawn, with a size,
    // and inside the window.
    function has(sel) {
        var el = document.querySelector(sel);
        if (!el || !el.getClientRects().length) return false;
        var r = el.getBoundingClientRect();
        return r.width > 0 && r.height > 0 && r.right > 0 && r.bottom > 0 &&
            r.left < window.innerWidth && r.top < window.innerHeight;
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

    function busy() {
        return { dialog: dialogOpen(), typing: typingIn(document) || typingIn(K.canvasDoc), dragging: S.held };
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
            save(M.offered(state));
            showCard();
            return;
        }
        // Offered or seen in another tab meanwhile: nothing to wait for.
        if (!M.offer(state, access(), {})) { holdWatch(false); return; }
        S.waitTimer = setTimeout(tryShow, WAIT_MS);
    }

    function showCard() {
        var content = access() === 'content';
        var card = document.createElement('div');
        card.className = 'sd-tour-card' + (phone() ? ' is-sheet' : '') + (reduced() ? '' : ' is-fading');
        card.setAttribute('role', 'region');
        card.setAttribute('aria-label', _sdT('New here? A one-minute tour'));
        card.innerHTML =
            '<span class="sd-tour-card-ic bi bi-signpost-2" aria-hidden="true"></span>' +
            '<div class="sd-tour-card-body">' +
                '<div class="sd-tour-card-title" role="status">' + esc(_sdT('New here? A one-minute tour')) + '</div>' +
                '<div class="sd-tour-card-line">' + esc(content ? _sdT('Three stops on what you can change.') : _sdT('Five stops on the real buttons.')) + '</div>' +
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

    function onCardKey(e) {
        if (e.key === 'Escape' && !dialogOpen()) closeCard();
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

    function start() {
        var back = document.activeElement;
        if (own(back)) back = null;
        closeCard();
        if (S.tour) return true;
        var list = M.stops(access());
        var at = M.first(list, has);
        if (!at) { finish(); return false; }
        var t = S.tour = {
            list: list, at: at, back: back, hidden: false, timer: 0, key: '',
            ring: document.createElement('div'), bubble: document.createElement('div')
        };
        t.ring.className = 'sd-tour-ring' + (reduced() ? '' : ' is-gliding');
        t.ring.setAttribute('aria-hidden', 'true');
        document.body.appendChild(t.ring);
        t.bubble.className = 'sd-tour-bubble';
        t.bubble.setAttribute('role', 'dialog');
        t.bubble.setAttribute('aria-modal', 'false');
        t.bubble.setAttribute('aria-label', _sdT('Tour'));
        t.bubble.addEventListener('click', onBubbleClick);
        t.bubble.addEventListener('keydown', onBubbleKey);
        placeAfterBar(t.bubble);
        document.addEventListener('keydown', onTourKey, true);
        window.addEventListener('resize', follow);
        t.timer = setInterval(follow, FOLLOW_MS);
        draw(true);
        return true;
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
        if (e.key === 'Escape' && S.tour && !dialogOpen()) finish();
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
        draw(true);
    }

    // Ends the tour as seen: by its last stop, Skip or Escape alike.
    function finish() {
        save(M.seen(load(), access()));
        var t = S.tour;
        S.tour = null;
        if (!t) return;
        clearInterval(t.timer);
        document.removeEventListener('keydown', onTourKey, true);
        window.removeEventListener('resize', follow);
        [t.ring, t.bubble].forEach(function (el) { if (el.parentNode) el.parentNode.removeChild(el); });
        var back = t.back;
        if (back && back.isConnected && typeof back.focus === 'function') {
            try { back.focus({ preventScroll: true }); } catch (e) { back.focus(); }
        }
    }

    // Draws the bubble's parts for the current stop; `moved` gives the Next
    // button the focus and announces the stop.
    function draw(moved) {
        var t = S.tour;
        var shown = M.shown(t.list, has);
        var place = 0;
        shown.forEach(function (s, n) { if (s.i === t.at.i) place = n; });
        var total = shown.length || 1;
        // Back before the first stop answers the stop itself.
        var prev = M.move(t.list, t.at.i, -1, has);
        var first = !prev || prev.i === t.at.i;
        var last = !M.move(t.list, t.at.i, 1, has);
        var words = sentence(t.at.id);
        var count = _sdT('Tour · {n} of {total}').replace('{n}', String(place + 1)).replace('{total}', String(total));
        var dots = '';
        for (var d = 0; d < total; d++) dots += '<i' + (d === place ? ' class="is-on"' : '') + '></i>';
        t.bubble.innerHTML =
            '<span class="sd-tour-arrow" aria-hidden="true"></span>' +
            '<div class="sd-tour-count"><span>' + esc(count) + '</span><span class="sd-tour-dots" aria-hidden="true">' + dots + '</span></div>' +
            '<p class="sd-tour-text"><b>' + esc(words[0]) + '</b> ' + esc(words[1]) + '</p>' +
            '<div class="sd-tour-btns">' +
                (last ? '' : '<button type="button" class="sd-btn sd-tour-skip" data-sd-tour="skip">' + esc(_sdT('Skip')) + '</button>') +
                '<span class="sd-tour-space"></span>' +
                (first ? '' : '<button type="button" class="sd-btn" data-sd-tour="back">' + esc(_sdT('Back')) + '</button>') +
                (last
                    ? '<button type="button" class="sd-btn sd-btn-primary" data-sd-tour="done">' + esc(_sdT('Done')) + '</button>'
                    : '<button type="button" class="sd-btn sd-btn-primary" data-sd-tour="next">' + esc(_sdT('Next')) + '</button>') +
            '</div>' +
            '<span class="sd-tour-said" aria-live="polite"></span>';
        t.key = '';
        follow();
        if (!moved) return;
        var main = t.bubble.querySelector('[data-sd-tour="next"], [data-sd-tour="done"]');
        if (main) {
            try { main.focus({ preventScroll: true }); } catch (e) { main.focus(); }
        }
        // Read out whole: "Tour, 3 of 5. See it. Look at the page…"
        var said = t.bubble.querySelector('.sd-tour-said');
        var spoken = _sdT('Tour, {n} of {total}.').replace('{n}', String(place + 1)).replace('{total}', String(total)) + ' ' + words[0] + ' ' + words[1];
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
        }
        if (wait) return;
        // The control went away: its stop's other control, else the next stop.
        var sel = M.anchor(t.list[t.at.i], has);
        if (!sel) { go(1); return; }
        if (sel !== t.at.anchor) t.at = { i: t.at.i, id: t.at.id, anchor: sel };
        var r = document.querySelector(sel).getBoundingClientRect();
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

    function clamp(v, lo, hi) {
        return Math.max(lo, Math.min(hi, v));
    }

    return {
        // The core's first quiet look found the card due: show it now, or at
        // the next quiet moment of this visit.
        offer: offer,
        // "Take the tour": the tour from its first stop, card or no card.
        start: start,
        // The model alone (window.PGDesignerTour), for a part that keeps a
        // first-use tip: running this part defines it.
        model: function () { return M; }
    };
});
