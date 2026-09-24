/**
 * PineGrap - Enterprise Website Platform — Live Chat site widget.
 *
 * pg_chat_render_site_widget() (chat.php) injects this file into frontend
 * pages. Frontend themes offer no Bootstrap/jQuery guarantee, so it is
 * FULLY self-contained: vanilla JS + XMLHttpRequest; it writes to the DOM
 * with textContent only (no innerHTML — message bodies are plain text).
 *
 * Behavior:
 *  - Visitors WITHOUT a conversation cause no API request until the bubble
 *    is clicked. Visitors with a conversation get a light "seen: 0" poll
 *    every 20 s while the window is closed: on a reply, an unread badge
 *    appears on the bubble and a notification sound plays. seen: 0 does
 *    NOT advance the server-side read cursor — the "seen" tick only forms
 *    while the window is actually open.
 *  - Poll with the window open: 4 s; 2 s while either side is typing.
 *    Both loops stop while the tab is hidden.
 *  - Delivered (single tick) / seen (double tick) on own messages, driven
 *    by the peer's server-side read cursor (peer_read_id).
 *  - When the operator closes the conversation the compose area is hidden
 *    PERMANENTLY; closing and reopening the window does not bring it back.
 *    A new conversation starts only through the explicit new-chat button.
 *  - Puzzle captcha (server verified) on the anonymous visitor's FIRST
 *    send; never asked of members. The conversation row is created
 *    server-side with the first message.
 *  - Open/closed state lives in sessionStorage; it survives page
 *    navigation.
 *
 * @author Erdal Güral (Kodpen)
 */

(function () {
    'use strict';

    var configElement = document.getElementById('pg-chat-site-config');
    var root = document.getElementById('pg-chat-site');
    var iconElement = document.getElementById('pg-chat-site-icon');

    if (!configElement || !root) {
        return;
    }

    var config;

    try {
        config = JSON.parse(configElement.textContent || configElement.innerText || '{}');
    } catch (error) {
        return;
    }

    var STR = config.strings || {};

    // Signed-in visitors get the PANEL's chat instead of the visitor
    // bubble: their own conversation list, the staff they may write to, and
    // the same chat_* endpoints the panel window calls. Everything below
    // that differs between the two windows hangs off this one flag.
    var MEMBER = !!config.member_mode;

    var state = {
        open: false,
        booted: false,
        // Member mode only: which of the two screens is showing, which tab
        // the list is on, and the conversation currently open.
        view: MEMBER ? 'list' : 'conversation',
        tab: 'conversations',
        conversation: null,
        conversations: [],
        users: [],
        listTimer: null,
        heartbeatTimer: null,
        canManage: false,
        conversationId: 0,
        sinceId: 0,
        status: 'open',
        operatorOnline: false,
        captchaRequired: false, // real value comes from bootstrap
        captchaSolved: false,
        lastTypedAt: 0,
        peerTyping: false,
        pollTimer: null,
        bgTimer: null,     // badge/sound poll while the window is closed
        unread: 0,         // bubble badge count (server-side count)
        peerReadId: 0,     // the peer's read cursor (single/double tick)
        closed: false,     // operator closed: compose stays hidden
        pendingBody: ''
    };

    // ── Helpers ─────────────────────────────────────────────────────────

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (typeof text === 'string' && text !== '') {
            node.textContent = text;
        }

        return node;
    }

    // Every rule the widget ships carries !important, so a site stylesheet
    // cannot reshape the window. That also outranks an ordinary inline
    // style, which is how the script used to show and hide things - so
    // anything set at runtime is set the same way. An empty value removes
    // the property and hands the element back to the stylesheet.
    function setStyle(node, property, value) {
        if (value === '' || value === null || typeof value === 'undefined') {
            if (node.style.removeProperty) {
                node.style.removeProperty(property);
            } else {
                node.style[property] = '';
            }

            return;
        }

        if (node.style.setProperty) {
            node.style.setProperty(property, value, 'important');
        } else {
            node.style[property] = value;
        }
    }

    function api(action, data, done) {
        var payload = { action: action, token: config.token };
        var key;

        for (key in (data || {})) {
            if (Object.prototype.hasOwnProperty.call(data, key)) {
                payload[key] = data[key];
            }
        }

        var xhr = new XMLHttpRequest();

        xhr.open('POST', config.api_url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }

            var response = {};

            try {
                response = JSON.parse(xhr.responseText || '{}');
            } catch (error) {
                response = { status: 'error', message: '' };
            }

            if (done) {
                done(response);
            }
        };

        xhr.send(JSON.stringify(payload));
    }

    function storage(key, value) {
        try {
            if (arguments.length === 2) {
                window.sessionStorage.setItem(key, value);
                return value;
            }

            return window.sessionStorage.getItem(key);
        } catch (error) {
            return null;
        }
    }

    // Client-side email format check. The server validates again with
    // filter_var and SILENTLY empties invalid values — without flagging it
    // red here, a scribbled value would look accepted.
    function emailLooksValid(value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
    }

    // ── Notification sound ──────────────────────────────────────────────
    // A short "ding" when a message arrives from the operator and the tab
    // is not focused (Web Audio; no sound file). Set up on the first user
    // interaction per the browser autoplay policy — clicking the bubble is
    // enough.
    var audio = { ctx: null };

    function unlockAudio() {
        var Ctx = window.AudioContext || window.webkitAudioContext;

        if (!audio.ctx && Ctx) {
            try {
                audio.ctx = new Ctx();
            } catch (error) {
                audio.ctx = null;
            }
        }

        if (audio.ctx && audio.ctx.state === 'suspended') {
            audio.ctx.resume();
        }
    }

    document.addEventListener('click', unlockAudio);
    document.addEventListener('keydown', unlockAudio);
    document.addEventListener('pointerdown', unlockAudio);
    document.addEventListener('touchstart', unlockAudio);

    function playTone() {
        try {
            var t = audio.ctx.currentTime;
            var osc = audio.ctx.createOscillator();
            var gain = audio.ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, t);
            osc.frequency.setValueAtTime(660, t + 0.09);
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(0.12, t + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.22);
            osc.connect(gain);
            gain.connect(audio.ctx.destination);
            osc.start(t);
            osc.stop(t + 0.24);
        } catch (error) {}
    }

    function playBeep() {
        if (!audio.ctx) {
            unlockAudio();
        }

        if (!audio.ctx) {
            return;
        }

        if (audio.ctx.state === 'suspended') {
            try {
                audio.ctx.resume().then(playTone).catch(function () {});
            } catch (error) {}

            return;
        }

        playTone();
    }

    // Auto theme: follow the operating system preference.
    function applyTheme() {
        if (config.theme !== 'auto') {
            return;
        }

        var dark = false;

        if (window.matchMedia) {
            dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }

        root.setAttribute('data-theme', dark ? 'dark' : 'light');
    }

    applyTheme();

    if (config.theme === 'auto' && window.matchMedia) {
        var media = window.matchMedia('(prefers-color-scheme: dark)');

        if (media.addEventListener) {
            media.addEventListener('change', applyTheme);
        }
    }

    // ── Skeleton ────────────────────────────────────────────────────────

    var bubble = el('button', 'pinegrap-chat-bubble');
    bubble.type = 'button';
    bubble.setAttribute('aria-label', STR.subtitle);

    if (iconElement) {
        bubble.innerHTML = iconElement.textContent || '';
    }

    var badge = el('span', 'pinegrap-chat-badge');
    bubble.appendChild(badge);

    // Bubble badge: shows the count while the window is closed and unread
    // messages exist.
    function updateBadge() {
        if (state.open || !(state.unread > 0)) {
            setStyle(badge, 'display', 'none');
            return;
        }

        badge.textContent = (state.unread > 9) ? '9+' : String(state.unread);
        setStyle(badge, 'display', 'flex');
    }

    var win = el('div', 'pinegrap-chat-window');

    var header = el('div', 'pinegrap-chat-header');
    var title = el('strong', '', STR.title);
    var status = el('div', 'pinegrap-chat-status', STR.subtitle);
    var closeButton = el('button', 'pinegrap-chat-close', '✕');
    closeButton.type = 'button';
    closeButton.addEventListener('click', function () {
        toggleWindow();
    });
    // Member mode adds a back arrow (conversation to list) and one header
    // action, closing the conversation. Both are created in either mode so
    // nothing below has to test for their existence; only member mode puts
    // them on screen.
    var backButton = el('button', 'pinegrap-chat-back');
    backButton.type = 'button';
    backButton.setAttribute('aria-label', STR.back || '');
    backButton.title = STR.back || '';
    backButton.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';

    var headAction = el('button', 'pinegrap-chat-head-action');
    headAction.type = 'button';
    headAction.setAttribute('aria-label', STR.close_conversation || '');
    headAction.title = STR.close_conversation || '';
    headAction.innerHTML = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 12.2l2.7 2.7L16 9.6"/></svg>';

    header.appendChild(title);
    header.appendChild(status);
    header.appendChild(closeButton);

    var listBox = el('div', 'pinegrap-chat-list');
    var tabsRow = el('div', 'pinegrap-chat-tabs');
    var tabConversations = el('button', 'pinegrap-chat-tab pinegrap-chat-on', STR.tab_conversations || '');
    var tabUsers = el('button', 'pinegrap-chat-tab', STR.tab_users || '');
    var listBody = el('div');

    if (MEMBER) {
        header.appendChild(backButton);
        header.appendChild(headAction);

        backButton.addEventListener('click', function () {
            leaveConversation();
        });

        headAction.addEventListener('click', function () {
            if (!state.conversationId) {
                return;
            }

            api('chat_close', { conversation_id: state.conversationId }, function () {
                leaveConversation();
            });
        });

        tabConversations.type = 'button';
        tabUsers.type = 'button';

        tabConversations.addEventListener('click', function () {
            state.tab = 'conversations';
            renderList();
        });

        tabUsers.addEventListener('click', function () {
            state.tab = 'users';
            renderList();
        });

        tabsRow.appendChild(tabConversations);
        tabsRow.appendChild(tabUsers);
        listBox.appendChild(tabsRow);
        listBox.appendChild(listBody);
    }

    win.appendChild(header);
    win.appendChild(listBox);

    // The list is what a member sees first. Set here rather than through
    // setView() because the composer the latter also touches is built
    // further down.
    if (MEMBER) {
        root.classList.add('pinegrap-chat-view-list');
    }

    var offlineNote = el('div', 'pinegrap-chat-offline-note', STR.offline_note);
    setStyle(offlineNote, 'display', 'none');
    win.appendChild(offlineNote);

    var messagesBox = el('div', 'pinegrap-chat-messages');
    win.appendChild(messagesBox);

    var typingLine = el('div', 'pinegrap-chat-typing', STR.typing + '…');
    win.appendChild(typingLine);

    // Anonymous identity (optional name/email), and optional is the whole
    // of it. Nothing is asked of somebody who has not said anything yet:
    // the row stays out of the way until the first message is on its way,
    // and then appears as one quiet line that opens the two fields when it
    // is clicked. Attachments do not wait on it - the captcha is the gate
    // that costs an attacker something, and a field that takes two
    // scribbled characters was never one.
    var identityRow = null;
    var identityInvite = null;
    var identityFields = null;
    var nameInput = null;
    var emailInput = null;

    if (!config.member) {
        identityRow = el('div', 'pinegrap-chat-identity');

        nameInput = el('input');
        nameInput.type = 'text';
        nameInput.placeholder = STR.name_placeholder;
        nameInput.maxLength = 100;
        nameInput.value = config.name || '';

        emailInput = el('input');
        emailInput.type = 'email';
        emailInput.placeholder = STR.email_placeholder;
        emailInput.maxLength = 255;
        emailInput.value = config.email || '';

        function saveIdentity() {
            var emailValue = emailInput.value.replace(/^\s+|\s+$/g, '');

            // Invalid format is flagged red immediately; the server also
            // validates with filter_var and empties invalid values, so it
            // never reaches the record.
            if (emailValue !== '' && !emailLooksValid(emailValue)) {
                emailInput.classList.add('pinegrap-chat-required');
            }

            api('site_chat_update_identity', { name: nameInput.value, email: emailInput.value }, function () {
                if (nameInput.value.replace(/^\s+|\s+$/g, '') !== '') {
                    nameInput.classList.remove('pinegrap-chat-required');
                }

                if (emailLooksValid(emailInput.value.replace(/^\s+|\s+$/g, ''))) {
                    emailInput.classList.remove('pinegrap-chat-required');
                }

                // Both given: the invitation has done its job.
                if (identityComplete()) {
                    hideIdentityRow();
                }
            });
        }

        nameInput.addEventListener('change', saveIdentity);
        emailInput.addEventListener('change', saveIdentity);

        // The red flag clears as soon as the field becomes valid (while
        // typing).
        nameInput.addEventListener('input', function () {
            if (nameInput.value.replace(/^\s+|\s+$/g, '') !== '') {
                nameInput.classList.remove('pinegrap-chat-required');
            }
        });

        emailInput.addEventListener('input', function () {
            if (emailLooksValid(emailInput.value.replace(/^\s+|\s+$/g, ''))) {
                emailInput.classList.remove('pinegrap-chat-required');
            }
        });

        identityInvite = el('button', 'pinegrap-chat-identity-invite', STR.identity_invite);
        identityInvite.type = 'button';

        identityInvite.addEventListener('click', function () {
            expandIdentity();
            nameInput.focus();
        });

        identityFields = el('div', 'pinegrap-chat-identity-fields');
        identityFields.appendChild(nameInput);
        identityFields.appendChild(emailInput);

        identityRow.appendChild(identityInvite);
        identityRow.appendChild(identityFields);
        win.appendChild(identityRow);
    }

    // The row is offered once there is a conversation to attach the details
    // to - never before the first message, and never again once both have
    // been given.
    function showIdentityRow() {
        if (!identityRow || identityComplete()) {
            return;
        }

        identityRow.classList.add('pinegrap-chat-on');
    }

    function expandIdentity() {
        if (!identityRow) {
            return;
        }

        identityRow.classList.add('pinegrap-chat-on');
        identityRow.classList.add('pinegrap-chat-expanded');
    }

    function hideIdentityRow() {
        if (identityRow) {
            identityRow.classList.remove('pinegrap-chat-on');
            identityRow.classList.remove('pinegrap-chat-expanded');
        }
    }

    function identityComplete() {
        if (config.member) {
            return true;
        }

        // The email must be VALID, not merely non-empty — an invalid format
        // is emptied server-side, producing a "filled-looking empty field".
        return !!(nameInput && nameInput.value.replace(/^\s+|\s+$/g, '') !== ''
            && emailInput && emailLooksValid(emailInput.value.replace(/^\s+|\s+$/g, '')));
    }

    function addSystemNote(text) {
        if (!text) {
            return;
        }

        var row = el('div', 'pinegrap-chat-row');
        row.appendChild(el('div', 'pinegrap-chat-msg pinegrap-chat-system', text));
        messagesBox.appendChild(row);
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    // Captcha area (anonymous only; shown on the first send). Real jigsaw
    // feel: a piece-shaped hole in a patterned background, and the dragged
    // piece carries the pattern slice belonging to that hole — the picture
    // completes when aligned.
    var captchaBox = el('div', 'pinegrap-chat-captcha');
    var captchaHint = el('div', 'pinegrap-chat-captcha-hint', STR.captcha_hint);
    var cimg = el('div', 'pinegrap-chat-cimg');
    var hole = el('div', 'pinegrap-chat-hole');
    var piece = el('div', 'pinegrap-chat-piece');
    var captchaError = el('div', 'pinegrap-chat-error', STR.captcha_fail);
    cimg.appendChild(hole);
    cimg.appendChild(piece);
    captchaBox.appendChild(captchaHint);
    captchaBox.appendChild(cimg);
    captchaBox.appendChild(captchaError);
    win.appendChild(captchaBox);

    // ── Emoji palette ───────────────────────────────────────────────────
    // Plain characters the browser already draws: no font, no sprite sheet,
    // no external library. Each category is a space separated string so a
    // multi code point emoji (a heart with its variation selector, a flag)
    // survives the split intact - splitting by character would tear them in
    // half.

    var EMOJI = [
        { tab: '\uD83D\uDE00', list: '\uD83D\uDE00 \uD83D\uDE03 \uD83D\uDE04 \uD83D\uDE01 \uD83D\uDE06 \uD83D\uDE05 \uD83E\uDD23 \uD83D\uDE02 \uD83D\uDE42 \uD83D\uDE43 \uD83D\uDE09 \uD83D\uDE0A \uD83D\uDE07 \uD83E\uDD70 \uD83D\uDE0D \uD83E\uDD29 \uD83D\uDE18 \uD83D\uDE17 \uD83D\uDE1A \uD83D\uDE0B \uD83D\uDE1B \uD83D\uDE1C \uD83E\uDD2A \uD83D\uDE1D \uD83E\uDD17 \uD83E\uDD2D \uD83E\uDD14 \uD83E\uDD10 \uD83D\uDE10 \uD83D\uDE11 \uD83D\uDE36 \uD83D\uDE0F \uD83D\uDE12 \uD83D\uDE44 \uD83D\uDE2C \uD83D\uDE34 \uD83E\uDD24 \uD83D\uDE2A \uD83D\uDE35 \uD83E\uDD2F \uD83E\uDD73 \uD83D\uDE0E \uD83E\uDD13 \uD83E\uDDD0 \uD83D\uDE15 \uD83D\uDE1F \uD83D\uDE41 \uD83D\uDE2E \uD83D\uDE2F \uD83D\uDE32 \uD83D\uDE33 \uD83E\uDD7A \uD83D\uDE26 \uD83D\uDE27 \uD83D\uDE28 \uD83D\uDE30 \uD83D\uDE25 \uD83D\uDE22 \uD83D\uDE2D \uD83D\uDE31 \uD83D\uDE16 \uD83D\uDE23 \uD83D\uDE1E \uD83D\uDE13 \uD83D\uDE29 \uD83D\uDE2B \uD83E\uDD71 \uD83D\uDE24 \uD83D\uDE21 \uD83D\uDE20 \uD83E\uDD2C \uD83D\uDE08' },
        { tab: '\uD83D\uDC4B', list: '\uD83D\uDC4B \uD83E\uDD1A \u270B \uD83D\uDD96 \uD83D\uDC4C \uD83E\uDD0F \u270C\uFE0F \uD83E\uDD1E \uD83E\uDD1F \uD83E\uDD18 \uD83E\uDD19 \uD83D\uDC48 \uD83D\uDC49 \uD83D\uDC46 \uD83D\uDC47 \u261D\uFE0F \uD83D\uDC4D \uD83D\uDC4E \u270A \uD83D\uDC4A \uD83E\uDD1B \uD83E\uDD1C \uD83D\uDC4F \uD83D\uDE4C \uD83D\uDC50 \uD83E\uDD32 \uD83E\uDD1D \uD83D\uDE4F \uD83D\uDCAA \uD83E\uDDBE \uD83D\uDC40 \uD83E\uDDE0 \uD83D\uDC76 \uD83E\uDDD2 \uD83D\uDC66 \uD83D\uDC67 \uD83E\uDDD1 \uD83D\uDC68 \uD83D\uDC69 \uD83E\uDDD3 \uD83D\uDC74 \uD83D\uDC75 \uD83D\uDC6E \uD83D\uDC77 \uD83D\uDC81 \uD83D\uDE4B \uD83D\uDE45 \uD83D\uDE46' },
        { tab: '\u2764\uFE0F', list: '\u2764\uFE0F \uD83E\uDDE1 \uD83D\uDC9B \uD83D\uDC9A \uD83D\uDC99 \uD83D\uDC9C \uD83D\uDDA4 \uD83E\uDD0D \uD83E\uDD0E \uD83D\uDC94 \u2763\uFE0F \uD83D\uDC95 \uD83D\uDC9E \uD83D\uDC93 \uD83D\uDC97 \uD83D\uDC96 \uD83D\uDC98 \uD83D\uDC9D \u2728 \u2B50 \uD83C\uDF1F \uD83D\uDCAB \u26A1 \uD83D\uDD25 \uD83D\uDCA5 \uD83D\uDCAF \u2705 \u274C \u2753 \u2757 \u26A0\uFE0F \uD83D\uDD14 \uD83D\uDD15 \uD83C\uDF89 \uD83C\uDF8A \uD83C\uDF81 \uD83C\uDFC6 \uD83E\uDD47 \uD83C\uDFAF \uD83D\uDC4D' },
        { tab: '\uD83D\uDC36', list: '\uD83D\uDC36 \uD83D\uDC31 \uD83D\uDC2D \uD83D\uDC39 \uD83D\uDC30 \uD83E\uDD8A \uD83D\uDC3B \uD83D\uDC3C \uD83D\uDC28 \uD83D\uDC2F \uD83E\uDD81 \uD83D\uDC2E \uD83D\uDC37 \uD83D\uDC38 \uD83D\uDC35 \uD83D\uDE48 \uD83D\uDE49 \uD83D\uDE4A \uD83D\uDC14 \uD83D\uDC27 \uD83D\uDC26 \uD83E\uDD86 \uD83E\uDD85 \uD83E\uDD89 \uD83D\uDC3A \uD83D\uDC17 \uD83D\uDC34 \uD83E\uDD84 \uD83D\uDC1D \uD83D\uDC1B \uD83E\uDD8B \uD83D\uDC0C \uD83D\uDC1E \uD83C\uDF38 \uD83C\uDF3A \uD83C\uDF3B \uD83C\uDF39 \uD83C\uDF37 \uD83C\uDF31 \uD83C\uDF32 \uD83C\uDF33 \uD83C\uDF40 \uD83C\uDF41 \uD83C\uDF08 \u2600\uFE0F \uD83C\uDF19 \u26C5 \u2744\uFE0F' },
        { tab: '\uD83C\uDF55', list: '\uD83C\uDF4F \uD83C\uDF4E \uD83C\uDF50 \uD83C\uDF4A \uD83C\uDF4B \uD83C\uDF4C \uD83C\uDF49 \uD83C\uDF47 \uD83C\uDF53 \uD83C\uDF52 \uD83C\uDF51 \uD83E\uDD6D \uD83C\uDF4D \uD83E\uDD5D \uD83C\uDF45 \uD83E\uDD51 \uD83C\uDF46 \uD83E\uDD55 \uD83C\uDF3D \uD83C\uDF36\uFE0F \uD83E\uDD54 \uD83C\uDF5E \uD83E\uDDC0 \uD83E\uDD5A \uD83C\uDF73 \uD83E\uDD5E \uD83E\uDDC7 \uD83E\uDD53 \uD83C\uDF54 \uD83C\uDF5F \uD83C\uDF55 \uD83C\uDF2D \uD83E\uDD6A \uD83C\uDF2E \uD83C\uDF2F \uD83E\uDD57 \uD83C\uDF5D \uD83C\uDF5C \uD83C\uDF63 \uD83C\uDF64 \uD83C\uDF66 \uD83C\uDF70 \uD83C\uDF82 \uD83C\uDF6A \uD83C\uDF6B \uD83C\uDF7F \u2615 \uD83C\uDF75 \uD83C\uDF7A \uD83C\uDF7B \uD83E\uDD42 \uD83C\uDF77' },
        { tab: '\u26BD', list: '\u26BD \uD83C\uDFC0 \uD83C\uDFC8 \u26BE \uD83C\uDFBE \uD83C\uDFD0 \uD83C\uDFC9 \uD83C\uDFB1 \uD83C\uDFD3 \uD83C\uDFF8 \uD83E\uDD45 \uD83C\uDFD2 \uD83E\uDD4A \uD83C\uDFBF \uD83C\uDFC2 \uD83D\uDEB4 \uD83C\uDFC6 \uD83C\uDFAE \uD83C\uDFB2 \uD83C\uDFB8 \uD83C\uDFA7 \uD83C\uDFA4 \uD83C\uDFAC \uD83D\uDE97 \uD83D\uDE95 \uD83D\uDE8C \uD83D\uDE91 \uD83D\uDE92 \uD83D\uDE9C \uD83C\uDFCD\uFE0F \u2708\uFE0F \uD83D\uDE80 \uD83D\uDEF8 \uD83D\uDEA2 \u26F5 \uD83C\uDFE0 \uD83C\uDFE2 \uD83C\uDFE5 \uD83C\uDFE6 \uD83D\uDDFC \uD83D\uDDFD \uD83C\uDF0D \uD83C\uDF0E \uD83C\uDF0F' },
        { tab: '\uD83D\uDCBC', list: '\uD83D\uDCBC \uD83D\uDCC1 \uD83D\uDCC2 \uD83D\uDCC4 \uD83D\uDCC3 \uD83D\uDCD1 \uD83D\uDCCA \uD83D\uDCC8 \uD83D\uDCC9 \uD83D\uDCCC \uD83D\uDCCD \uD83D\uDCCE \uD83D\uDD17 \u2702\uFE0F \uD83D\uDCCF \uD83D\uDCD0 \uD83D\uDD12 \uD83D\uDD13 \uD83D\uDD11 \uD83D\uDDDD\uFE0F \uD83D\uDD28 \u2699\uFE0F \uD83E\uDDF0 \uD83E\uDDF2 \uD83D\uDCA1 \uD83D\uDD26 \uD83D\uDD6F\uFE0F \uD83E\uDDEF \uD83D\uDED2 \uD83D\uDCB0 \uD83D\uDCB3 \uD83D\uDC8E \u2696\uFE0F \uD83D\uDD27 \uD83E\uDE9B \uD83D\uDCF1 \uD83D\uDCBB \u2328\uFE0F \uD83D\uDDA5\uFE0F \uD83D\uDDA8\uFE0F \uD83D\uDDB1\uFE0F \uD83D\uDCBE \uD83D\uDCBF \uD83D\uDCF7 \uD83D\uDCF9 \uD83D\uDCFA \uD83D\uDCFB \u23F0 \u23F3' },
        { tab: '\uD83D\uDD23', list: '\u2714\uFE0F \u2795 \u2796 \u2716\uFE0F \u2797 \uD83D\uDD34 \uD83D\uDFE0 \uD83D\uDFE1 \uD83D\uDFE2 \uD83D\uDD35 \uD83D\uDFE3 \u26AB \u26AA \uD83D\uDFE5 \uD83D\uDFE7 \uD83D\uDFE8 \uD83D\uDFE9 \uD83D\uDFE6 \uD83D\uDFEA \u2B1B \u2B1C \uD83D\uDD3A \uD83D\uDD3B \uD83D\uDD36 \uD83D\uDD37 \uD83D\uDD38 \uD83D\uDD39 \uD83D\uDD18 \u25B6\uFE0F \u23F8\uFE0F \u23F9\uFE0F \u23ED\uFE0F \u23EE\uFE0F \uD83D\uDD00 \uD83D\uDD01 \uD83D\uDD02 \uD83D\uDD3C \uD83D\uDD3D \u2B06\uFE0F \u2B07\uFE0F \u2B05\uFE0F \u27A1\uFE0F \u21A9\uFE0F \u21AA\uFE0F' }
    ];

    var RECENT_KEY = 'pinegrap_chat_emoji_recent';

    // ── Composer ────────────────────────────────────────────────────────
    // A framed box: the text area on top, the tool row (attach, emoji,
    // microphone) and the round send button underneath.

    var compose = el('div', 'pinegrap-chat-compose');
    var composer = el('div', 'pinegrap-chat-composer');
    var textarea = el('textarea');
    textarea.placeholder = MEMBER ? STR.type_a_message : STR.placeholder;
    textarea.rows = 1;

    var tools = el('div', 'pinegrap-chat-tools');

    var sendButton = el('button', 'pinegrap-chat-send');
    sendButton.type = 'button';
    sendButton.disabled = true;
    sendButton.setAttribute('aria-label', STR.send);
    sendButton.title = STR.send;
    sendButton.innerHTML = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>';

    function toolButton(label, svg) {
        var button = el('button', 'pinegrap-chat-tool');
        button.type = 'button';
        button.title = label;
        button.setAttribute('aria-label', label);
        button.innerHTML = svg;

        return button;
    }

    var ICON_ATTACH = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.4 11.1 12.3 20a5.5 5.5 0 0 1-7.8-7.8l9.2-9.1a3.7 3.7 0 0 1 5.2 5.2l-9.2 9.1a1.8 1.8 0 0 1-2.6-2.6l8.5-8.4"/></svg>';
    var ICON_EMOJI = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5a4.5 4.5 0 0 0 7 0"/><path d="M9 9.5h.01M15 9.5h.01"/></svg>';
    var ICON_MIC = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="2.5" width="6" height="11" rx="3"/><path d="M5.5 11a6.5 6.5 0 0 0 13 0M12 17.5V21"/></svg>';

    // ── The file input lives on the BODY, never in the window ───────────
    // Frontend themes restyle, wrap or replace every input[type=file] they
    // can find, and one sitting in the chat window was caught by all of
    // them. It is created for the one click that needs it, parked on the
    // body off-screen (not display:none, which some engines refuse to
    // click), and removed as soon as the dialog has answered.

    function pickFile(accept, handler) {
        var input = document.createElement('input');

        input.type = 'file';
        input.id = 'pg-chat-site-file';
        input.accept = accept || '';
        input.tabIndex = -1;
        input.setAttribute('aria-hidden', 'true');
        // Hints for theme scripts that enhance file inputs on sight.
        input.setAttribute('data-no-enhance', '1');
        input.setAttribute('data-pinegrap-chat', '1');

        // Inline !important: a stylesheet cannot outrank it, whatever the
        // theme declares.
        var style = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;opacity:0;z-index:-1;pointer-events:none';

        if (input.style.setProperty) {
            input.style.setProperty('position', 'fixed', 'important');
            input.style.setProperty('left', '-9999px', 'important');
            input.style.setProperty('top', '0', 'important');
            input.style.setProperty('width', '1px', 'important');
            input.style.setProperty('height', '1px', 'important');
            input.style.setProperty('opacity', '0', 'important');
            input.style.setProperty('z-index', '-1', 'important');
            input.style.setProperty('pointer-events', 'none', 'important');
        } else {
            input.setAttribute('style', style);
        }

        function cleanUp() {
            if (input.parentNode) {
                input.parentNode.removeChild(input);
            }
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];

            cleanUp();

            if (file) {
                handler(file);
            }
        });

        // A cancelled dialog fires nothing in older browsers, so the node is
        // also swept on the next focus that reaches the page.
        window.addEventListener('focus', function sweep() {
            window.removeEventListener('focus', sweep);
            window.setTimeout(function () {
                if (!input.files || !input.files.length) {
                    cleanUp();
                }
            }, 400);
        });

        document.body.appendChild(input);
        input.click();
    }

    var attachButton = null;
    var emojiButton = null;
    var micButton = null;
    var emojiPanel = null;

    if (config.attach && (config.attach.images || config.attach.files || config.attach.audio)) {
        attachButton = toolButton(STR.attach, ICON_ATTACH);

        attachButton.addEventListener('click', function () {
            pickFile(config.attach.accept || '', doUpload);
        });

        tools.appendChild(attachButton);
    }

    emojiButton = toolButton(STR.emoji, ICON_EMOJI);
    tools.appendChild(emojiButton);

    // The microphone appears only when the browser can actually record:
    // getUserMedia is absent outside a secure context, and MediaRecorder is
    // missing on older engines. A button that can only apologise is worse
    // than no button.
    var canRecord = !!(config.attach && config.attach.audio
        && navigator.mediaDevices && navigator.mediaDevices.getUserMedia
        && typeof window.MediaRecorder !== 'undefined');

    if (canRecord) {
        micButton = toolButton(STR.voice_message, ICON_MIC);
        tools.appendChild(micButton);
    }

    tools.appendChild(el('span', 'pinegrap-chat-tools-grow'));
    tools.appendChild(sendButton);

    // Recording row: shown in place of the tool row while the microphone is
    // live, so it cannot be armed twice.
    var recRow = el('div', 'pinegrap-chat-rec');
    var recDot = el('span', 'pinegrap-chat-rec-dot');
    var recTime = el('span', 'pinegrap-chat-rec-time', '0:00');
    var recStop = null;
    var recCancel = null;

    if (canRecord) {
        recCancel = toolButton(STR.discard_recording, '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>');
        recStop = el('button', 'pinegrap-chat-send');
        recStop.type = 'button';
        recStop.title = STR.stop_recording;
        recStop.setAttribute('aria-label', STR.stop_recording);
        recStop.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><rect x="5" y="5" width="14" height="14" rx="2.5"/></svg>';

        recRow.appendChild(recDot);
        recRow.appendChild(recTime);
        recRow.appendChild(el('span', 'pinegrap-chat-tools-grow'));
        recRow.appendChild(recCancel);
        recRow.appendChild(recStop);
    }

    composer.appendChild(textarea);
    composer.appendChild(tools);
    composer.appendChild(recRow);
    compose.appendChild(composer);

    // Emoji panel, between the messages and the composer.
    emojiPanel = el('div', 'pinegrap-chat-emoji');
    var emojiTabs = el('div', 'pinegrap-chat-emoji-tabs');
    var emojiGrid = el('div', 'pinegrap-chat-emoji-grid');
    emojiPanel.appendChild(emojiTabs);
    emojiPanel.appendChild(emojiGrid);
    win.appendChild(emojiPanel);
    win.appendChild(compose);

    // The text area grows with its content up to the CSS ceiling.
    function autoGrow() {
        // Hidden: nothing to measure, and the stylesheet is already right.
        if (!textarea.offsetHeight && !textarea.offsetParent) {
            return;
        }

        if (textarea.value === '') {
            setStyle(textarea, 'height', '');
            setStyle(textarea, 'overflow-y', 'hidden');

            return;
        }

        setStyle(textarea, 'height', 'auto');

        var wanted = textarea.scrollHeight;

        setStyle(textarea, 'height', Math.min(wanted, 96) + 'px');
        setStyle(textarea, 'overflow-y', (wanted > 96) ? 'auto' : 'hidden');
    }

    function refreshSendState() {
        sendButton.disabled = (textarea.value.replace(/^\s+|\s+$/g, '') === '');
    }

    autoGrow();

    textarea.addEventListener('focus', function () {
        composer.classList.add('pinegrap-chat-focus');
    });

    textarea.addEventListener('blur', function () {
        composer.classList.remove('pinegrap-chat-focus');
    });

    function insertAtCursor(text) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;

        if (typeof start === 'number' && typeof end === 'number') {
            textarea.value = textarea.value.slice(0, start) + text + textarea.value.slice(end);
            textarea.selectionStart = textarea.selectionEnd = start + text.length;
        } else {
            textarea.value += text;
        }

        state.lastTypedAt = Date.now();
        autoGrow();
        refreshSendState();
        textarea.focus();
    }

    function recentEmoji() {
        var raw = storage(RECENT_KEY);

        return raw ? raw.split(' ').filter(function (item) { return item !== ''; }) : [];
    }

    function rememberEmoji(character) {
        var recent = recentEmoji().filter(function (item) { return item !== character; });

        recent.unshift(character);
        storage(RECENT_KEY, recent.slice(0, 24).join(' '));
    }

    function fillEmojiGrid(list) {
        emojiGrid.innerHTML = '';

        list.forEach(function (character) {
            var button = el('button', '', character);
            button.type = 'button';

            button.addEventListener('click', function () {
                rememberEmoji(character);
                insertAtCursor(character);
            });

            emojiGrid.appendChild(button);
        });
    }

    function buildEmojiTabs() {
        var recent = recentEmoji();
        var groups = [];

        if (recent.length) {
            groups.push({ tab: '\uD83D\uDD53', list: recent, title: STR.recent });
        }

        EMOJI.forEach(function (group) {
            groups.push({ tab: group.tab, list: group.list.split(' ') });
        });

        emojiTabs.innerHTML = '';

        groups.forEach(function (group, index) {
            var tab = el('button', 'pinegrap-chat-emoji-tab', group.tab);
            tab.type = 'button';

            if (group.title) {
                tab.title = group.title;
            }

            tab.addEventListener('click', function () {
                var i;
                var all = emojiTabs.querySelectorAll('.pinegrap-chat-emoji-tab');

                for (i = 0; i < all.length; i++) {
                    all[i].className = 'pinegrap-chat-emoji-tab';
                }

                tab.className = 'pinegrap-chat-emoji-tab pinegrap-chat-on';
                fillEmojiGrid(group.list);
            });

            if (index === 0) {
                tab.className = 'pinegrap-chat-emoji-tab pinegrap-chat-on';
                fillEmojiGrid(group.list);
            }

            emojiTabs.appendChild(tab);
        });
    }

    function toggleEmoji(force) {
        if (!emojiPanel || !emojiButton) {
            return;
        }

        var open = (typeof force === 'boolean') ? force : !emojiPanel.classList.contains('pinegrap-chat-on');

        if (open) {
            buildEmojiTabs();
            emojiPanel.classList.add('pinegrap-chat-on');
            emojiButton.classList.add('pinegrap-chat-on');
        } else {
            emojiPanel.classList.remove('pinegrap-chat-on');
            emojiButton.classList.remove('pinegrap-chat-on');
        }
    }

    emojiButton.addEventListener('click', function () {
        toggleEmoji();
    });

    // ── Voice recording ─────────────────────────────────────────────────
    // Capped at two minutes: the upload ceiling is five megabytes and an
    // unattended microphone is the one way a visitor reaches it by accident.

    var RECORD_LIMIT = 120;

    var recorder = {
        instance: null,
        stream: null,
        chunks: [],
        extension: 'webm',
        started: 0,
        timer: null,
        cancelled: false
    };

    function recorderMime() {
        var candidates = [
            ['audio/webm;codecs=opus', 'webm'],
            ['audio/webm', 'webm'],
            ['audio/ogg;codecs=opus', 'ogg'],
            ['audio/ogg', 'ogg'],
            ['audio/mp4', 'm4a']
        ];
        var i;

        if (window.MediaRecorder && window.MediaRecorder.isTypeSupported) {
            for (i = 0; i < candidates.length; i++) {
                if (window.MediaRecorder.isTypeSupported(candidates[i][0])) {
                    return candidates[i];
                }
            }
        }

        // No usable answer from isTypeSupported: let the browser choose and
        // take the container it reports back.
        return ['', 'webm'];
    }

    function releaseRecorder() {
        if (recorder.timer) {
            clearInterval(recorder.timer);
            recorder.timer = null;
        }

        if (recorder.stream) {
            recorder.stream.getTracks().forEach(function (track) {
                track.stop();
            });
        }

        recorder.instance = null;
        recorder.stream = null;
        recorder.chunks = [];
        composer.classList.remove('pinegrap-chat-recording');
        autoGrow();
    }

    function tickRecorder() {
        var seconds = Math.floor((Date.now() - recorder.started) / 1000);

        recTime.textContent = Math.floor(seconds / 60) + ':' + (seconds % 60 < 10 ? '0' : '') + (seconds % 60);

        if (seconds >= RECORD_LIMIT) {
            stopRecording(false);
        }
    }

    function startRecording() {
        if (!canRecord || recorder.instance) {
            return;
        }

        if (!state.conversationId) {
            addSystemNote(STR.write_first);
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            var chosen = recorderMime();
            var options = chosen[0] ? { mimeType: chosen[0] } : undefined;

            try {
                recorder.instance = new window.MediaRecorder(stream, options);
            } catch (error) {
                try {
                    recorder.instance = new window.MediaRecorder(stream);
                } catch (inner) {
                    stream.getTracks().forEach(function (track) { track.stop(); });
                    addSystemNote(STR.mic_unsupported);
                    return;
                }
            }

            recorder.stream = stream;
            recorder.chunks = [];
            recorder.extension = chosen[1];
            recorder.cancelled = false;
            recorder.started = Date.now();

            // The engine may hand back a container other than the one that
            // was asked for; the extension follows what it actually made.
            var actual = recorder.instance.mimeType || chosen[0] || '';

            if (actual.indexOf('ogg') !== -1) {
                recorder.extension = 'ogg';
            } else if (actual.indexOf('mp4') !== -1 || actual.indexOf('m4a') !== -1 || actual.indexOf('aac') !== -1) {
                recorder.extension = 'm4a';
            } else if (actual.indexOf('webm') !== -1) {
                recorder.extension = 'webm';
            }

            recorder.instance.addEventListener('dataavailable', function (event) {
                if (event.data && event.data.size > 0) {
                    recorder.chunks.push(event.data);
                }
            });

            recorder.instance.addEventListener('stop', function () {
                var blob = recorder.chunks.length ? new Blob(recorder.chunks, { type: actual || 'audio/webm' }) : null;
                var extension = recorder.extension;
                var cancelled = recorder.cancelled;

                releaseRecorder();

                if (cancelled || !blob || blob.size === 0) {
                    return;
                }

                uploadBlob('voice-' + Date.now() + '.' + extension, blob);
            });

            recTime.textContent = '0:00';
            composer.classList.add('pinegrap-chat-recording');
            recorder.instance.start();
            recorder.timer = setInterval(tickRecorder, 500);
        }).catch(function () {
            addSystemNote(STR.mic_denied);
        });
    }

    function stopRecording(cancel) {
        if (!recorder.instance) {
            return;
        }

        recorder.cancelled = !!cancel;

        try {
            recorder.instance.stop();
        } catch (error) {
            releaseRecorder();
        }
    }

    if (canRecord) {
        micButton.addEventListener('click', function () {
            startRecording();
        });

        recStop.addEventListener('click', function () {
            stopRecording(false);
        });

        recCancel.addEventListener('click', function () {
            stopRecording(true);
        });
    }

    function showAttach() {
        // The tools are always on screen; acting on them without a
        // conversation drops a "write a message first" note instead.
        if (attachButton) {
            setStyle(attachButton, 'display', '');
        }
    }

    function uploadKind(name) {
        var dot = name.lastIndexOf('.');

        if (dot === -1) {
            return '';
        }

        var extension = name.slice(dot + 1).toLowerCase();

        if ('jpg jpeg png gif webp'.split(' ').indexOf(extension) !== -1) {
            return 'image';
        }

        if ('mp3 m4a ogg oga wav weba webm'.split(' ').indexOf(extension) !== -1) {
            return 'audio';
        }

        if ('pdf zip rar 7z doc docx xls xlsx ppt pptx txt csv'.split(' ').indexOf(extension) !== -1) {
            return 'file';
        }

        return '';
    }

    // One upload path for both a picked file and a finished recording: a
    // File is a Blob, and the recording is a Blob with a name invented for
    // it. Every check below therefore runs for both.
    function uploadBlob(name, blob) {
        if (!state.conversationId) {
            addSystemNote(STR.write_first);
            return;
        }

        var kind = uploadKind(name);

        if (kind === ''
            || (kind === 'image' && !config.attach.images)
            || (kind === 'file' && !config.attach.files)
            || (kind === 'audio' && !config.attach.audio)
        ) {
            addSystemNote(STR.file_type);
            return;
        }

        if (blob.size > config.attach.max) {
            addSystemNote(STR.file_too_big);
            return;
        }

        var reader = new FileReader();

        reader.onload = function () {
            api(MEMBER ? 'chat_attach' : 'site_chat_attach', {
                conversation_id: state.conversationId,
                name: name,
                data: reader.result
            }, function (response) {
                if (response.status !== 'success') {
                    addSystemNote(response.message || '');
                    return;
                }

                appendMessages([response.data]);
            });
        };

        reader.readAsDataURL(blob);
    }

    function doUpload(file) {
        uploadBlob(file.name, file);
    }

    root.appendChild(win);
    root.appendChild(bubble);

    // ── Scroll leak guard ───────────────────────────────────────────────
    // Scrolling inside the panel must not spill into the page: CSS
    // overscroll-behavior suffices in modern browsers; this JS fallback is
    // for the rest. Inside the message area, blocked only AT THE EDGES
    // (normal scrolling stays free); in the panel's other regions (header,
    // compose) blocked entirely — a textarea that can scroll itself is left
    // alone.
    function scrollRegion(target) {
        var regions = [messagesBox, listBox, emojiGrid];
        var i;

        for (i = 0; i < regions.length; i++) {
            if (regions[i] && regions[i].contains(target)) {
                return regions[i];
            }
        }

        return null;
    }

    function shouldBlockScroll(target, delta) {
        if (textarea === target && textarea.scrollHeight > textarea.clientHeight) {
            return false;
        }

        var region = scrollRegion(target);

        if (!region) {
            return true;
        }

        var atTop = (region.scrollTop <= 0);
        var atBottom = (region.scrollTop + region.clientHeight >= region.scrollHeight - 1);

        return ((delta < 0 && atTop) || (delta > 0 && atBottom));
    }

    win.addEventListener('wheel', function (event) {
        if (shouldBlockScroll(event.target, event.deltaY)) {
            event.preventDefault();
        }
    }, { passive: false });

    var touchLastY = 0;

    win.addEventListener('touchstart', function (event) {
        if (event.touches && event.touches.length) {
            touchLastY = event.touches[0].clientY;
        }
    }, { passive: true });

    win.addEventListener('touchmove', function (event) {
        if (!event.touches || !event.touches.length) {
            return;
        }

        var currentY = event.touches[0].clientY;
        var delta = touchLastY - currentY;
        touchLastY = currentY;

        if (shouldBlockScroll(event.target, delta)) {
            event.preventDefault();
        }
    }, { passive: false });

    // ── View updaters ───────────────────────────────────────────────────

    function setOperatorOnline(online) {
        state.operatorOnline = !!online;
        status.textContent = STR.subtitle + ' · ' + (state.operatorOnline ? STR.online : STR.offline);
        setStyle(offlineNote, 'display', state.operatorOnline ? 'none' : 'block');
    }

    // Messages arrive in two shapes: the site endpoints already say whose
    // a message is, the panel endpoints say who sent it and leave the
    // comparison to the reader. Everything past this line sees one shape.
    function normalizeMessage(message) {
        if (!MEMBER) {
            return message;
        }

        return {
            id: message.id,
            kind: message.sender_kind,
            mine: (message.sender_kind !== 'system'
                && !!config.me
                && message.sender_user_id === config.me.id),
            body: message.body,
            created_at: message.created_at,
            attachment: message.attachment
        };
    }

    // The uppercase extension on a badge rather than an icon font: a
    // frontend theme guarantees no icon set, and the extension is the one
    // label that is always right.
    function fileExtension(name) {
        var dot = (name || '').lastIndexOf('.');

        if (dot === -1) {
            return '\u2022';
        }

        return (name || '').slice(dot + 1).slice(0, 4);
    }

    function attachmentNode(attachment, hasBody) {
        if (attachment.kind === 'image') {
            var image = document.createElement('img');
            image.src = attachment.url;
            image.alt = attachment.name || '';

            if (hasBody) {
                setStyle(image, 'margin-top', '6px');
            }

            (function (url) {
                image.addEventListener('click', function () {
                    window.open(url, '_blank');
                });
            })(attachment.url);

            return image;
        }

        if (attachment.kind === 'audio') {
            // preload=metadata so a conversation full of voice notes does
            // not pull every recording down the moment it opens.
            var player = document.createElement('audio');
            player.src = attachment.url;
            player.controls = true;
            player.preload = 'metadata';

            return player;
        }

        var fileLink = document.createElement('a');
        fileLink.href = attachment.url;
        fileLink.target = '_blank';
        fileLink.rel = 'noopener';
        fileLink.className = 'pinegrap-chat-file';
        fileLink.appendChild(el('span', 'pinegrap-chat-file-ext', fileExtension(attachment.name)));
        fileLink.appendChild(el('span', 'pinegrap-chat-file-name', attachment.name || ''));

        return fileLink;
    }

    function appendMessages(list) {
        if (!list || !list.length) {
            return;
        }

        var shouldScroll = (messagesBox.scrollTop + messagesBox.clientHeight) >= (messagesBox.scrollHeight - 40);
        var i;

        for (i = 0; i < list.length; i++) {
            var message = normalizeMessage(list[i]);

            if (message.id > state.sinceId) {
                state.sinceId = message.id;
            }

            var row = el('div', 'pinegrap-chat-row');
            var cls = 'pinegrap-chat-msg ';

            if (message.kind === 'system') {
                cls += 'pinegrap-chat-system';
            } else if (message.mine) {
                cls += 'pinegrap-chat-mine';
            } else {
                cls += 'pinegrap-chat-peer';
            }

            var bubble = el('div', cls);

            if (message.body) {
                bubble.appendChild(document.createTextNode(message.body));
            }

            // Images preview, recordings get a player, everything else
            // stays a download link with its type on a badge.
            if (message.attachment && message.attachment.url) {
                bubble.appendChild(attachmentNode(message.attachment, !!message.body));
            }

            // Delivered/seen tick on own messages only: single tick means
            // it reached the server, double tick means the operator opened
            // the conversation and saw it.
            if (message.mine && message.id) {
                var tick = el('span', 'pinegrap-chat-tick', '✓');
                tick.setAttribute('data-mid', message.id);
                bubble.appendChild(tick);
            }

            row.appendChild(bubble);
            messagesBox.appendChild(row);
        }

        updateTicks();

        if (shouldScroll) {
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }
    }

    // Tick state derives from the peer's read cursor; the cursor only moves
    // forward. Called on every poll round — the cursor advances even when
    // no messages arrive.
    function updateTicks() {
        var ticks = messagesBox.querySelectorAll('.pinegrap-chat-tick');
        var i;

        for (i = 0; i < ticks.length; i++) {
            var messageId = parseInt(ticks[i].getAttribute('data-mid'), 10) || 0;
            var seen = (messageId > 0 && messageId <= state.peerReadId);

            ticks[i].textContent = seen ? '✓✓' : '✓';
            ticks[i].title = seen ? (STR.seen || '') : (STR.delivered || '');
            ticks[i].className = 'pinegrap-chat-tick' + (seen ? ' pinegrap-chat-tick-seen' : '');
        }
    }

    function showWelcome() {
        if (config.welcome && !messagesBox.firstChild) {
            var row = el('div', 'pinegrap-chat-row');
            row.appendChild(el('div', 'pinegrap-chat-msg pinegrap-chat-peer', config.welcome));
            messagesBox.appendChild(row);
        }
    }

    // ── Closed conversation ─────────────────────────────────────────────
    // When the operator closes, the compose area is hidden PERMANENTLY:
    // closing and reopening the window does not bring it back. A new
    // conversation starts only through the button below.

    var closedNote = null;

    function markConversationClosed() {
        state.conversationId = 0;
        state.sinceId = 0;
        state.closed = true;
        state.peerTyping = false;
        state.unread = 0;

        stopPoll();
        stopBgPoll();
        updateBadge();

        setStyle(compose, 'display', 'none');
        setStyle(typingLine, 'display', 'none');

        hideIdentityRow();

        if (!closedNote) {
            closedNote = el('div', 'pinegrap-chat-row');
            closedNote.appendChild(el('div', 'pinegrap-chat-msg pinegrap-chat-system', STR.closed));
            messagesBox.appendChild(closedNote);

            var newChatButton = el('button', 'pinegrap-chat-newchat', STR.new_chat);
            newChatButton.type = 'button';
            newChatButton.addEventListener('click', startNewChat);
            messagesBox.appendChild(newChatButton);

            messagesBox.scrollTop = messagesBox.scrollHeight;
        }
    }

    function startNewChat() {
        state.closed = false;
        state.conversationId = 0;
        state.sinceId = 0;
        state.peerReadId = 0;
        state.unread = 0;
        closedNote = null;

        messagesBox.textContent = '';
        setStyle(compose, 'display', '');
        setStyle(typingLine, 'display', 'none');
        updateBadge();
        showWelcome();

        // A new conversation starts clean: the invitation is offered again
        // after its first message, and the name and e-mail already typed
        // stay in the inputs so nothing has to be typed twice.
        hideIdentityRow();

        textarea.focus();
    }

    // ── Captcha (visual jigsaw) ─────────────────────────────────────────

    var captcha = { active: false, dragging: false, holeLeft: 0, pieceLeft: 0, usable: 1 };

    // Jigsaw piece: square body + one knob on top and one on the right
    // (the nonzero fill rule unions the subpaths). The hole uses the same
    // shape.
    var PIECE_PATH = 'M2 14 H34 V46 H2 Z M10 14 A8 8 0 1 0 26 14 A8 8 0 1 0 10 14 Z M26 30 A8 8 0 1 0 42 30 A8 8 0 1 0 26 30 Z';

    function applyPieceShape(node) {
        var value = 'path("' + PIECE_PATH + '")';

        setStyle(node, 'clip-path', value);
        setStyle(node, '-webkit-clip-path', value);

        // Older browsers without path() support fall back to a rounded
        // square piece — the function stays the same, only the shape is
        // simpler.
        if (!node.style.clipPath && !node.style.webkitClipPath) {
            setStyle(node, 'border-radius', '10px');
        }
    }

    applyPieceShape(hole);
    applyPieceShape(piece);

    // Pattern: a base fading from the accent color to dark + light/shadow
    // spots at pixel positions. Positions are computed from the width, so
    // the piece slice and the hole line up exactly.
    function captchaBackground(width) {
        return 'radial-gradient(circle at ' + Math.round(width * 0.18) + 'px 26px, rgba(255,255,255,.4), rgba(255,255,255,0) 34px),'
            + 'radial-gradient(circle at ' + Math.round(width * 0.62) + 'px 72px, rgba(0,0,0,.28), rgba(0,0,0,0) 42px),'
            + 'radial-gradient(circle at ' + Math.round(width * 0.85) + 'px 22px, rgba(255,255,255,.32), rgba(255,255,255,0) 30px),'
            + 'linear-gradient(115deg, var(--pinegrap-chat-accent), #334155)';
    }

    function showCaptcha(target) {
        setStyle(captchaError, 'display', 'none');
        setStyle(captchaBox, 'display', 'block');
        cimg.className = 'pinegrap-chat-cimg';

        var width = cimg.clientWidth || 280;
        var background = captchaBackground(width);

        captcha.active = true;
        captcha.usable = Math.max(1, width - 48);
        captcha.holeLeft = Math.round((target / 100) * captcha.usable);
        captcha.pieceLeft = 0;

        setStyle(cimg, 'background', background);
        setStyle(hole, 'left', captcha.holeLeft + 'px');

        // The piece carries the hole's slice (the background is shifted to
        // the hole position): dropping it in the right place completes the
        // picture.
        piece.className = 'pinegrap-chat-piece';
        setStyle(piece, 'left', '0px');
        setStyle(piece, 'background', background);
        setStyle(piece, 'background-size', width + 'px 96px');
        setStyle(piece, 'background-position', (-captcha.holeLeft) + 'px -24px');
    }

    function hideCaptcha() {
        captcha.active = false;
        setStyle(captchaBox, 'display', 'none');
    }

    function requestCaptcha() {
        api('site_chat_captcha', { op: 'challenge' }, function (response) {
            if (response.status !== 'success') {
                return;
            }

            if (response.data.locked) {
                captchaError.textContent = STR.captcha_locked;
                setStyle(captchaError, 'display', 'block');
                setStyle(captchaBox, 'display', 'block');
                return;
            }

            showCaptcha(response.data.target);
        });
    }

    function verifyCaptcha() {
        var position = (captcha.pieceLeft / captcha.usable) * 100;

        api('site_chat_captcha', { op: 'verify', position: position }, function (response) {
            if (response.status !== 'success') {
                return;
            }

            if (response.data.ok) {
                state.captchaSolved = true;

                // Snap the piece into place and close the hole: picture
                // complete.
                piece.className = 'pinegrap-chat-piece pinegrap-chat-snap';
                setStyle(piece, 'left', captcha.holeLeft + 'px');
                cimg.className = 'pinegrap-chat-cimg pinegrap-chat-solved';

                window.setTimeout(function () {
                    hideCaptcha();

                    // Send the pending message now, if any.
                    if (state.pendingBody !== '') {
                        var body = state.pendingBody;
                        state.pendingBody = '';
                        sendMessage(body);
                    }
                }, 350);

                return;
            }

            captchaError.textContent = response.data.locked ? STR.captcha_locked : STR.captcha_fail;
            setStyle(captchaError, 'display', 'block');

            if (!response.data.locked) {
                // The server burns the target on every failure; request a
                // new puzzle.
                requestCaptcha();
            }
        });
    }

    function pieceMove(clientX) {
        var rect = cimg.getBoundingClientRect();
        var x = clientX - rect.left - 24;

        if (x < 0) {
            x = 0;
        }

        if (x > captcha.usable) {
            x = captcha.usable;
        }

        captcha.pieceLeft = x;
        setStyle(piece, 'left', x + 'px');
    }

    piece.addEventListener('pointerdown', function (event) {
        if (!captcha.active) {
            return;
        }

        captcha.dragging = true;

        if (piece.setPointerCapture) {
            piece.setPointerCapture(event.pointerId);
        }

        event.preventDefault();
    });

    piece.addEventListener('pointermove', function (event) {
        if (captcha.dragging) {
            pieceMove(event.clientX);
        }
    });

    piece.addEventListener('pointerup', function () {
        if (!captcha.dragging) {
            return;
        }

        captcha.dragging = false;
        verifyCaptcha();
    });

    // ── Poll ────────────────────────────────────────────────────────────

    function isTypingActive() {
        return (textarea.value !== '' && (Date.now() - state.lastTypedAt) < 4000);
    }

    function stopPoll() {
        if (state.pollTimer) {
            clearTimeout(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function schedulePoll() {
        stopPoll();

        if (!state.open || !state.conversationId) {
            return;
        }

        var delay = ((isTypingActive() || state.peerTyping) ? 2 : 4) * 1000;

        state.pollTimer = setTimeout(function () {
            poll();
            schedulePoll();
        }, delay);
    }

    function poll() {
        if (MEMBER) {
            memberPoll();
            return;
        }

        if (!state.open || !state.conversationId || document.hidden) {
            return;
        }

        api('site_chat_poll', {
            conversation_id: state.conversationId,
            since_id: state.sinceId,
            typing: isTypingActive(),
            seen: 1
        }, function (response) {
            if (response.status !== 'success') {
                if (response.code === 'gone') {
                    // Conversation deleted/inaccessible: reset locally, the
                    // next message starts a new conversation.
                    state.conversationId = 0;
                    state.sinceId = 0;
                    stopPoll();
                }

                return;
            }

            // Ding when a new operator message arrives and the tab is not
            // focused.
            var incoming = (response.data.messages || []).some(function (message) {
                return !message.mine && message.kind !== 'system';
            });

            if (incoming && !document.hasFocus()) {
                playBeep();
            }

            // Ticks: the cursor only moves forward — max() guards against a
            // stale response.
            var peerReadId = parseInt(response.data.peer_read_id, 10) || 0;

            if (peerReadId > state.peerReadId) {
                state.peerReadId = peerReadId;
            }

            appendMessages(response.data.messages || []);
            updateTicks();
            setOperatorOnline(response.data.operator_online);

            state.peerTyping = !!response.data.peer_typing;
            setStyle(typingLine, 'display', state.peerTyping ? 'block' : 'none');

            if (response.data.status === 'closed') {
                // Operator closed: the compose area is hidden permanently; a
                // new conversation starts only through the new-chat button.
                markConversationClosed();
            }
        });
    }

    // ── Background poll (window closed) ─────────────────────────────────
    // Announces a reply through the bubble badge + sound; without it the
    // visitor would never notice. Sends seen: 0 — the server does NOT
    // advance the read cursor and fetches no message bodies (only the
    // counter returns, the lightest possible path).

    function stopBgPoll() {
        if (state.bgTimer) {
            clearTimeout(state.bgTimer);
            state.bgTimer = null;
        }
    }

    function scheduleBgPoll() {
        stopBgPoll();

        // A member is watched whether or not a conversation is open: any of
        // theirs may get a reply. A visitor has exactly one, and none of the
        // widget touches the network until they start it.
        if (state.open || (!MEMBER && !state.conversationId)) {
            return;
        }

        state.bgTimer = setTimeout(function () {
            bgPoll();
            scheduleBgPoll();
        }, (MEMBER ? (config.poll && config.poll.badge ? config.poll.badge : 60) : 20) * 1000);
    }

    function bgPoll() {
        if (MEMBER) {
            memberBgPoll();
            return;
        }

        if (state.open || !state.conversationId || document.hidden) {
            return;
        }

        api('site_chat_poll', {
            conversation_id: state.conversationId,
            since_id: state.sinceId,
            typing: 0,
            seen: 0
        }, function (response) {
            // If the window opened meanwhile, the open flow has taken over.
            if (state.open || !state.conversationId) {
                return;
            }

            if (response.status !== 'success') {
                if (response.code === 'gone') {
                    state.conversationId = 0;
                    state.sinceId = 0;
                    state.unread = 0;
                    stopBgPoll();
                    updateBadge();
                }

                return;
            }

            if (response.data.status === 'closed') {
                markConversationClosed();
                return;
            }

            var unread = parseInt(response.data.unread, 10) || 0;

            // One beep on increase; the count never drops while closed (the
            // cursor does not advance, so it cannot beep repeatedly).
            if (unread > state.unread) {
                playBeep();
            }

            state.unread = unread;
            updateBadge();
        });
    }

    // ── Sending ─────────────────────────────────────────────────────────

    function sendMessage(body) {
        if (MEMBER) {
            memberSend(body);
            return;
        }

        sendButton.disabled = true;

        api('site_chat_send', {
            conversation_id: state.conversationId,
            body: body,
            page_url: window.location.pathname + window.location.search
        }, function (response) {
            sendButton.disabled = false;

            if (response.status !== 'success') {
                if (response.code === 'captcha') {
                    state.pendingBody = body;
                    requestCaptcha();
                    return;
                }

                if (response.message) {
                    var row = el('div', 'pinegrap-chat-row');
                    row.appendChild(el('div', 'pinegrap-chat-msg pinegrap-chat-system', response.message));
                    messagesBox.appendChild(row);
                    messagesBox.scrollTop = messagesBox.scrollHeight;
                }

                return;
            }

            textarea.value = '';
            autoGrow();
            refreshSendState();
            toggleEmoji(false);
            state.conversationId = response.data.conversation_id;
            appendMessages(response.data.messages || []);
            setOperatorOnline(response.data.operator_online);

            // The first message is on its way, so there is now a
            // conversation to attach a name and an e-mail to: the invitation
            // appears, closed, under the thread.
            showIdentityRow();
            showAttach();

            schedulePoll();
        });
    }

    function sendCurrent() {
        var body = textarea.value.replace(/^\s+|\s+$/g, '');

        if (body === '') {
            return;
        }

        sendMessage(body);
    }

    sendButton.addEventListener('click', sendCurrent);

    textarea.addEventListener('input', function () {
        state.lastTypedAt = Date.now();
        autoGrow();
        refreshSendState();
    });

    textarea.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendCurrent();
        }
    });

    // ── Open / close ────────────────────────────────────────────────────

    function bootstrap() {
        api('site_chat_bootstrap', {}, function (response) {
            if (response.status !== 'success') {
                status.textContent = STR.unavailable;
                return;
            }

            state.booted = true;
            config.token = response.data.token || config.token;
            state.captchaRequired = !!response.data.captcha_required;
            setOperatorOnline(response.data.operator_online);

            if (response.data.conversation) {
                state.conversationId = response.data.conversation.id;
                state.peerReadId = parseInt(response.data.conversation.peer_read_id, 10) || 0;
                appendMessages(response.data.conversation.messages || []);
                showIdentityRow();
                showAttach();

                // Poll right away: the read cursor advances without waiting
                // (the badge and the operator's "seen" tick do not wait for
                // the first timer).
                poll();
                schedulePoll();
            } else {
                showWelcome();
            }

            state.unread = 0;
            updateBadge();
        });
    }

    // ── Member mode ─────────────────────────────────────────────────────
    // The panel's chat, rendered with the widget's own CSS. Two screens: the
    // list (conversations / people) and one conversation. Nothing here
    // decides who may talk to whom - chat_open, chat_send and chat_poll
    // apply the pairing rule and the ownership checks server-side, exactly
    // as they do for the panel window.

    function setView(view) {
        state.view = view;

        if (!MEMBER) {
            return;
        }

        root.classList.remove('pinegrap-chat-view-list');
        root.classList.remove('pinegrap-chat-view-conv');
        root.classList.add(view === 'list' ? 'pinegrap-chat-view-list' : 'pinegrap-chat-view-conv');

        toggleEmoji(false);
    }

    function presenceLabel(presence) {
        if (presence === 'online') {
            return STR.online;
        }

        return (presence === 'away') ? STR.away : STR.offline;
    }

    function avatarNode(user) {
        var wrap = el('div', 'pinegrap-chat-ava-wrap');

        if (user && user.avatar) {
            var image = document.createElement('img');
            image.className = 'pinegrap-chat-ava';
            image.src = user.avatar;
            image.alt = '';
            wrap.appendChild(image);
        } else {
            // A site visitor has no account and therefore no picture; the
            // first letter of the name they gave is better than a stranger's
            // silhouette.
            var initial = ((user && user.name) ? user.name : '?').replace(/^\s+/, '').charAt(0);
            wrap.appendChild(el('div', 'pinegrap-chat-ava-letter', initial.toUpperCase()));
        }

        if (user && user.presence) {
            wrap.appendChild(el('span', 'pinegrap-chat-dot pinegrap-chat-dot-' + user.presence));
        }

        return wrap;
    }

    function listItem(user, title, subtitle, onClick) {
        var item = el('div', 'pinegrap-chat-item');

        item.appendChild(avatarNode(user));

        var main = el('div', 'pinegrap-chat-item-main');
        main.appendChild(el('div', 'pinegrap-chat-item-name', title));
        main.appendChild(el('div', 'pinegrap-chat-item-sub', subtitle));
        item.appendChild(main);

        item.addEventListener('click', onClick);

        return item;
    }

    function renderUsers() {
        listBody.innerHTML = '';

        if (!state.users.length) {
            listBody.appendChild(el('div', 'pinegrap-chat-empty', STR.no_online_users));
            return;
        }

        state.users.forEach(function (user) {
            var subtitle = user.role_label;

            if (user.presence === 'online') {
                subtitle += ' · ' + STR.online;
            } else if (user.last_seen) {
                subtitle += ' · ' + user.last_seen;
            }

            listBody.appendChild(listItem(user, user.name, subtitle, function () {
                openWithUser(user);
            }));
        });
    }

    function renderConversations() {
        listBody.innerHTML = '';

        if (!state.conversations.length) {
            listBody.appendChild(el('div', 'pinegrap-chat-empty', STR.no_conversations));
            return;
        }

        state.conversations.forEach(function (conversation) {
            var person = conversation.peer || { name: conversation.title };
            var item = listItem(
                person,
                conversation.title,
                conversation.preview || (conversation.channel === 'site' ? STR.site : STR.panel),
                function () {
                    openConversationRow(conversation);
                }
            );

            if (conversation.unread) {
                item.className = 'pinegrap-chat-item pinegrap-chat-item-unread';
                item.appendChild(el('span', 'pinegrap-chat-item-badge'));
            }

            if (conversation.status === 'closed') {
                item.appendChild(el('span', 'pinegrap-chat-chip', STR.closed_short));
            }

            listBody.appendChild(item);
        });
    }

    function renderList() {
        title.textContent = STR.title;
        status.textContent = (state.tab === 'users') ? STR.tab_users : STR.conversations;

        tabConversations.className = 'pinegrap-chat-tab' + ((state.tab === 'conversations') ? ' pinegrap-chat-on' : '');
        tabUsers.className = 'pinegrap-chat-tab' + ((state.tab === 'users') ? ' pinegrap-chat-on' : '');

        if (state.tab === 'users') {
            renderUsers();
        } else {
            renderConversations();
        }
    }

    function applyBootstrap(data) {
        state.conversations = data.conversations || [];
        state.users = data.online_users || [];
        state.unread = parseInt(data.unread, 10) || 0;
    }

    function updateConversationHeader() {
        var conversation = state.conversation;

        if (!conversation) {
            return;
        }

        var peer = conversation.peer;

        title.textContent = peer ? peer.name : (conversation.title || STR.title);

        var line = [];

        if (peer) {
            line.push(presenceLabel(peer.presence));

            if (peer.presence !== 'online' && peer.last_seen) {
                line.push(peer.last_seen);
            }
        } else {
            line.push(STR.site);

            if (conversation.ip_address) {
                line.push(STR.ip + ' ' + conversation.ip_address);
            }
        }

        status.textContent = line.join(' · ');

        // Closing belongs to the staff side; a member never sees the button.
        // The server checks again on the action itself.
        if (state.canManage && state.conversationId > 0 && conversation.status !== 'closed') {
            headAction.className = 'pinegrap-chat-head-action pinegrap-chat-shown';
        } else {
            headAction.className = 'pinegrap-chat-head-action';
        }
    }

    function enterConversation(conversation) {
        stopPoll();
        stopListPoll();

        state.conversation = conversation;
        state.conversationId = parseInt(conversation.id, 10) || 0;
        state.sinceId = 0;
        state.peerReadId = parseInt(conversation.peer_read_id, 10) || 0;
        state.peerTyping = false;
        state.canManage = !!conversation.can_manage;
        state.closed = (conversation.status === 'closed');

        messagesBox.innerHTML = '';
        setStyle(typingLine, 'display', 'none');
        setStyle(compose, 'display', state.closed ? 'none' : '');

        setView('conversation');
        updateConversationHeader();

        if (state.closed) {
            addSystemNote(STR.conversation_closed);
        }

        // chat_open hands the messages over with the conversation; a row
        // picked from the list carries only its summary, so that one polls.
        if (conversation.messages) {
            appendMessages(conversation.messages);

            if (state.conversationId > 0) {
                api('chat_mark_read', { conversation_id: state.conversationId, since_id: state.sinceId }, function () {});
            }
        } else if (state.conversationId > 0) {
            memberPoll();
        }

        schedulePoll();
        refreshSendState();
        textarea.focus();
    }

    function openWithUser(user) {
        api('chat_open', { target_user_id: user.id }, function (response) {
            if (response.status !== 'success') {
                // The list screen is showing, where a note written into the
                // message area would never be read.
                if (response.message) {
                    window.alert(response.message);
                }

                return;
            }

            enterConversation(response.data);
        });
    }

    function openConversationRow(row) {
        enterConversation({
            id: row.id,
            channel: row.channel,
            status: row.status,
            title: row.title,
            peer: row.peer,
            ip_address: row.ip_address,
            can_manage: row.can_manage
        });
    }

    function leaveConversation() {
        stopPoll();
        state.conversation = null;
        state.conversationId = 0;
        state.sinceId = 0;
        state.closed = false;
        setStyle(compose, 'display', '');
        setView('list');
        renderList();
        refreshLists();
        scheduleListPoll();
    }

    // ── Member polling ──────────────────────────────────────────────────

    function stopListPoll() {
        if (state.listTimer) {
            clearTimeout(state.listTimer);
            state.listTimer = null;
        }
    }

    function scheduleListPoll() {
        stopListPoll();

        if (!MEMBER || !state.open || state.view !== 'list') {
            return;
        }

        state.listTimer = setTimeout(function () {
            refreshLists();
            scheduleListPoll();
        }, (config.poll && config.poll.list ? config.poll.list : 15) * 1000);
    }

    function refreshLists() {
        if (!MEMBER || document.hidden) {
            return;
        }

        api('chat_bootstrap', {}, function (response) {
            if (response.status !== 'success') {
                return;
            }

            var previous = state.unread;

            applyBootstrap(response.data);

            if (state.unread > previous && !document.hasFocus()) {
                playBeep();
            }

            if (state.view === 'list') {
                renderList();
            }

            if (!state.open) {
                updateBadge();
            }
        });
    }

    function memberPoll() {
        if (!state.open || !state.conversationId || document.hidden) {
            return;
        }

        api('chat_poll', {
            conversation_id: state.conversationId,
            since_id: state.sinceId,
            mark_read: 1,
            typing: isTypingActive()
        }, function (response) {
            if (response.status !== 'success') {
                if (response.code === 'gone') {
                    // Deleted by the other side, or no longer ours: back to
                    // the list rather than a window pointing at nothing.
                    leaveConversation();
                }

                return;
            }

            var incoming = (response.data.messages || []).some(function (message) {
                return (message.sender_kind !== 'system'
                    && (!config.me || message.sender_user_id !== config.me.id));
            });

            if (incoming && !document.hasFocus()) {
                playBeep();
            }

            var peerReadId = parseInt(response.data.peer_read_id, 10) || 0;

            if (peerReadId > state.peerReadId) {
                state.peerReadId = peerReadId;
            }

            if (response.data.peer && state.conversation) {
                state.conversation.peer = response.data.peer;
                updateConversationHeader();
            }

            appendMessages(response.data.messages || []);
            updateTicks();

            state.peerTyping = !!response.data.peer_typing;
            setStyle(typingLine, 'display', state.peerTyping ? 'block' : 'none');

            if (response.data.status === 'closed' && !state.closed) {
                state.closed = true;
                setStyle(compose, 'display', 'none');
                addSystemNote(STR.conversation_closed);
                updateConversationHeader();
            }
        });
    }

    // Window closed: one cheap count, the same endpoint the panel badge
    // uses. No message body is fetched and no read cursor moves.
    function memberBgPoll() {
        if (state.open || document.hidden) {
            return;
        }

        api('chat_unread_check', {}, function (response) {
            if (state.open || response.status !== 'success') {
                return;
            }

            var unread = parseInt(response.data.unread, 10) || 0;

            if (unread > state.unread) {
                playBeep();
            }

            state.unread = unread;
            updateBadge();
        });
    }

    function memberSend(body) {
        sendButton.disabled = true;

        var payload = { conversation_id: state.conversationId, body: body };

        // First message of a draft: the server creates the conversation row
        // with this request, so it has to be told who it is with.
        if (!state.conversationId && state.conversation && state.conversation.peer) {
            payload.target_user_id = state.conversation.peer.id;
        }

        api('chat_send', payload, function (response) {
            sendButton.disabled = false;

            if (response.status !== 'success') {
                addSystemNote(response.message || '');
                return;
            }

            if (!state.conversationId && response.data.conversation_id) {
                state.conversationId = parseInt(response.data.conversation_id, 10) || 0;

                if (state.conversation) {
                    state.conversation.id = state.conversationId;
                }
            }

            textarea.value = '';
            autoGrow();
            refreshSendState();
            toggleEmoji(false);
            appendMessages([response.data]);
            updateConversationHeader();
            schedulePoll();
        });
    }

    // ── Presence ────────────────────────────────────────────────────────
    // The panel writes user_online_timestamp on every page it loads; a
    // member who spends the afternoon on the front of the site loads none
    // of them and would sit in the staff list as offline while typing. The
    // beat runs only while the window is open - someone who never opens the
    // chat is not waiting in it either.

    function stopHeartbeat() {
        if (state.heartbeatTimer) {
            clearInterval(state.heartbeatTimer);
            state.heartbeatTimer = null;
        }
    }

    function heartbeat() {
        if (!MEMBER || document.hidden) {
            return;
        }

        api('user_online_check', {}, function () {});
    }

    function startHeartbeat() {
        stopHeartbeat();

        if (!MEMBER || !config.heartbeat) {
            return;
        }

        heartbeat();
        state.heartbeatTimer = setInterval(heartbeat, config.heartbeat * 1000);
    }

    function memberBootstrap() {
        api('chat_bootstrap', {}, function (response) {
            if (response.status !== 'success') {
                status.textContent = STR.unavailable;
                return;
            }

            state.booted = true;
            applyBootstrap(response.data);
            setView('list');
            renderList();
            scheduleListPoll();
            updateBadge();
        });
    }

    // ── Mobile: full-screen window pinned to the visible viewport ───────
    // Below the phone breakpoint the window fills the screen (CSS). A fixed
    // element is anchored to the LAYOUT viewport, which the on-screen
    // keyboard does not shrink: focusing the compose field pushes the window
    // above the visible area, and on iOS the page scrolls underneath it. The
    // visual viewport reports the box that is actually on screen, so while
    // the window is open its position and size are pinned to that box.
    // Browsers without the API keep the CSS 100dvh behavior.

    var MOBILE_BREAKPOINT = 575.98;

    function isFullscreenLayout() {
        return (window.innerWidth <= MOBILE_BREAKPOINT);
    }

    function pinWindowToViewport() {
        if (!state.open || !isFullscreenLayout()) {
            releaseWindowViewport();
            return;
        }

        var viewport = window.visualViewport;

        if (!viewport) {
            return;
        }

        setStyle(win, 'top', viewport.offsetTop + 'px');
        setStyle(win, 'left', viewport.offsetLeft + 'px');
        setStyle(win, 'width', viewport.width + 'px');
        setStyle(win, 'height', viewport.height + 'px');
    }

    function releaseWindowViewport() {
        setStyle(win, 'top', '');
        setStyle(win, 'left', '');
        setStyle(win, 'width', '');
        setStyle(win, 'height', '');
    }

    function resizeFullscreenWindow() {
        pinWindowToViewport();

        // The newest message must stay visible when the keyboard opens or
        // the device rotates.
        if (state.open) {
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }
    }

    if (window.visualViewport) {
        // resize = keyboard opened/closed or rotation; scroll = the page
        // moved under a fixed element (iOS), where only re-pinning is
        // wanted, not a jump to the newest message.
        window.visualViewport.addEventListener('resize', resizeFullscreenWindow);
        window.visualViewport.addEventListener('scroll', pinWindowToViewport);
    }

    window.addEventListener('resize', resizeFullscreenWindow);
    window.addEventListener('orientationchange', resizeFullscreenWindow);

    // The page is held still while the window is open. The in-window scroll
    // guard alone was not enough: a theme that drives scrolling itself (a
    // smooth-scroll or parallax script listening on wheel) moves the page
    // regardless of what the guard prevents, and the page slid under the
    // chat on every wheel turn. overflow:hidden on both elements keeps the
    // scroll position, so nothing jumps when the window closes again.
    function lockPageScroll(locked) {
        var nodes = [document.documentElement, document.body];
        var i;

        for (i = 0; i < nodes.length; i++) {
            if (!nodes[i] || !nodes[i].classList) {
                continue;
            }

            try {
                if (locked) {
                    nodes[i].classList.add('pinegrap-chat-no-scroll');
                } else {
                    nodes[i].classList.remove('pinegrap-chat-no-scroll');
                }
            } catch (error) {}
        }
    }

    function toggleWindow() {
        state.open = !state.open;

        if (state.open) {
            // classList rather than className: member mode keeps the current
            // screen in a class on the same element, and overwriting the
            // whole attribute would drop it.
            root.classList.add('pinegrap-chat-open');
            storage('pg_chat_site_open', '1');
            lockPageScroll(true);
            pinWindowToViewport();

            stopBgPoll();

            if (!MEMBER) {
                state.unread = 0;
            }

            updateBadge();

            // A closed conversation STAYS closed: toggling the window does
            // not bring the compose area back; a new conversation starts
            // only through the new-chat button.
            setStyle(compose, 'display', state.closed ? 'none' : '');

            if (MEMBER) {
                startHeartbeat();

                if (!state.booted) {
                    memberBootstrap();
                } else if (state.view === 'list') {
                    renderList();
                    refreshLists();
                    scheduleListPoll();
                } else {
                    memberPoll();
                    schedulePoll();
                }
            } else if (!state.booted) {
                bootstrap();
            } else {
                poll();
                schedulePoll();
            }

            textarea.focus();
        } else {
            root.classList.remove('pinegrap-chat-open');
            storage('pg_chat_site_open', '0');
            lockPageScroll(false);
            releaseWindowViewport();
            stopPoll();
            stopListPoll();
            stopHeartbeat();
            toggleEmoji(false);

            // While a conversation is active, replies are watched even when
            // closed (badge + sound).
            scheduleBgPoll();
        }
    }

    bubble.addEventListener('click', toggleWindow);

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopPoll();
            stopBgPoll();
            stopListPoll();
            return;
        }

        if (MEMBER && state.open && state.view === 'list') {
            refreshLists();
            scheduleListPoll();
            return;
        }

        if (state.open && state.conversationId) {
            poll();
            schedulePoll();
        } else if (!state.open && (MEMBER || state.conversationId)) {
            bgPoll();
            scheduleBgPoll();
        }
    });

    // Reopen after page navigation if the window was open (the
    // conversation continues too).
    if (MEMBER) {
        // The badge count is rendered with the page, so a member who never
        // opens the chat still sees that something is waiting; the
        // background count keeps it current.
        state.unread = parseInt(config.unread, 10) || 0;
        updateBadge();

        if (storage('pg_chat_site_open') === '1') {
            toggleWindow();
        } else {
            scheduleBgPoll();
        }
    } else if (storage('pg_chat_site_open') === '1') {
        toggleWindow();
    } else if (config.conversation_id) {
        // Window closed but a conversation exists: the badge count comes
        // from the server (one count at page load), replies are watched by
        // the background poll. The "no request until the bubble is clicked"
        // rule still holds for visitors WITHOUT a conversation.
        state.conversationId = parseInt(config.conversation_id, 10) || 0;
        state.unread = parseInt(config.unread, 10) || 0;
        updateBadge();
        scheduleBgPoll();
    }
})();
