/**
 * Pinegrap - Enterprise Website Platform — Live Chat backend panel.
 *
 * pg_chat_render_backend_launcher() (called from output_footer()) injects
 * this file into every panel page. All texts and settings come from the
 * #pg-chat-config JSON block (lang() translations happen on the PHP side);
 * this file contains NO user-visible literal text.
 *
 * Security: every server-supplied value is written to the DOM through
 * textContent / attribute assignment only. innerHTML is never used —
 * message bodies are plain text.
 *
 * Poll cadence:
 *   badge 60 s (panel closed) · lists 15 s · open conversation 5 s.
 *   All timers stop while the tab is hidden (visibilitychange).
 *
 * @author Erdal Güral (Kodpen)
 */

(function () {
    'use strict';

    var configElement = document.getElementById('pg-chat-config');
    var root = document.getElementById('pg-chat-root');

    if (!configElement || !root || typeof jQuery === 'undefined') {
        return;
    }

    var config;

    try {
        config = JSON.parse(configElement.textContent || configElement.innerText || '{}');
    } catch (error) {
        return;
    }

    var $ = jQuery;
    var STR = config.strings || {};
    var POLL = config.poll || { badge: 60, list: 15, conversation: 5 };

    var state = {
        open: false,            // is the panel visible
        tab: 'conversations',   // 'conversations' | 'online' — conversations first
        view: 'list',           // 'list' | 'conversation'
        conversation: null,     // { id, title, peer, status, sinceId, gone }
        unread: 0,
        timers: { badge: null, list: null, conversation: null },
        originalTitle: document.title
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

    function api(action, data, done, fail) {
        var payload = { action: action, token: config.token };
        var key;

        for (key in (data || {})) {
            if (Object.prototype.hasOwnProperty.call(data, key)) {
                payload[key] = data[key];
            }
        }

        $.ajax({
            url: config.api_url,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload)
        }).done(function (response) {
            if (done) {
                done(response || {});
            }
        }).fail(function () {
            if (fail) {
                fail();
            }
        });
    }

    // ── Emoji palette ───────────────────────────────────────────────────
    // Plain characters the browser already draws: no font, no sprite sheet,
    // no external library. Each category is a space separated string so a
    // multi code point emoji (a heart with its variation selector) survives
    // the split intact - splitting by character would tear it in half. The
    // same table the site widget carries; the two windows offer the same
    // palette.

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

    var EMOJI_RECENT_KEY = 'pg_chat_emoji_recent';

    function recentEmoji() {
        try {
            var raw = window.localStorage.getItem(EMOJI_RECENT_KEY);

            return raw ? raw.split(' ').filter(function (item) { return item !== ''; }) : [];
        } catch (error) {
            return [];
        }
    }

    function rememberEmoji(character) {
        var recent = recentEmoji().filter(function (item) { return item !== character; });

        recent.unshift(character);

        try {
            window.localStorage.setItem(EMOJI_RECENT_KEY, recent.slice(0, 24).join(' '));
        } catch (error) {}
    }

    // ── The file input lives on the BODY, never in the panel ────────────
    // It is created for the one click that needs it, parked on the body
    // off-screen (not display:none, which some engines treat as "not
    // clickable"), and removed as soon as the dialog has answered. Nothing
    // that styles or enhances form controls inside the panel can reach it.

    function pickFile(accept, handler) {
        var input = document.createElement('input');

        input.type = 'file';
        input.id = 'pg-chat-backend-file';
        input.accept = accept || '';
        input.tabIndex = -1;
        input.setAttribute('aria-hidden', 'true');
        input.setAttribute('data-no-enhance', '1');

        // Inline !important: no stylesheet can outrank it.
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
            input.setAttribute('style', 'position:fixed;left:-9999px;top:0;width:1px;height:1px;opacity:0;z-index:-1;pointer-events:none');
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

    // Uppercase extension on a badge rather than a per-type icon: the badge
    // is always right, an icon set never covers every extension people send.
    function fileExtension(name) {
        var dot = (name || '').lastIndexOf('.');

        if (dot === -1) {
            return '•';
        }

        return (name || '').slice(dot + 1).slice(0, 4);
    }

    // Images preview, recordings get a player, everything else stays a
    // download link.
    function attachmentNode(attachment, hasBody) {
        if (attachment.kind === 'image') {
            var image = document.createElement('img');
            image.src = attachment.url;
            image.alt = attachment.name || '';
            image.style.maxWidth = '100%';
            image.style.borderRadius = '10px';
            image.style.display = 'block';
            image.style.cursor = 'pointer';

            if (hasBody) {
                image.style.marginTop = '6px';
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
        fileLink.className = 'pg-chat-file';
        fileLink.appendChild(el('span', 'pg-chat-file-ext', fileExtension(attachment.name)));
        fileLink.appendChild(el('span', 'pg-chat-file-name', attachment.name || ''));

        return fileLink;
    }

    function formatTime(timestamp) {
        if (!timestamp) {
            return '';
        }

        var date = new Date(timestamp * 1000);
        var now = new Date();
        var sameDay = date.getFullYear() === now.getFullYear()
            && date.getMonth() === now.getMonth()
            && date.getDate() === now.getDate();

        function pad(value) {
            return (value < 10 ? '0' : '') + value;
        }

        if (sameDay) {
            return pad(date.getHours()) + ':' + pad(date.getMinutes());
        }

        return pad(date.getDate()) + '.' + pad(date.getMonth() + 1) + ' ' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    function presenceLabel(presence) {
        if (presence === 'online') {
            return STR.online;
        }
        if (presence === 'away') {
            return STR.away;
        }
        return STR.offline;
    }

    function stopTimer(name) {
        if (state.timers[name]) {
            clearInterval(state.timers[name]);
            state.timers[name] = null;
        }
    }

    // ── Notification sound ──────────────────────────────────────────────
    // No sound file: a short "ding" via Web Audio. Set up on the first user
    // interaction per the browser autoplay policy; without any interaction
    // the browser would not allow audio anyway. The preference persists in
    // localStorage and toggles from the bell icon in the panel header.
    var audio = { ctx: null };

    function soundEnabled() {
        try {
            return window.localStorage.getItem('pg_chat_sound') !== '0';
        } catch (error) {
            return true;
        }
    }

    function setSoundEnabled(enabled) {
        try {
            window.localStorage.setItem('pg_chat_sound', enabled ? '1' : '0');
        } catch (error) {}
    }

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
        if (!soundEnabled()) {
            return;
        }

        // No context yet: try to create one now (the browser allows it if
        // the page has been interacted with); if suspended, resume first,
        // then play.
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

    function updateTitle() {
        if (state.unread > 0 && !state.open) {
            document.title = '(' + state.unread + ') ' + state.originalTitle;
        } else {
            document.title = state.originalTitle;
        }
    }

    function setBadge(count) {
        state.unread = count;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
        updateTitle();
    }

    // ── Skeleton ────────────────────────────────────────────────────────

    var launcher = el('button', 'pg-chat-launcher');
    launcher.type = 'button';
    launcher.setAttribute('aria-label', STR.chat);

    var launcherIcon = el('i', 'bi bi-chat-dots-fill');
    launcher.appendChild(launcherIcon);

    var badge = el('span', 'pg-chat-badge');
    launcher.appendChild(badge);

    var panel = el('div', 'pg-chat-panel card shadow');

    root.appendChild(launcher);
    root.appendChild(panel);

    // ── List view ───────────────────────────────────────────────────────

    function renderListView(data) {
        state.view = 'list';
        panel.textContent = '';

        // Tabs live inside the header ("Users | Conversations") — no
        // separate tab row, saving vertical space. Flat header (no
        // gradient).
        var header = el('div', 'card-header border-0 d-flex align-items-center justify-content-between py-2 gap-2');

        var tabsWrap = el('ul', 'nav nav-pills pg-chat-tabs flex-nowrap mb-0');
        var onlineTab = el('li', 'nav-item');
        var onlineLink = el('a', 'nav-link' + (state.tab === 'online' ? ' active' : ''), STR.tab_users);
        onlineLink.href = 'javascript:void(0)';
        onlineTab.appendChild(onlineLink);
        var conversationsTab = el('li', 'nav-item');
        var conversationsLink = el('a', 'nav-link' + (state.tab === 'conversations' ? ' active' : ''), STR.tab_conversations);
        conversationsLink.href = 'javascript:void(0)';
        conversationsTab.appendChild(conversationsLink);
        // Conversations first (and the active tab when the panel opens).
        tabsWrap.appendChild(conversationsTab);
        tabsWrap.appendChild(onlineTab);

        onlineLink.addEventListener('click', function () {
            state.tab = 'online';
            refreshLists();
        });

        conversationsLink.addEventListener('click', function () {
            state.tab = 'conversations';
            refreshLists();
        });

        var headerActions = el('div', 'd-flex align-items-center gap-2 flex-shrink-0');

        var soundButton = el('button', 'btn btn-sm btn-link text-muted p-0');
        soundButton.type = 'button';
        soundButton.title = STR.sound;
        soundButton.appendChild(el('i', soundEnabled() ? 'bi bi-bell' : 'bi bi-bell-slash'));
        soundButton.addEventListener('click', function () {
            setSoundEnabled(!soundEnabled());
            soundButton.textContent = '';
            soundButton.appendChild(el('i', soundEnabled() ? 'bi bi-bell' : 'bi bi-bell-slash'));
        });

        var closeButton = el('button', 'btn-close');
        closeButton.type = 'button';
        closeButton.setAttribute('aria-label', STR.close);
        closeButton.addEventListener('click', togglePanel);

        headerActions.appendChild(soundButton);
        headerActions.appendChild(closeButton);
        header.appendChild(tabsWrap);
        header.appendChild(headerActions);
        panel.appendChild(header);

        var body = el('div', 'pg-chat-body bg-body-tertiary');
        panel.appendChild(body);

        if (!data) {
            body.appendChild(el('div', 'text-center text-muted small py-4', STR.loading + '...'));
            return body;
        }

        if (state.tab === 'online') {
            renderOnlineUsers(body, data.online_users || []);
        } else {
            renderConversations(body, data.conversations || []);
        }

        return body;
    }

    // Presence line with "last seen" appended for away/offline users
    // (e.g. "Offline · Last seen: 2 hours ago"). The exact timestamp goes
    // into the element tooltip via presenceTitle().
    function presenceText(user) {
        var text = presenceLabel(user.presence);

        if (user.presence !== 'online' && user.last_seen) {
            text += ' · ' + (STR.last_seen || '') + ': ' + user.last_seen;
        }

        return text;
    }

    function presenceTitle(user) {
        return user.last_seen_exact ? ((STR.last_seen || '') + ': ' + user.last_seen_exact) : '';
    }

    // Link to a user's account record, or '' when the viewer may not open
    // that screen. The permission flag is computed server-side so nobody is
    // handed a link that ends in "Access denied"; the edit page re-checks
    // on its own regardless.
    function accountHref(user) {
        var urls = config.urls || {};

        return (user.can_edit_user && urls.edit_user) ? (urls.edit_user + user.id) : '';
    }

    // Header link to a profile page. Opens in a new tab so the panel keeps
    // its place, and stops the click so the header does not act on it.
    function headerLink(iconClass, title, href) {
        var link = el('a', 'btn btn-sm btn-link text-muted p-0 me-2 pg-chat-link');

        link.href = href;
        link.target = '_blank';
        link.rel = 'noopener';
        link.title = title;
        link.setAttribute('aria-label', title);
        link.appendChild(el('i', 'bi ' + iconClass));
        link.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        return link;
    }

    // A person's picture with their presence on it, or - for a visitor who
    // has no account and therefore no picture - the first letter of the
    // name they gave.
    function avatarNode(user, fallbackName) {
        var wrap = el('div', 'position-relative flex-shrink-0');

        if (user && user.avatar) {
            var avatar = el('img', 'rounded-circle pg-chat-avatar');
            avatar.src = user.avatar;
            avatar.alt = '';
            wrap.appendChild(avatar);

            var dot = el('span', 'position-absolute rounded-circle border border-2 border-white pg-chat-dot pg-chat-dot-' + user.presence);
            dot.title = presenceLabel(user.presence);
            wrap.appendChild(dot);

            return wrap;
        }

        var source = (user && user.name) ? user.name : (fallbackName || '');
        var initial = source.replace(/^\s+/, '').charAt(0).toUpperCase();

        wrap.appendChild(el('div', 'pg-chat-letter', initial || '?'));

        return wrap;
    }

    function userRow(user, onClick) {
        var row = el('div', 'pg-chat-row');

        row.appendChild(avatarNode(user));

        var main = el('div', 'pg-chat-row-main');
        var nameLine = el('div', 'd-flex align-items-center gap-1');

        // Plain text: the whole row opens the conversation, and the links
        // to the profile pages live in that conversation's header.
        nameLine.appendChild(el('span', 'pg-chat-row-name', user.name));
        nameLine.appendChild(el('span', 'pg-chat-chip', user.role_label));
        main.appendChild(nameLine);

        // "Last seen" rides on the presence line; the full text is in the
        // tooltip when the label is too long for the row.
        var presence = el('div', 'pg-chat-row-sub', presenceText(user));
        presence.title = presenceTitle(user);
        main.appendChild(presence);

        row.appendChild(main);
        row.addEventListener('click', onClick);

        return row;
    }

    // ── AI Chat (Cloudflare AutoRAG snippet) ────────────────────────────
    // A fixed, always-online list entry for staff (role <= 2); when opened,
    // the snippet's own UI runs inside the window. The module script loads
    // once, on first open.

    var aiScriptLoaded = false;

    function ensureAiScript() {
        if (aiScriptLoaded || !config.ai || !config.ai.script) {
            return;
        }

        aiScriptLoaded = true;

        // If the page (e.g. output_header) already loaded the same module
        // script, a second copy is not added.
        try {
            if (document.querySelector('script[src="' + config.ai.script + '"]')) {
                return;
            }
        } catch (error) {}

        var moduleScript = document.createElement('script');
        moduleScript.type = 'module';
        moduleScript.src = config.ai.script;
        document.head.appendChild(moduleScript);
    }

    function aiRow() {
        var row = el('div', 'pg-chat-row');

        var avatarWrap = el('div', 'position-relative flex-shrink-0');
        var avatar = el('div', 'pg-chat-ai-avatar');
        avatar.appendChild(el('i', 'bi bi-robot'));
        avatarWrap.appendChild(avatar);

        var dot = el('span', 'position-absolute rounded-circle border border-2 border-white pg-chat-dot pg-chat-dot-online');
        dot.title = STR.online;
        avatarWrap.appendChild(dot);

        var main = el('div', 'pg-chat-row-main');
        var nameLine = el('div', 'd-flex align-items-center gap-1');
        nameLine.appendChild(el('span', 'pg-chat-row-name fw-semibold', config.ai.label));

        var badgeAi = el('span', 'badge rounded-pill text-bg-success flex-shrink-0', 'AI');
        badgeAi.style.fontSize = '10px';
        nameLine.appendChild(badgeAi);

        main.appendChild(nameLine);
        main.appendChild(el('div', 'pg-chat-row-sub', STR.online));

        row.appendChild(avatarWrap);
        row.appendChild(main);
        row.addEventListener('click', openAiView);

        return row;
    }

    function openAiView() {
        state.view = 'ai';
        state.conversation = null;
        stopTimer('list');
        stopTimer('conversation');
        ensureAiScript();

        panel.textContent = '';

        var header = el('div', 'card-header border-0 d-flex align-items-center py-2');

        var backButton = el('button', 'btn btn-sm btn-link text-muted p-0 me-2');
        backButton.type = 'button';
        backButton.title = STR.back;
        backButton.appendChild(el('i', 'bi bi-arrow-left'));
        backButton.addEventListener('click', leaveAiView);
        header.appendChild(backButton);

        var titleWrap = el('div', 'flex-grow-1 overflow-hidden');
        var titleLine = el('div', 'fw-semibold text-truncate', config.ai.label);
        titleLine.style.fontSize = '13px';
        titleWrap.appendChild(titleLine);
        titleWrap.appendChild(el('small', 'text-muted', STR.online));
        header.appendChild(titleWrap);

        panel.appendChild(header);

        // The snippet draws its own UI; the panel only hosts it.
        var frame = el('div', 'pg-chat-ai-frame bg-body');
        var snippet = document.createElement('chat-page-snippet');
        snippet.setAttribute('api-url', config.ai.api);
        snippet.setAttribute('hide-branding', 'true');
        frame.appendChild(snippet);
        panel.appendChild(frame);

        // The snippet opens with its sidebar visible, too wide for the
        // narrow panel; once ready, .toggle-sidebar-button is triggered once
        // to hide it (searched inside the shadow DOM as well; gives up
        // after 5 s).
        var sidebarTries = 0;
        var sidebarTimer = setInterval(function () {
            sidebarTries++;

            var toggle = null;

            try {
                toggle = snippet.querySelector ? snippet.querySelector('.toggle-sidebar-button') : null;

                if (!toggle && snippet.shadowRoot) {
                    toggle = snippet.shadowRoot.querySelector('.toggle-sidebar-button');
                }
            } catch (error) {
                toggle = null;
            }

            if (toggle) {
                toggle.click();
                clearInterval(sidebarTimer);
            } else if (sidebarTries > 25 || state.view !== 'ai') {
                clearInterval(sidebarTimer);
            }
        }, 200);
    }

    function leaveAiView() {
        state.view = 'list';
        renderListView(null);
        refreshLists();

        stopTimer('list');
        state.timers.list = setInterval(refreshLists, POLL.list * 1000);
    }

    function renderOnlineUsers(body, users) {
        body.textContent = '';

        // Fixed AI Chat entry: always on top and online.
        if (config.ai) {
            body.appendChild(aiRow());
        }

        if (!users.length) {
            body.appendChild(el('div', 'text-center text-muted small py-4 px-3', STR.no_online_users));
            return;
        }

        users.forEach(function (user) {
            body.appendChild(userRow(user, function () {
                openWithUser(user);
            }));
        });
    }

    function renderConversations(body, conversations) {
        body.textContent = '';

        if (!conversations.length) {
            // Empty state centered + a start button that switches to the
            // Users tab.
            var empty = el('div', 'd-flex flex-column align-items-center justify-content-center text-center gap-2 px-3');
            empty.style.height = '100%';
            empty.appendChild(el('div', 'text-muted small', STR.no_conversations));

            var startButton = el('button', 'btn btn-sm btn-primary');
            startButton.type = 'button';
            startButton.textContent = STR.start_chat;
            startButton.addEventListener('click', function () {
                state.tab = 'online';
                refreshLists();
            });

            empty.appendChild(startButton);
            body.appendChild(empty);
            return;
        }

        conversations.forEach(function (conversation) {
            var row = el('div', 'pg-chat-row' + (conversation.unread ? ' pg-chat-row-unread' : ''));

            row.appendChild(avatarNode(conversation.peer, conversation.title));

            var main = el('div', 'pg-chat-row-main');
            var nameLine = el('div', 'd-flex align-items-center gap-1');
            nameLine.appendChild(el('span', 'pg-chat-row-name', conversation.title));

            if (conversation.channel === 'site') {
                nameLine.appendChild(el('span', 'pg-chat-chip', STR.site));
            }

            // A site conversation addressed to another operator must not
            // look addressed to the viewer, so whose it is rides beside the
            // name - and only when there is a name to print. An operator
            // whose account is gone leaves an empty label, which used to be
            // drawn as a lone arrow on a line of its own.
            if (conversation.channel === 'site' && conversation.operator && conversation.operator.username
                && config.me && conversation.operator.id !== config.me.id) {
                nameLine.appendChild(el('span', 'pg-chat-chip', conversation.operator.username));
            }

            main.appendChild(nameLine);
            main.appendChild(el('div', 'pg-chat-row-sub', conversation.preview || ''));
            row.appendChild(main);

            var side = el('div', 'pg-chat-row-side');
            side.appendChild(el('div', 'pg-chat-row-time', formatTime(conversation.last_message_at)));

            if (conversation.unread) {
                side.appendChild(el('span', 'pg-chat-row-dot'));
            }

            row.appendChild(side);

            row.addEventListener('click', function () {
                openConversationView({
                    id: conversation.id,
                    title: conversation.title,
                    peer: conversation.peer,
                    operator: conversation.operator || null,
                    status: conversation.status,
                    can_manage: conversation.can_manage,
                    ip_address: conversation.ip_address || '',
                    party_email: conversation.party_email || '',
                    page_url: conversation.page_url || ''
                });
            });

            body.appendChild(row);
        });
    }

    function refreshLists() {
        if (!state.open || state.view !== 'list') {
            return;
        }

        var body = renderListView(null);

        api('chat_bootstrap', {}, function (response) {
            if (response.status !== 'success' || !state.open || state.view !== 'list') {
                return;
            }

            var unread = response.data.unread || 0;

            // A new unread conversation is audible even while the panel is
            // open on the list view.
            if (unread > state.unread) {
                playBeep();
            }

            setBadge(unread);

            if (state.tab === 'online') {
                renderOnlineUsers(body, response.data.online_users || []);
            } else {
                renderConversations(body, response.data.conversations || []);
            }
        });
    }

    // ── Conversation view ───────────────────────────────────────────────

    function openWithUser(user) {
        api('chat_open', { target_user_id: user.id }, function (response) {
            if (response.status !== 'success') {
                window.alert(response.message || '');
                return;
            }

            var data = response.data;

            openConversationView({
                id: data.id,
                title: data.peer ? data.peer.name : '',
                peer: data.peer,
                status: data.status,
                can_manage: data.can_manage,
                peer_read_id: data.peer_read_id || 0,
                messages: data.messages || []
            });
        });
    }

    function openConversationView(conversation) {
        state.view = 'conversation';
        state.conversation = {
            id: conversation.id,
            title: conversation.title,
            peer: conversation.peer || null,
            operator: conversation.operator || null,
            status: conversation.status,
            // Hierarchical authority comes from the server: the higher (or
            // equal) role closes/deletes.
            canManage: !!conversation.can_manage,
            // The peer's read cursor (delivered/seen ticks). 0 when opened
            // from the list; the first poll brings the real value.
            peerReadId: conversation.peer_read_id || 0,
            // Visitor IP (site channel only; empty on backend
            // conversations) — shown in the header subtitle.
            ipAddress: conversation.ip_address || '',
            // Visitor context (site channel only): email in the subtitle,
            // originating page in its tooltip.
            partyEmail: conversation.party_email || '',
            pageUrl: conversation.page_url || '',
            sinceId: 0,
            gone: false
        };

        stopTimer('list');
        panel.textContent = '';

        // Header: back + name/presence + manage buttons (flat header, no
        // gradient).
        var header = el('div', 'card-header border-0 d-flex align-items-center py-2');

        var backButton = el('button', 'btn btn-sm btn-link text-muted p-0 me-2');
        backButton.type = 'button';
        backButton.title = STR.back;
        backButton.appendChild(el('i', 'bi bi-arrow-left'));
        backButton.addEventListener('click', function () {
            leaveConversationView();
        });
        header.appendChild(backButton);

        var titleWrap = el('div', 'flex-grow-1 overflow-hidden');
        var titleLine = el('div', 'fw-semibold text-truncate');
        titleLine.style.fontSize = '13px';

        // Header title: name/surname links to the linked contact's edit
        // page, the small @username next to it links to the account's edit
        // page — each rendered only when the viewer has the permission.
        var headerPeer = state.conversation.peer;

        titleLine.textContent = state.conversation.title;

        if (headerPeer && headerPeer.username) {
            var accountName = el('span', 'text-muted ms-1', '@' + headerPeer.username);
            accountName.style.fontSize = '11px';
            accountName.style.fontWeight = 'normal';
            titleLine.appendChild(accountName);
        }
        var presenceLine = el('small', 'text-muted');
        if (state.conversation.peer) {
            presenceLine.textContent = presenceText(state.conversation.peer);
            presenceLine.title = presenceTitle(state.conversation.peer);
        } else {
            // Site conversation subtitle: target operator (when it belongs
            // to another operator) + the visitor's IP address.
            var subtitleParts = [];

            if (state.conversation.operator && config.me && state.conversation.operator.id !== config.me.id) {
                // The site conversation belongs to another operator: make it
                // clear in the header too.
                subtitleParts.push('→ ' + state.conversation.operator.username);
            }

            if (state.conversation.partyEmail) {
                subtitleParts.push(state.conversation.partyEmail);
            }

            if (state.conversation.ipAddress) {
                subtitleParts.push((STR.ip || 'IP') + ': ' + state.conversation.ipAddress);
            }

            presenceLine.textContent = subtitleParts.join(' · ');

            // Which page the visitor started the chat from, on hover.
            if (state.conversation.pageUrl) {
                presenceLine.title = (STR.page || '') + ': ' + state.conversation.pageUrl;
            }
        }
        titleWrap.appendChild(titleLine);
        titleWrap.appendChild(presenceLine);
        header.appendChild(titleWrap);

        // Contact card and user account, for the viewers whose role lets
        // them open those screens. The flags come from the server and the
        // screens check again for themselves.
        if (headerPeer) {
            var contactHref = (headerPeer.can_edit_contact && headerPeer.contact_id && (config.urls || {}).edit_contact)
                ? config.urls.edit_contact + headerPeer.contact_id
                : '';

            if (contactHref) {
                header.appendChild(headerLink('bi-person-vcard', STR.edit_contact, contactHref));
            }

            var userHref = accountHref(headerPeer);

            if (userHref) {
                header.appendChild(headerLink('bi-person-gear', STR.edit_user, userHref));
            }
        }

        if (state.conversation.canManage) {
            var closeConversationButton = el('button', 'btn btn-sm btn-link text-muted p-0 me-2');
            closeConversationButton.type = 'button';
            closeConversationButton.title = STR.close_conversation;
            closeConversationButton.appendChild(el('i', 'bi bi-check2-circle'));
            closeConversationButton.addEventListener('click', function () {
                // A draft has no server row; closing just leaves the view.
                if (!state.conversation || state.conversation.id === 0) {
                    leaveConversationView();
                    return;
                }
                api('chat_close', { conversation_id: state.conversation.id }, function () {
                    leaveConversationView();
                });
            });
            header.appendChild(closeConversationButton);

            var deleteButton = el('button', 'btn btn-sm btn-link text-danger p-0');
            deleteButton.type = 'button';
            deleteButton.title = STR.delete_conversation;
            deleteButton.appendChild(el('i', 'bi bi-trash'));
            deleteButton.addEventListener('click', confirmDelete);
            header.appendChild(deleteButton);
        }

        panel.appendChild(header);

        var body = el('div', 'pg-chat-body bg-body-tertiary px-2 py-2');
        panel.appendChild(body);

        // ── Composer ────────────────────────────────────────────────────
        // A framed box: the text area on top, the tool row (attach, emoji,
        // microphone) and the round send button underneath. The same shape
        // the site widget draws, so the two windows read as one feature.
        var compose = el('div', 'card-footer p-2 pg-chat-compose');
        var composer = el('div', 'pg-chat-composer');
        var textarea = el('textarea');
        textarea.placeholder = STR.type_a_message;
        textarea.rows = 1;

        var tools = el('div', 'pg-chat-tools');

        var sendButton = el('button', 'pg-chat-send');
        sendButton.type = 'button';
        sendButton.title = STR.send;
        sendButton.setAttribute('aria-label', STR.send);
        sendButton.disabled = true;
        sendButton.appendChild(el('i', 'bi bi-arrow-up'));

        function toolButton(label, iconClass) {
            var button = el('button', 'pg-chat-tool');
            button.type = 'button';
            button.title = label;
            button.setAttribute('aria-label', label);
            button.appendChild(el('i', 'bi ' + iconClass));

            return button;
        }

        // Attach: always on screen (uploading still needs an existing
        // conversation, so a draft gets a note rather than a dialog).
        var attachButton = null;

        if (config.attach && config.attach.enabled) {
            attachButton = toolButton(STR.attach, 'bi-paperclip');

            attachButton.addEventListener('click', function () {
                // A draft has no row to hang an attachment on; the
                // conversation is created by the first message.
                if (!state.conversation || state.conversation.id === 0) {
                    window.alert(STR.write_first);
                    return;
                }

                pickFile(config.attach.accept || '', uploadFile);
            });

            tools.appendChild(attachButton);
        }

        var emojiButton = toolButton(STR.emoji, 'bi-emoji-smile');
        tools.appendChild(emojiButton);

        // The microphone appears only when the browser can actually record:
        // getUserMedia is absent outside a secure context and MediaRecorder
        // is missing on older engines. A button that can only apologise is
        // worse than no button.
        var canRecord = !!(config.attach && config.attach.enabled && config.attach.audio
            && navigator.mediaDevices && navigator.mediaDevices.getUserMedia
            && typeof window.MediaRecorder !== 'undefined');

        var micButton = canRecord ? toolButton(STR.voice_message, 'bi-mic') : null;

        if (canRecord) {
            tools.appendChild(micButton);
        }

        tools.appendChild(el('span', 'pg-chat-tools-grow'));
        tools.appendChild(sendButton);

        // Recording row: shown in place of the tool row while the microphone
        // is live, so it cannot be armed twice.
        var recRow = el('div', 'pg-chat-rec');
        var recTime = el('span', 'pg-chat-rec-time', '0:00');
        var recStop = null;
        var recCancel = null;

        if (canRecord) {
            recCancel = toolButton(STR.discard_recording, 'bi-x-lg');
            recStop = el('button', 'pg-chat-send');
            recStop.type = 'button';
            recStop.title = STR.stop_recording;
            recStop.setAttribute('aria-label', STR.stop_recording);
            recStop.appendChild(el('i', 'bi bi-stop-fill'));

            recRow.appendChild(el('span', 'pg-chat-rec-dot'));
            recRow.appendChild(recTime);
            recRow.appendChild(el('span', 'pg-chat-tools-grow'));
            recRow.appendChild(recCancel);
            recRow.appendChild(recStop);
        }

        composer.appendChild(textarea);
        composer.appendChild(tools);
        composer.appendChild(recRow);
        compose.appendChild(composer);

        // Emoji panel, between the messages and the composer.
        var emojiPanel = el('div', 'pg-chat-emoji');
        var emojiTabs = el('div', 'pg-chat-emoji-tabs');
        var emojiGrid = el('div', 'pg-chat-emoji-grid');
        emojiPanel.appendChild(emojiTabs);
        emojiPanel.appendChild(emojiGrid);

        panel.appendChild(emojiPanel);
        panel.appendChild(compose);

        function autoGrow() {
            // Hidden: nothing to measure, and the stylesheet is already
            // right.
            if (!textarea.offsetHeight && !textarea.offsetParent) {
                return;
            }

            if (textarea.value === '') {
                textarea.style.height = '';
                textarea.style.overflowY = 'hidden';

                return;
            }

            textarea.style.height = 'auto';

            var wanted = textarea.scrollHeight;

            textarea.style.height = Math.min(wanted, 96) + 'px';
            textarea.style.overflowY = (wanted > 96) ? 'auto' : 'hidden';
        }

        function refreshSendState() {
            sendButton.disabled = (textarea.value.replace(/^\s+|\s+$/g, '') === '');
        }

        autoGrow();

        function insertAtCursor(text) {
            var start = textarea.selectionStart;
            var end = textarea.selectionEnd;

            if (typeof start === 'number' && typeof end === 'number') {
                textarea.value = textarea.value.slice(0, start) + text + textarea.value.slice(end);
                textarea.selectionStart = textarea.selectionEnd = start + text.length;
            } else {
                textarea.value += text;
            }

            if (state.conversation) {
                state.conversation._lastTypedAt = Date.now();
            }

            autoGrow();
            refreshSendState();
            textarea.focus();
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
                var tab = el('button', 'pg-chat-emoji-tab', group.tab);
                tab.type = 'button';

                if (group.title) {
                    tab.title = group.title;
                }

                tab.addEventListener('click', function () {
                    var all = emojiTabs.querySelectorAll('.pg-chat-emoji-tab');
                    var i;

                    for (i = 0; i < all.length; i++) {
                        all[i].className = 'pg-chat-emoji-tab';
                    }

                    tab.className = 'pg-chat-emoji-tab pg-chat-tool-on';
                    fillEmojiGrid(group.list);
                });

                if (index === 0) {
                    tab.className = 'pg-chat-emoji-tab pg-chat-tool-on';
                    fillEmojiGrid(group.list);
                }

                emojiTabs.appendChild(tab);
            });
        }

        function toggleEmoji(force) {
            var open = (typeof force === 'boolean') ? force : !emojiPanel.classList.contains('pg-chat-emoji-on');

            if (open) {
                buildEmojiTabs();
                emojiPanel.classList.add('pg-chat-emoji-on');
                emojiButton.classList.add('pg-chat-tool-on');
            } else {
                emojiPanel.classList.remove('pg-chat-emoji-on');
                emojiButton.classList.remove('pg-chat-tool-on');
            }
        }

        emojiButton.addEventListener('click', function () {
            toggleEmoji();
        });

        // ── Upload ──────────────────────────────────────────────────────
        // One path for a picked file and for a finished recording: a File is
        // a Blob, and the recording is a Blob with a name invented for it.

        function uploadBlob(name, blob) {
            if (!state.conversation || state.conversation.id === 0) {
                return;
            }

            if (blob.size > config.attach.max) {
                window.alert(STR.file_too_big);
                return;
            }

            var reader = new FileReader();

            reader.onload = function () {
                if (attachButton) {
                    attachButton.disabled = true;
                }

                api('chat_attach', {
                    conversation_id: state.conversation.id,
                    name: name,
                    data: reader.result
                }, function (response) {
                    if (attachButton) {
                        attachButton.disabled = false;
                    }

                    if (response.status !== 'success') {
                        window.alert(response.message || '');
                        return;
                    }

                    appendMessages(body, [response.data]);
                }, function () {
                    if (attachButton) {
                        attachButton.disabled = false;
                    }
                });
            };

            reader.readAsDataURL(blob);
        }

        function uploadFile(file) {
            uploadBlob(file.name, file);
        }

        // ── Voice recording ─────────────────────────────────────────────
        // Capped at two minutes: the upload ceiling is five megabytes and an
        // unattended microphone is the one way it is reached by accident.

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

            // No usable answer from isTypeSupported: let the browser choose
            // and take the container it reports back.
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
            composer.classList.remove('pg-chat-recording');
            autoGrow();
        }

        function tickRecorder() {
            var seconds = Math.floor((Date.now() - recorder.started) / 1000);

            recTime.textContent = Math.floor(seconds / 60) + ':' + (seconds % 60 < 10 ? '0' : '') + (seconds % 60);

            if (seconds >= RECORD_LIMIT) {
                stopRecording(false);
            }
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

        function startRecording() {
            if (!canRecord || recorder.instance) {
                return;
            }

            if (!state.conversation || state.conversation.id === 0) {
                window.alert(STR.write_first);
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
                        window.alert(STR.mic_unsupported);
                        return;
                    }
                }

                recorder.stream = stream;
                recorder.chunks = [];
                recorder.extension = chosen[1];
                recorder.cancelled = false;
                recorder.started = Date.now();

                // The engine may hand back a container other than the one it
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
                composer.classList.add('pg-chat-recording');
                recorder.instance.start();
                recorder.timer = setInterval(tickRecorder, 500);
            }).catch(function () {
                window.alert(STR.mic_denied);
            });
        }

        if (canRecord) {
            micButton.addEventListener('click', startRecording);
            recStop.addEventListener('click', function () { stopRecording(false); });
            recCancel.addEventListener('click', function () { stopRecording(true); });
        }

        function sendCurrent() {
            var body_text = textarea.value.replace(/^\s+|\s+$/g, '');

            if (body_text === '' || state.conversation.gone) {
                return;
            }

            sendButton.disabled = true;

            var payload = { conversation_id: state.conversation.id, body: body_text };

            // First message from a draft: the server creates the
            // conversation row with this request.
            if (state.conversation.id === 0 && state.conversation.peer) {
                payload.target_user_id = state.conversation.peer.id;
            }

            api('chat_send', payload, function (response) {
                sendButton.disabled = false;

                if (response.status !== 'success') {
                    window.alert(response.message || '');
                    return;
                }

                // The draft became a real conversation: take the id and
                // start the poll loop. The tools were on screen all along;
                // they simply had nothing to attach to until now.
                if (state.conversation.id === 0 && response.data.conversation_id) {
                    state.conversation.id = response.data.conversation_id;
                    scheduleConversationPoll(body);
                }

                textarea.value = '';
                autoGrow();
                refreshSendState();
                toggleEmoji(false);
                appendMessages(body, [response.data]);
            }, function () {
                sendButton.disabled = false;
            });
        }

        sendButton.addEventListener('click', sendCurrent);
        textarea.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                sendCurrent();
            }
        });

        // References for the typing signal (the poll reads/updates them)
        // and for the mobile viewport lock (keeps the newest message
        // visible when the keyboard opens).
        state.conversation._body = body;
        state.conversation._textarea = textarea;
        state.conversation._presence = presenceLine;
        state.conversation._presenceDefault = presenceLine.textContent;
        state.conversation._compose = compose;
        state.conversation._lastTypedAt = 0;
        state.conversation.peerTyping = false;

        // The compose area is not shown on a closed conversation.
        if (state.conversation.status === 'closed') {
            compose.style.display = 'none';
        }

        textarea.addEventListener('input', function () {
            if (state.conversation) {
                state.conversation._lastTypedAt = Date.now();
            }

            autoGrow();
            refreshSendState();
        });

        // Initial load: messages are ready when coming from chat_open; when
        // coming from the list, poll. A draft (id=0) has no server row yet:
        // neither poll nor mark-read is called.
        if (conversation.messages) {
            appendMessages(body, conversation.messages);
            if (state.conversation.id > 0) {
                markConversationRead();
            }
        } else {
            pollConversation(body);
        }

        scheduleConversationPoll(body);

        textarea.focus();
    }

    function confirmDelete() {
        // Nothing to delete on a draft; just leave the view.
        if (!state.conversation || state.conversation.id === 0) {
            leaveConversationView();
            return;
        }

        function reallyDelete() {
            api('chat_delete', { conversation_id: state.conversation.id }, function () {
                leaveConversationView();
            });
        }

        if (typeof window.pgConfirm === 'function') {
            window.pgConfirm({
                title: STR.delete_conversation,
                message: STR.delete_confirm,
                confirmText: STR.delete,
                cancelText: STR.cancel,
                variant: 'danger'
            }).then(function (confirmed) {
                if (confirmed) {
                    reallyDelete();
                }
            });
        } else if (window.confirm(STR.delete_confirm)) {
            reallyDelete();
        }
    }

    function appendMessages(body, messages) {
        var shouldScroll = (body.scrollTop + body.clientHeight) >= (body.scrollHeight - 40);

        messages.forEach(function (message) {
            if (message.id > state.conversation.sinceId) {
                state.conversation.sinceId = message.id;
            }

            var line = el('div', 'd-flex mb-1');
            var bubbleClass = 'pg-chat-bubble ';
            var mine = false;

            if (message.sender_kind === 'system') {
                bubbleClass += 'pg-chat-bubble-system mx-auto';
            } else if (config.me && message.sender_user_id === config.me.id) {
                bubbleClass += 'pg-chat-bubble-me ms-auto';
                mine = true;
            } else {
                bubbleClass += 'pg-chat-bubble-peer me-auto';
            }

            var bubble = el('div', bubbleClass);

            if (message.body) {
                bubble.appendChild(document.createTextNode(message.body));
            }

            // Images preview, recordings get a player, everything else
            // stays a download link with its type on a badge.
            if (message.attachment && message.attachment.url) {
                bubble.appendChild(attachmentNode(message.attachment, !!message.body));
            }

            // Delivered/seen tick on own messages only: single tick means it
            // reached the server, double tick means the peer opened the
            // conversation (while focused).
            if (mine && message.id) {
                var tick = el('span', 'pg-chat-tick', '✓');
                tick.setAttribute('data-mid', message.id);
                bubble.appendChild(tick);
            }

            bubble.title = formatTime(message.created_at);
            line.appendChild(bubble);
            body.appendChild(line);
        });

        updateTicks(body);

        if (messages.length && shouldScroll) {
            body.scrollTop = body.scrollHeight;
        }
    }

    // Refreshes tick state against the peer's read cursor. The cursor only
    // moves forward; the poll calls this every round (the cursor advances
    // even without new messages).
    function updateTicks(body) {
        if (!state.conversation) {
            return;
        }

        var peerReadId = state.conversation.peerReadId || 0;
        var ticks = body.querySelectorAll('.pg-chat-tick');
        var i;

        for (i = 0; i < ticks.length; i++) {
            var messageId = parseInt(ticks[i].getAttribute('data-mid'), 10) || 0;
            var seen = (messageId > 0 && messageId <= peerReadId);

            ticks[i].textContent = seen ? '✓✓' : '✓';
            ticks[i].title = seen ? (STR.seen || '') : (STR.delivered || '');
            ticks[i].className = 'pg-chat-tick' + (seen ? ' pg-chat-tick-seen' : '');
        }
    }

    function isTypingActive() {
        return !!(state.conversation
            && state.conversation._textarea
            && state.conversation._textarea.value !== ''
            && (Date.now() - state.conversation._lastTypedAt) < 4000);
    }

    // The conversation poll schedules itself: drops to 2 s while someone is
    // typing (livelier typing indicator and messages), returns to the base
    // cadence (5 s) when calm.
    function scheduleConversationPoll(body) {
        stopTimer('conversation');

        if (!state.open || !state.conversation || state.conversation.id === 0 || state.conversation.gone) {
            return;
        }

        var delay = ((isTypingActive() || state.conversation.peerTyping) ? 2 : POLL.conversation) * 1000;

        state.timers.conversation = setTimeout(function () {
            pollConversation(body);
            scheduleConversationPoll(body);
        }, delay);
    }

    function markConversationRead() {
        api('chat_mark_read', { conversation_id: state.conversation.id, since_id: state.conversation.sinceId }, function () {});
    }

    function pollConversation(body) {
        if (!state.open || state.view !== 'conversation' || !state.conversation || state.conversation.gone || state.conversation.id === 0) {
            return;
        }

        var markRead = document.hasFocus();

        api('chat_poll', {
            conversation_id: state.conversation.id,
            since_id: state.conversation.sinceId,
            mark_read: markRead,
            typing: isTypingActive()
        }, function (response) {
            if (!state.conversation) {
                return;
            }

            // If the peer deleted the conversation, end the window
            // gracefully.
            if (response.status !== 'success') {
                if (response.code === 'gone') {
                    state.conversation.gone = true;
                    appendMessages(body, [{ id: state.conversation.sinceId, sender_kind: 'system', sender_user_id: 0, body: response.message || '', created_at: Math.floor(Date.now() / 1000) }]);
                    stopTimer('conversation');
                }
                return;
            }

            // Ding when a new peer message arrives and the tab is not
            // focused.
            var incoming = (response.data.messages || []).some(function (message) {
                return message.sender_kind !== 'system' && (!config.me || message.sender_user_id !== config.me.id);
            });

            if (incoming && !document.hasFocus()) {
                playBeep();
            }

            // Ticks: the cursor only moves forward — max() guards against a
            // stale response.
            var peerReadId = parseInt(response.data.peer_read_id, 10) || 0;

            if (peerReadId > (state.conversation.peerReadId || 0)) {
                state.conversation.peerReadId = peerReadId;
            }

            appendMessages(body, response.data.messages || []);

            state.conversation.peerTyping = !!response.data.peer_typing;

            if (state.conversation._presence) {
                if (state.conversation.peerTyping) {
                    state.conversation._presence.textContent = STR.typing + '…';
                } else if (response.data.peer) {
                    state.conversation._presence.textContent = presenceText(response.data.peer);
                    state.conversation._presence.title = presenceTitle(response.data.peer);
                } else if (typeof state.conversation._presenceDefault === 'string') {
                    // Site conversations have no peer; when typing ends, the
                    // header falls back to its default label (e.g. the
                    // operator marker).
                    state.conversation._presence.textContent = state.conversation._presenceDefault;
                }
            }

            if (response.data.status === 'closed' && state.conversation.status !== 'closed') {
                state.conversation.status = 'closed';

                // Conversation closed: hide the compose area.
                if (state.conversation._compose) {
                    state.conversation._compose.style.display = 'none';
                }
            }
        });
    }

    function leaveConversationView() {
        stopTimer('conversation');
        state.conversation = null;
        state.tab = 'conversations';
        renderListView(null);
        refreshLists();

        stopTimer('list');
        state.timers.list = setInterval(refreshLists, POLL.list * 1000);
    }

    // ── Panel open/close + badge loop ───────────────────────────────────

    // ── Mobile: full-screen panel pinned to the visible viewport ────────
    // Below the phone breakpoint the panel fills the screen (CSS). A fixed
    // element is anchored to the LAYOUT viewport, which the on-screen
    // keyboard does not shrink: focusing the compose field pushes the window
    // above the visible area, and on iOS the page scrolls underneath it. The
    // visual viewport reports the box that is actually on screen, so while
    // the panel is open its position and size are pinned to that box.
    // Browsers without the API keep the CSS 100dvh behavior.

    var MOBILE_BREAKPOINT = 575.98;

    function isFullscreenLayout() {
        return (window.innerWidth <= MOBILE_BREAKPOINT);
    }

    function pinPanelToViewport() {
        if (!state.open || !isFullscreenLayout()) {
            releasePanelViewport();
            return;
        }

        root.classList.add('pg-chat-fullscreen');
        document.body.classList.add('pg-chat-no-scroll');

        var viewport = window.visualViewport;

        if (!viewport) {
            return;
        }

        panel.style.top = viewport.offsetTop + 'px';
        panel.style.left = viewport.offsetLeft + 'px';
        panel.style.width = viewport.width + 'px';
        panel.style.height = viewport.height + 'px';
    }

    function releasePanelViewport() {
        root.classList.remove('pg-chat-fullscreen');
        document.body.classList.remove('pg-chat-no-scroll');
        panel.style.top = '';
        panel.style.left = '';
        panel.style.width = '';
        panel.style.height = '';
    }

    // The newest message must stay visible when the keyboard opens or the
    // device rotates; the shrinking box would otherwise leave the view
    // parked mid-history.
    function keepMessagesInView() {
        if (state.view !== 'conversation' || !state.conversation || !state.conversation._body) {
            return;
        }

        var messages = state.conversation._body;

        messages.scrollTop = messages.scrollHeight;
    }

    function resizeFullscreenPanel() {
        pinPanelToViewport();
        keepMessagesInView();
    }

    if (window.visualViewport) {
        // resize = keyboard opened/closed or rotation; scroll = the page
        // moved under a fixed element (iOS), where only re-pinning is
        // wanted, not a jump to the newest message.
        window.visualViewport.addEventListener('resize', resizeFullscreenPanel);
        window.visualViewport.addEventListener('scroll', pinPanelToViewport);
    }

    window.addEventListener('resize', resizeFullscreenPanel);
    window.addEventListener('orientationchange', resizeFullscreenPanel);

    function togglePanel() {
        state.open = !state.open;

        if (state.open) {
            panel.classList.add('pg-chat-open');
            pinPanelToViewport();
            state.tab = 'conversations';
            renderListView(null);
            refreshLists();
            stopTimer('badge');
            stopTimer('list');
            state.timers.list = setInterval(refreshLists, POLL.list * 1000);
        } else {
            panel.classList.remove('pg-chat-open');
            releasePanelViewport();
            stopTimer('list');
            stopTimer('conversation');
            state.view = 'list';
            state.conversation = null;
            startBadgeLoop();
        }

        updateTitle();
    }

    function checkUnread() {
        api('chat_unread_check', {}, function (response) {
            if (response.status === 'success') {
                var unread = response.data.unread || 0;

                // Ding when a new unread conversation arrives.
                if (unread > state.unread) {
                    playBeep();
                }

                setBadge(unread);
            }
        });
    }

    function startBadgeLoop() {
        stopTimer('badge');
        checkUnread();
        state.timers.badge = setInterval(checkUnread, POLL.badge * 1000);
    }

    launcher.addEventListener('click', togglePanel);

    // No timer runs while the tab is hidden; everything resumes where it
    // left off when visible again.
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopTimer('badge');
            stopTimer('list');
            stopTimer('conversation');
        } else {
            if (state.open) {
                if (state.view === 'conversation' && state.conversation && state.conversation.id > 0) {
                    var body = panel.querySelector('.pg-chat-body');
                    pollConversation(body);
                    scheduleConversationPoll(body);
                } else if (state.view === 'list') {
                    stopTimer('list');
                    state.timers.list = setInterval(refreshLists, POLL.list * 1000);
                    refreshLists();
                }
            } else {
                startBadgeLoop();
            }
        }
    });

    // Click-to-chat bridge from the dashboard's "Online Engagement" widget.
    window.pgChatOpenWith = function (userId) {
        userId = parseInt(userId, 10);

        if (!userId) {
            return;
        }

        if (!state.open) {
            togglePanel();
        }

        openWithUser({ id: userId });
    };

    // Open an existing conversation by id, for the waiting-conversation rows
    // of the same widget. Those rows include site conversations, which have a
    // visitor rather than a user account on the other end, so pgChatOpenWith
    // cannot reach them.
    //
    // The row carries only an id; everything openConversationView needs
    // (title, peer, authority) is server-derived, so the list is re-fetched
    // and matched rather than trusted from the page. A conversation that is
    // no longer in the list — closed in another tab, or past the list's own
    // limit — leaves the panel on the list view, which is the honest result;
    // an error dialog would say the click failed when the conversation simply
    // is not waiting any more.
    window.pgChatOpenConversation = function (conversationId) {
        conversationId = parseInt(conversationId, 10);

        if (!conversationId) {
            return;
        }

        if (!state.open) {
            togglePanel();
        }

        api('chat_bootstrap', {}, function (response) {
            if (response.status !== 'success') {
                return;
            }

            var conversations = response.data.conversations || [];
            var index;

            for (index = 0; index < conversations.length; index++) {
                if (conversations[index].id === conversationId) {
                    openConversationView({
                        id: conversations[index].id,
                        title: conversations[index].title,
                        peer: conversations[index].peer,
                        operator: conversations[index].operator || null,
                        status: conversations[index].status,
                        can_manage: conversations[index].can_manage
                    });

                    return;
                }
            }
        });
    };

    startBadgeLoop();
})();
