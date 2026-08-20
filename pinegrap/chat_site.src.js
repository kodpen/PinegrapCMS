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

    var state = {
        open: false,
        booted: false,
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

    var bubble = el('button', 'pgcs-bubble');
    bubble.type = 'button';
    bubble.setAttribute('aria-label', STR.subtitle);

    if (iconElement) {
        bubble.innerHTML = iconElement.textContent || '';
    }

    var badge = el('span', 'pgcs-badge');
    bubble.appendChild(badge);

    // Bubble badge: shows the count while the window is closed and unread
    // messages exist.
    function updateBadge() {
        if (state.open || !(state.unread > 0)) {
            badge.style.display = 'none';
            return;
        }

        badge.textContent = (state.unread > 9) ? '9+' : String(state.unread);
        badge.style.display = 'flex';
    }

    var win = el('div', 'pgcs-window');

    var header = el('div', 'pgcs-header');
    var title = el('strong', '', STR.title);
    var status = el('div', 'pgcs-status', STR.subtitle);
    var closeButton = el('button', 'pgcs-close', '✕');
    closeButton.type = 'button';
    closeButton.addEventListener('click', function () {
        toggleWindow();
    });
    header.appendChild(title);
    header.appendChild(status);
    header.appendChild(closeButton);
    win.appendChild(header);

    var offlineNote = el('div', 'pgcs-offline-note', STR.offline_note);
    offlineNote.style.display = 'none';
    win.appendChild(offlineNote);

    var messagesBox = el('div', 'pgcs-messages');
    win.appendChild(messagesBox);

    var typingLine = el('div', 'pgcs-typing', STR.typing + '…');
    win.appendChild(typingLine);

    // Anonymous identity row (optional name/email). For attachment uploads
    // these fields become REQUIRED: when skipped, the row reappears
    // highlighted red and the pending upload resumes once filled in.
    var identityRow = null;
    var nameInput = null;
    var emailInput = null;
    var pendingUpload = null;

    if (!config.member) {
        identityRow = el('div', 'pgcs-identity');

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
                emailInput.classList.add('pgcs-required');
            }

            api('site_chat_update_identity', { name: nameInput.value, email: emailInput.value }, function () {
                if (nameInput.value.replace(/^\s+|\s+$/g, '') !== '') {
                    nameInput.classList.remove('pgcs-required');
                }

                if (emailLooksValid(emailInput.value.replace(/^\s+|\s+$/g, ''))) {
                    emailInput.classList.remove('pgcs-required');
                }

                // Resume the pending upload once the required fields are
                // complete.
                if (pendingUpload && identityComplete()) {
                    var file = pendingUpload;
                    pendingUpload = null;
                    doUpload(file);
                }
            });
        }

        nameInput.addEventListener('change', saveIdentity);
        emailInput.addEventListener('change', saveIdentity);

        // The red flag clears as soon as the field becomes valid (while
        // typing).
        nameInput.addEventListener('input', function () {
            if (nameInput.value.replace(/^\s+|\s+$/g, '') !== '') {
                nameInput.classList.remove('pgcs-required');
            }
        });

        emailInput.addEventListener('input', function () {
            if (emailLooksValid(emailInput.value.replace(/^\s+|\s+$/g, ''))) {
                emailInput.classList.remove('pgcs-required');
            }
        });

        identityRow.appendChild(nameInput);
        identityRow.appendChild(emailInput);
        win.appendChild(identityRow);

        // The identity row is not shown again to a visitor who already has
        // a conversation; it is also hidden after the first message
        // (readability).
        if (config.has_conversation) {
            identityRow.style.display = 'none';
        }
    }

    function hideIdentityRow() {
        if (identityRow) {
            identityRow.style.display = 'none';
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

    function requireIdentity() {
        if (!identityRow) {
            return;
        }

        identityRow.style.display = '';

        if (nameInput) {
            nameInput.classList.add('pgcs-required');
        }

        if (emailInput) {
            emailInput.classList.add('pgcs-required');
        }

        if (nameInput) {
            nameInput.focus();
        }
    }

    function addSystemNote(text) {
        if (!text) {
            return;
        }

        var row = el('div', 'pgcs-row');
        row.appendChild(el('div', 'pgcs-msg pgcs-system', text));
        messagesBox.appendChild(row);
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    // Captcha area (anonymous only; shown on the first send). Real jigsaw
    // feel: a piece-shaped hole in a patterned background, and the dragged
    // piece carries the pattern slice belonging to that hole — the picture
    // completes when aligned.
    var captchaBox = el('div', 'pgcs-captcha');
    var captchaHint = el('div', 'pgcs-captcha-hint', STR.captcha_hint);
    var cimg = el('div', 'pgcs-cimg');
    var hole = el('div', 'pgcs-hole');
    var piece = el('div', 'pgcs-piece');
    var captchaError = el('div', 'pgcs-error', STR.captcha_fail);
    cimg.appendChild(hole);
    cimg.appendChild(piece);
    captchaBox.appendChild(captchaHint);
    captchaBox.appendChild(cimg);
    captchaBox.appendChild(captchaError);
    win.appendChild(captchaBox);

    // Compose area.
    var compose = el('div', 'pgcs-compose');
    var textarea = el('textarea');
    textarea.placeholder = STR.placeholder;
    var sendButton = el('button', 'pgcs-send');
    sendButton.type = 'button';
    sendButton.setAttribute('aria-label', STR.send);
    sendButton.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M2 21 23 12 2 3v7l15 2-15 2z"/></svg>';

    // Attach button: always in the compose area when any type is allowed;
    // uploading requires an existing conversation (a message comes first).
    var attachButton = null;
    var attachInput = null;

    if (config.attach && (config.attach.images || config.attach.files)) {
        attachInput = document.createElement('input');
        attachInput.type = 'file';

        var acceptParts = [];

        if (config.attach.images) {
            acceptParts.push('.jpg,.jpeg,.png,.gif,.webp');
        }

        if (config.attach.files) {
            acceptParts.push('.pdf,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv');
        }

        attachInput.accept = acceptParts.join(',');
        attachInput.style.display = 'none';

        // The visible control is a plain "+" button; the raw file input is
        // always hidden (CSS !important — themes cannot override) and opens
        // when "+" is clicked.
        attachButton = el('button', 'pgcs-attach');
        attachButton.type = 'button';
        attachButton.title = STR.attach;
        attachButton.setAttribute('aria-label', STR.attach);
        attachButton.appendChild(el('span', '', '+'));

        attachButton.addEventListener('click', function () {
            attachInput.click();
        });

        attachInput.addEventListener('change', function () {
            var file = attachInput.files && attachInput.files[0];
            attachInput.value = '';

            if (file) {
                doUpload(file);
            }
        });

        compose.appendChild(attachInput);
        compose.appendChild(attachButton);
    }

    compose.appendChild(textarea);
    compose.appendChild(sendButton);
    win.appendChild(compose);

    function showAttach() {
        // "+" is always visible; clicking it without a conversation makes
        // doUpload drop a "write a message first" note.
        if (attachButton) {
            attachButton.style.display = '';
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

        if ('pdf zip rar 7z doc docx xls xlsx ppt pptx txt csv'.split(' ').indexOf(extension) !== -1) {
            return 'file';
        }

        return '';
    }

    function doUpload(file) {
        // Every new attempt cancels the previously pending upload — filling
        // the fields later can never silently send an older file.
        pendingUpload = null;

        if (!state.conversationId) {
            addSystemNote(STR.write_first);
            return;
        }

        var kind = uploadKind(file.name);

        if (kind === ''
            || (kind === 'image' && !config.attach.images)
            || (kind === 'file' && !config.attach.files)
        ) {
            addSystemNote(STR.file_type);
            return;
        }

        if (file.size > config.attach.max) {
            addSystemNote(STR.file_too_big);
            return;
        }

        // Anonymous visitors must have provided name + email for EVERY
        // attachment kind (images included): when skipped, the fields come
        // back as required and the upload resumes once filled. (Never asked
        // of members.)
        if (!identityComplete()) {
            pendingUpload = file;
            requireIdentity();
            addSystemNote(STR.file_identity);
            return;
        }

        var reader = new FileReader();

        reader.onload = function () {
            api('site_chat_attach', {
                conversation_id: state.conversationId,
                name: file.name,
                data: reader.result
            }, function (response) {
                if (response.status !== 'success') {
                    if (response.code === 'identity') {
                        pendingUpload = file;
                        requireIdentity();
                        addSystemNote(response.message || STR.file_identity);
                        return;
                    }

                    addSystemNote(response.message || '');
                    return;
                }

                appendMessages([response.data]);
            });
        };

        reader.readAsDataURL(file);
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
    function shouldBlockScroll(target, delta) {
        if (textarea === target && textarea.scrollHeight > textarea.clientHeight) {
            return false;
        }

        if (!messagesBox.contains(target)) {
            return true;
        }

        var atTop = (messagesBox.scrollTop <= 0);
        var atBottom = (messagesBox.scrollTop + messagesBox.clientHeight >= messagesBox.scrollHeight - 1);

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
        offlineNote.style.display = state.operatorOnline ? 'none' : 'block';
    }

    function appendMessages(list) {
        if (!list || !list.length) {
            return;
        }

        var shouldScroll = (messagesBox.scrollTop + messagesBox.clientHeight) >= (messagesBox.scrollHeight - 40);
        var i;

        for (i = 0; i < list.length; i++) {
            var message = list[i];

            if (message.id > state.sinceId) {
                state.sinceId = message.id;
            }

            var row = el('div', 'pgcs-row');
            var cls = 'pgcs-msg ';

            if (message.kind === 'system') {
                cls += 'pgcs-system';
            } else if (message.mine) {
                cls += 'pgcs-mine';
            } else {
                cls += 'pgcs-peer';
            }

            var bubble = el('div', cls);

            if (message.body) {
                bubble.appendChild(document.createTextNode(message.body));
            }

            // Image attachments preview inside the bubble; files are links
            // only.
            if (message.attachment && message.attachment.url) {
                if (message.attachment.kind === 'image') {
                    var image = document.createElement('img');
                    image.src = message.attachment.url;
                    image.alt = message.attachment.name || '';

                    if (message.body) {
                        image.style.marginTop = '6px';
                    }

                    (function (url) {
                        image.addEventListener('click', function () {
                            window.open(url, '_blank');
                        });
                    })(message.attachment.url);

                    bubble.appendChild(image);
                } else {
                    var fileLink = document.createElement('a');
                    fileLink.href = message.attachment.url;
                    fileLink.target = '_blank';
                    fileLink.rel = 'noopener';
                    fileLink.className = 'pgcs-file';
                    fileLink.appendChild(document.createTextNode('📎 ' + (message.attachment.name || '')));
                    bubble.appendChild(fileLink);
                }
            }

            // Delivered/seen tick on own messages only: single tick means
            // it reached the server, double tick means the operator opened
            // the conversation and saw it.
            if (message.mine && message.id) {
                var tick = el('span', 'pgcs-tick', '✓');
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
        var ticks = messagesBox.querySelectorAll('.pgcs-tick');
        var i;

        for (i = 0; i < ticks.length; i++) {
            var messageId = parseInt(ticks[i].getAttribute('data-mid'), 10) || 0;
            var seen = (messageId > 0 && messageId <= state.peerReadId);

            ticks[i].textContent = seen ? '✓✓' : '✓';
            ticks[i].title = seen ? (STR.seen || '') : (STR.delivered || '');
            ticks[i].className = 'pgcs-tick' + (seen ? ' pgcs-tick-seen' : '');
        }
    }

    function showWelcome() {
        if (config.welcome && !messagesBox.firstChild) {
            var row = el('div', 'pgcs-row');
            row.appendChild(el('div', 'pgcs-msg pgcs-peer', config.welcome));
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

        compose.style.display = 'none';
        typingLine.style.display = 'none';

        if (identityRow) {
            identityRow.style.display = 'none';
        }

        if (!closedNote) {
            closedNote = el('div', 'pgcs-row');
            closedNote.appendChild(el('div', 'pgcs-msg pgcs-system', STR.closed));
            messagesBox.appendChild(closedNote);

            var newChatButton = el('button', 'pgcs-newchat', STR.new_chat);
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
        compose.style.display = '';
        typingLine.style.display = 'none';
        updateBadge();
        showWelcome();

        // The identity row returns for a new conversation (anonymous); the
        // saved name and email stay in the inputs, no retyping needed.
        if (identityRow) {
            identityRow.style.display = '';
        }

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

        node.style.clipPath = value;
        node.style.webkitClipPath = value;

        // Older browsers without path() support fall back to a rounded
        // square piece — the function stays the same, only the shape is
        // simpler.
        if (!node.style.clipPath && !node.style.webkitClipPath) {
            node.style.borderRadius = '10px';
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
            + 'linear-gradient(115deg, var(--pgcs-accent), #334155)';
    }

    function showCaptcha(target) {
        captchaError.style.display = 'none';
        captchaBox.style.display = 'block';
        cimg.className = 'pgcs-cimg';

        var width = cimg.clientWidth || 280;
        var background = captchaBackground(width);

        captcha.active = true;
        captcha.usable = Math.max(1, width - 48);
        captcha.holeLeft = Math.round((target / 100) * captcha.usable);
        captcha.pieceLeft = 0;

        cimg.style.background = background;
        hole.style.left = captcha.holeLeft + 'px';

        // The piece carries the hole's slice (the background is shifted to
        // the hole position): dropping it in the right place completes the
        // picture.
        piece.className = 'pgcs-piece';
        piece.style.left = '0px';
        piece.style.background = background;
        piece.style.backgroundSize = width + 'px 96px';
        piece.style.backgroundPosition = (-captcha.holeLeft) + 'px -24px';
    }

    function hideCaptcha() {
        captcha.active = false;
        captchaBox.style.display = 'none';
    }

    function requestCaptcha() {
        api('site_chat_captcha', { op: 'challenge' }, function (response) {
            if (response.status !== 'success') {
                return;
            }

            if (response.data.locked) {
                captchaError.textContent = STR.captcha_locked;
                captchaError.style.display = 'block';
                captchaBox.style.display = 'block';
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
                piece.className = 'pgcs-piece pgcs-snap';
                piece.style.left = captcha.holeLeft + 'px';
                cimg.className = 'pgcs-cimg pgcs-solved';

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
            captchaError.style.display = 'block';

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
        piece.style.left = x + 'px';
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
            typingLine.style.display = state.peerTyping ? 'block' : 'none';

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

        if (state.open || !state.conversationId) {
            return;
        }

        state.bgTimer = setTimeout(function () {
            bgPoll();
            scheduleBgPoll();
        }, 20000);
    }

    function bgPoll() {
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
                    var row = el('div', 'pgcs-row');
                    row.appendChild(el('div', 'pgcs-msg pgcs-system', response.message));
                    messagesBox.appendChild(row);
                    messagesBox.scrollTop = messagesBox.scrollHeight;
                }

                return;
            }

            textarea.value = '';
            state.conversationId = response.data.conversation_id;
            appendMessages(response.data.messages || []);
            setOperatorOnline(response.data.operator_online);

            // First message sent: hide the identity row to save space, show
            // the attach button.
            hideIdentityRow();
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
                hideIdentityRow();
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

    function toggleWindow() {
        state.open = !state.open;

        if (state.open) {
            root.className = 'pgcs-open';
            storage('pg_chat_site_open', '1');

            stopBgPoll();
            state.unread = 0;
            updateBadge();

            // A closed conversation STAYS closed: toggling the window does
            // not bring the compose area back; a new conversation starts
            // only through the new-chat button.
            compose.style.display = state.closed ? 'none' : '';

            if (!state.booted) {
                bootstrap();
            } else {
                poll();
                schedulePoll();
            }

            textarea.focus();
        } else {
            root.className = '';
            storage('pg_chat_site_open', '0');
            stopPoll();

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
        } else if (state.open && state.conversationId) {
            poll();
            schedulePoll();
        } else if (!state.open && state.conversationId) {
            bgPoll();
            scheduleBgPoll();
        }
    });

    // Reopen after page navigation if the window was open (the
    // conversation continues too).
    if (storage('pg_chat_site_open') === '1') {
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
