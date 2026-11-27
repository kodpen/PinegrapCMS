/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the "Preview on a phone" window part: the QR code of
 * the person's phone link (an eight-hour share link of the design, made on
 * the first press and found again while it lives,
 * includes/fn/prototype_phone.php), the page it starts on, the time it has
 * left, "Copy link" and "End now". A page the link for anyone cannot draw
 * offers the team link instead. Opened from the Preview menu's "Preview on
 * a phone" and the command palette. Not needed to open a page: the core
 * runs this file the first time the window is asked for (_sdPartLoad(),
 * pg_designer_parts()) and hands it the page through a host
 * (_sdPhoneHost()). Below 768 px the window fills the screen and offers
 * "Open here".
 *
 * The code is inline SVG drawn by the server; the address is kept only
 * while the window is open, and nothing runs once it is closed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('phone', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var sdToast = K.sdToast;
    var _pgApiPost = K._pgApiPost;

    var state = { host: null, el: null, answer: null, at: 0, audience: 'anyone', view: '', tick: null, copied: null, busy: false };

    // The time a link has left, in words: "7 h 58 min", "8 h", "42 min",
    // "less than a minute"; '' once it has run out.
    function _phLeftWords(seconds) {
        seconds = Math.floor(seconds);
        if (!(seconds > 0)) return '';
        if (seconds < 60) return _sdT('less than a minute');
        var minutes = Math.floor(seconds / 60);
        if (minutes < 60) return _sdT('{var} min', String(minutes));
        var h = Math.floor(minutes / 60), m = minutes % 60;
        return m ? _sdT('{var:1} h {var:2} min', [String(h), String(m)]) : _sdT('{var} h', String(h));
    }

    // What the window shows for an answer of prototype_phone and the time
    // left: 'team' (the page needs a team link), 'live' (a design with no
    // draft pages: the code of the live page, no link behind it), 'ready',
    // 'ended' (ran out while open) or 'failed'.
    function _phView(res, secondsLeft) {
        if (!res || res.status !== 'success') return 'failed';
        if (res.team_only) return 'team';
        if (res.live) return (res.url && res.qr) ? 'live' : 'failed';
        if (!res.link || !res.url || !res.qr) return 'failed';
        return secondsLeft > 0 ? 'ready' : 'ended';
    }

    // Why a 'failed' answer made no link, and whether "Try again" can help:
    // the server's own message where it answered an error, retried only
    // where it marks the answer `retry` (the link could not be stored); no
    // answer or a malformed one says "Nothing was changed." and may be
    // tried again. A design with no draft pages is never helped by a retry.
    function _phFailure(res) {
        if (res && res.status === 'error') {
            return { text: res.message ? String(res.message) : _sdT('Nothing was changed.'), again: !!res.retry };
        }
        return { text: _sdT('Nothing was changed.'), again: true };
    }

    function _phLeft() {
        var link = state.answer && state.answer.link;
        if (!link) return 0;
        return (link.seconds_left || 0) - (Date.now() - state.at) / 1000;
    }

    function _phNarrow() {
        return !!(window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches);
    }

    function _phBuild() {
        var d = document.createElement('dialog');
        d.className = 'dialog sd-settings-modal-dark sd-modal-content pg-dialog-centered sd-phone-dialog' + (_phNarrow() ? ' sd-phone-narrow' : '');
        d.tabIndex = -1;
        // The designer's windows are dark whatever the panel's scheme: without
        // this a light panel drew the window white, its lines dark on dark.
        d.setAttribute('data-bs-theme', 'dark');
        d.setAttribute('aria-labelledby', 'sd-phone-title');
        d.innerHTML =
            '<div class="dialog-header sd-modal-header">' +
                '<h5 class="dialog-title" id="sd-phone-title">' + esc(_sdT('Preview on a phone')) + '</h5>' +
                '<button type="button" class="btn-close fg-white" data-bs-dismiss="dialog" aria-label="' + esc(_sdT('Close')) + '"></button>' +
            '</div>' +
            '<div class="dialog-body sd-modal-body sd-phone-body" aria-live="polite"></div>' +
            '<div class="dialog-footer sd-modal-footer sd-phone-foot"></div>';
        document.body.appendChild(d);
        d.addEventListener('click', function (e) {
            var b = e.target.closest('[data-ph]');
            if (!b || b.disabled) return;
            var what = b.getAttribute('data-ph');
            if (what === 'copy') _phCopy(b);
            else if (what === 'end') _phEnd();
            else if (what === 'open') { if (state.answer && state.answer.url) window.open(state.answer.url, '_blank', 'noopener'); }
            else if (what === 'save') _phSave(b);
            else if (what === 'team') _phAsk('team');
            else if (what === 'again') _phAsk(state.audience);
        });
        return d;
    }

    // The info line under the code: a page never saved, changes not saved
    // (with the designer's own Save), or what the phone shows.
    function _phInfoLine() {
        var host = state.host;
        if (host.page && !host.page.saved) {
            return '<p class="sd-phone-line"><span class="bi bi-info-circle" aria-hidden="true"></span><span>' +
                esc(_sdT('“{var}” has not been saved yet. Save it and it joins the phone\'s page list.', host.page.name || _sdT('New page'))) + '</span></p>';
        }
        if (host.dirty()) {
            return '<p class="sd-phone-line sd-phone-warn"><span class="bi bi-exclamation-triangle" aria-hidden="true"></span><span>' +
                esc(_sdT('You have changes that are not saved. The phone shows them after a save.')) + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-ph="save">' + esc(_sdT('Save')) + '</button></p>';
        }
        return '<p class="sd-phone-line"><span class="bi bi-info-circle" aria-hidden="true"></span><span>' +
            esc(_sdT('The phone shows the pages as last saved and redraws after each save.')) + '</span></p>';
    }

    // The foot's buttons; a live page's address has no link to end.
    function _phFoot(on, live) {
        var off = on ? '' : ' disabled';
        var copy = '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-ph="copy"' + off + '><span class="bi bi-copy me-1" aria-hidden="true"></span>' + esc(_sdT('Copy link')) + '</button>';
        var end = live ? '' : '<button type="button" class="btn btn-sm btn-text theme-secondary" data-ph="end"' + off + '>' + esc(_sdT('End now')) + '</button>';
        if (_phNarrow()) {
            return '<button type="button" class="btn btn-sm btn-solid theme-primary" data-ph="open"' + off + '><span class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></span>' + esc(_sdT('Open here')) + '</button>' + copy + end;
        }
        return end + '<span class="me-auto"></span>' + copy;
    }

    // Draws the window for state.view.
    function _phDraw() {
        var d = state.el;
        if (!d) return;
        var body = d.querySelector('.sd-phone-body');
        var foot = d.querySelector('.sd-phone-foot');
        var res = state.answer;
        var html = '', footHtml = _phFoot(false);
        if (state.view === 'wait') {
            html = '<div class="sd-phone-code sd-phone-wait"><span class="spinner-border" role="status" aria-hidden="true"></span></div>' +
                '<p class="sd-phone-head">' + esc(_sdT('Making the link…')) + '</p><p class="sd-phone-sub">&nbsp;</p>' + _phInfoLine();
        } else if (state.view === 'team') {
            html = '<div class="sd-phone-code sd-phone-wait"><span class="bi bi-lock" aria-hidden="true"></span></div>' +
                '<p class="sd-phone-head">' + esc(_sdT('“{var}” is for signed-in visitors', (res && res.start_page) || (state.host.page && state.host.page.name) || '')) + '</p>' +
                '<p class="sd-phone-sub">' + esc(_sdT('A link for anyone cannot show it. A team link opens after a sign-in on the phone.')) + '</p>' +
                '<p class="sd-phone-act"><button type="button" class="btn btn-sm btn-solid theme-primary" data-ph="team">' + esc(_sdT('Make a team link')) + '</button></p>';
            footHtml = '<span class="sd-phone-note">' + esc(_sdT('Team: administrators, designers and managers.')) + '</span>';
        } else if (state.view === 'failed') {
            var why = _phFailure(res);
            html = '<div class="sd-phone-code sd-phone-wait"><span class="bi bi-qr-code" aria-hidden="true"></span></div>' +
                '<p class="sd-phone-head">' + esc(_sdT('The link could not be made')) + '</p>' +
                '<p class="sd-phone-sub">' + esc(why.text) + '</p>' +
                (why.again ? '<p class="sd-phone-act"><button type="button" class="btn btn-sm btn-outline theme-secondary" data-ph="again">' + esc(_sdT('Try again')) + '</button></p>' : '');
            footHtml = '';
        } else if (state.view === 'ended') {
            html = '<div class="sd-phone-code sd-phone-dim">' + res.qr + '</div>' +
                '<p class="sd-phone-head">' + esc(_sdT('This link has ended')) + '</p>' +
                '<p class="sd-phone-sub">' + esc(_sdT('The phone now shows “This link no longer works.”')) + '</p>' +
                '<p class="sd-phone-act"><button type="button" class="btn btn-sm btn-solid theme-primary" data-ph="again">' + esc(_sdT('Make a new link')) + '</button></p>';
        } else if (state.view === 'ready') {
            var team = res.link.audience === 'team';
            // The code comes from the server as SVG markup drawn from the
            // address (pg_qr_svg()); it holds no text from elsewhere.
            html = '<div class="sd-phone-code">' + res.qr + '</div>' +
                '<p class="sd-phone-head">' + esc(_phNarrow() ? _sdT('Point another phone\'s camera here') : _sdT('Point your phone\'s camera here')) + '</p>' +
                (team ? '<p class="sd-phone-sub"><span class="badge theme-info me-1">' + esc(_sdT('Team link')) + '</span>' + esc(_sdT('Sign in on the phone to open it.')) + '</p>' : '') +
                '<p class="sd-phone-sub" data-ph-left>' + _phStartsLine() + '</p>' + _phInfoLine();
            footHtml = _phFoot(true);
        } else if (state.view === 'live') {
            // No share link stands behind this code: no time left, nothing
            // to end, no redraw after a save.
            html = '<div class="sd-phone-code">' + res.qr + '</div>' +
                '<p class="sd-phone-head">' + esc(_phNarrow() ? _sdT('Point another phone\'s camera here') : _sdT('Point your phone\'s camera here')) + '</p>' +
                '<p class="sd-phone-sub">' + _sdT('Opens {var} on the site', '<b>' + esc(res.start_page || '') + '</b>') + '</p>' +
                '<p class="sd-phone-line"><span class="bi bi-info-circle" aria-hidden="true"></span><span>' +
                esc(_sdT('This design has no draft pages, so the code opens the live page as visitors see it.')) + '</span></p>';
            footHtml = _phFoot(true, true);
        }
        body.innerHTML = html;
        foot.innerHTML = footHtml;
        // No link, no foot: "Copy link" and "End now" are not drawn dead.
        foot.hidden = footHtml === '';
    }

    function _phStartsLine() {
        var link = state.answer.link;
        return _sdT('Starts on {var:1} · works for {var:2}', ['<b>' + esc(link.start_page || '') + '</b>', '<b>' + esc(_phLeftWords(_phLeft())) + '</b>']);
    }

    // Once a minute while the window is open: the time left counted down,
    // the code dimmed once it has run out, the unsaved line brought up to
    // date.
    function _phTick() {
        if (!state.el || state.view !== 'ready') return;
        if (_phLeft() <= 0) {
            state.view = 'ended';
            _phDraw();
            return;
        }
        var line = state.el.querySelector('[data-ph-left]');
        if (line) line.innerHTML = _phStartsLine();
        var info = state.el.querySelector('.sd-phone-line');
        if (info) info.outerHTML = _phInfoLine();
    }

    // Asks the server for the person's phone link of the design for an
    // audience: found again while it lives, else made.
    function _phAsk(audience) {
        if (state.busy || !state.el) return;
        state.busy = true;
        state.audience = audience;
        state.view = 'wait';
        _phDraw();
        var host = state.host;
        _pgApiPost('prototype_phone', { style_id: host.styleId, page_id: host.page ? host.page.id : 0, audience: audience }).then(function (res) {
            state.busy = false;
            if (!state.el) return;
            state.answer = res || null;
            state.at = Date.now();
            state.view = _phView(res, res && res.link ? res.link.seconds_left : 0);
            _phDraw();
            var first = state.el.querySelector('[data-ph="copy"]:not([disabled])') || state.el.querySelector('.sd-phone-act .btn');
            if (first) first.focus();
        }, function () {
            state.busy = false;
            if (!state.el) return;
            state.answer = null;
            state.view = 'failed';
            _phDraw();
        });
    }

    // "Copy link": "Copied" with a tick for two seconds. A write the browser
    // refuses shows the address in a prompt to copy by hand.
    function _phCopy(btn) {
        var url = state.answer && state.answer.url;
        if (!url) return;
        var done = function () {
            btn.innerHTML = '<span class="bi bi-check-lg me-1" aria-hidden="true"></span>' + esc(_sdT('Copied'));
            clearTimeout(state.copied);
            state.copied = setTimeout(function () {
                if (btn.isConnected) btn.innerHTML = '<span class="bi bi-copy me-1" aria-hidden="true"></span>' + esc(_sdT('Copy link'));
            }, 2000);
        };
        var failed = function () { window.prompt(_sdT('Copy link'), url); };
        if (!navigator.clipboard || !navigator.clipboard.writeText) { failed(); return; }
        navigator.clipboard.writeText(url).then(done, failed);
    }

    // "End now" asks nothing: the link is revoked, the window closes and a
    // line says so.
    function _phEnd() {
        var link = state.answer && state.answer.link;
        if (!link || state.busy) return;
        state.busy = true;
        state.el.querySelectorAll('.sd-phone-foot .btn').forEach(function (b) { b.disabled = true; });
        _pgApiPost('prototype_phone_end', { link_id: link.id }).then(function (res) {
            state.busy = false;
            if (!res || res.status !== 'success') {
                sdToast(esc((res && res.message) || _sdT('Sorry, we could not accept your request.')), 'error');
                if (state.el) _phDraw();
                return;
            }
            _phClose();
            sdToast(esc(_sdT('The phone preview has ended.')), 'success');
        }, function () {
            state.busy = false;
            sdToast(esc(_sdT('Network error.')), 'error');
            if (state.el) _phDraw();
        });
    }

    // The amber line's Save is the designer's own; the line leaves once the
    // design is saved.
    function _phSave(btn) {
        btn.disabled = true;
        state.host.save(function () {
            if (!state.el || state.view !== 'ready') return;
            var info = state.el.querySelector('.sd-phone-line');
            if (info) info.outerHTML = _phInfoLine();
        });
    }

    function _phClose() {
        if (state.el && window.pgUI) pgUI.dialog.hide(state.el);
    }

    // Opens the window for the page the host names. Escape or the cross
    // closes it; the link lives on.
    function open(host) {
        if (state.el || !window.pgUI) return;
        state.host = host;
        state.answer = null;
        // Focus goes back where the window was asked for; a Preview menu row
        // is hidden by then, so its button takes it.
        var back = document.activeElement;
        state.el = _phBuild();
        pgUI.dialog.on(state.el, 'hidden', function () {
            var to = back && back.isConnected && back.getClientRects().length ? back : document.getElementById('sd-preview-menu-btn');
            if (to && to.getClientRects().length) to.focus();
            clearInterval(state.tick);
            clearTimeout(state.copied);
            state.tick = null;
            if (state.el) state.el.remove();
            state.el = null;
            state.answer = null;
            state.host = null;
        });
        pgUI.dialog.show(state.el);
        state.tick = setInterval(_phTick, 60000);
        _phAsk('anyone');
    }

    return { open: open };
});
