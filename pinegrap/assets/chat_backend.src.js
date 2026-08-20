/**
 * PineGrap - Enterprise Website Platform — Live Chat backend panel.
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

    // ── Bildirim sesi ───────────────────────────────────────────────────
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

    function userRow(user, onClick) {
        var row = el('div', 'pg-chat-row d-flex align-items-center px-2 py-2');

        var avatarWrap = el('div', 'position-relative me-2 flex-shrink-0');
        var avatar = el('img', 'rounded-circle pg-chat-avatar');
        avatar.src = user.avatar;
        avatar.alt = '';
        avatarWrap.appendChild(avatar);

        var dot = el('span', 'position-absolute rounded-circle border border-2 border-white pg-chat-dot pg-chat-dot-' + user.presence);
        dot.title = presenceLabel(user.presence);
        avatarWrap.appendChild(dot);

        var info = el('div', 'flex-grow-1 overflow-hidden');
        var topLine = el('div', 'd-flex justify-content-between align-items-center');
        var name = el('span', 'text-truncate me-1 fw-semibold', user.name);
        name.style.fontSize = '13px';
        var role = el('span', 'badge rounded-pill text-bg-light flex-shrink-0', user.role_label);
        role.style.fontSize = '10px';
        topLine.appendChild(name);
        topLine.appendChild(role);
        var presence = el('small', 'text-muted', presenceLabel(user.presence));
        info.appendChild(topLine);
        info.appendChild(presence);

        row.appendChild(avatarWrap);
        row.appendChild(info);
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
        var row = el('div', 'pg-chat-row d-flex align-items-center px-2 py-2');

        var avatarWrap = el('div', 'position-relative me-2 flex-shrink-0');
        var avatar = el('div', 'pg-chat-ai-avatar');
        avatar.appendChild(el('i', 'bi bi-robot'));
        avatarWrap.appendChild(avatar);

        var dot = el('span', 'position-absolute rounded-circle border border-2 border-white pg-chat-dot pg-chat-dot-online');
        dot.title = STR.online;
        avatarWrap.appendChild(dot);

        var info = el('div', 'flex-grow-1 overflow-hidden');
        var topLine = el('div', 'd-flex justify-content-between align-items-center');
        var name = el('span', 'text-truncate me-1 fw-semibold', config.ai.label);
        name.style.fontSize = '13px';
        var badgeAi = el('span', 'badge rounded-pill text-bg-success flex-shrink-0', 'AI');
        badgeAi.style.fontSize = '10px';
        topLine.appendChild(name);
        topLine.appendChild(badgeAi);
        info.appendChild(topLine);
        info.appendChild(el('small', 'text-muted', STR.online));

        row.appendChild(avatarWrap);
        row.appendChild(info);
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
            var row = el('div', 'pg-chat-row px-2 py-2' + (conversation.unread ? ' pg-chat-row-unread' : ''));

            var topLine = el('div', 'd-flex justify-content-between align-items-center');
            var title = el('span', 'text-truncate me-1', conversation.title);
            title.style.fontSize = '13px';
            var time = el('small', 'text-muted flex-shrink-0', formatTime(conversation.last_message_at));
            topLine.appendChild(title);
            topLine.appendChild(time);

            var bottomLine = el('div', 'd-flex justify-content-between align-items-center');
            var preview = el('small', 'text-muted text-truncate me-1', conversation.preview || '');
            bottomLine.appendChild(preview);

            if (conversation.unread) {
                bottomLine.appendChild(el('span', 'badge rounded-pill text-bg-danger', ' '));
            }

            if (conversation.channel === 'site') {
                topLine.insertBefore(el('span', 'badge text-bg-info me-1', STR.site), title);
            }

            row.appendChild(topLine);
            row.appendChild(bottomLine);

            // A site conversation addressed to another operator must not
            // look addressed to the viewer: the target operator is labeled
            // explicitly on the row.
            if (conversation.channel === 'site' && conversation.operator
                && config.me && conversation.operator.id !== config.me.id) {
                var operatorLine = el('div', '');
                operatorLine.appendChild(el('small', 'text-muted', '→ ' + conversation.operator.username));
                row.appendChild(operatorLine);
            }

            row.addEventListener('click', function () {
                openConversationView({
                    id: conversation.id,
                    title: conversation.title,
                    peer: conversation.peer,
                    operator: conversation.operator || null,
                    status: conversation.status,
                    can_manage: conversation.can_manage,
                    ip_address: conversation.ip_address || ''
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
        var titleLine = el('div', 'fw-semibold text-truncate', state.conversation.title);
        titleLine.style.fontSize = '13px';
        var presenceLine = el('small', 'text-muted');
        if (state.conversation.peer) {
            presenceLine.textContent = presenceLabel(state.conversation.peer.presence);
        } else {
            // Site conversation subtitle: target operator (when it belongs
            // to another operator) + the visitor's IP address.
            var subtitleParts = [];

            if (state.conversation.operator && config.me && state.conversation.operator.id !== config.me.id) {
                // The site conversation belongs to another operator: make it
                // clear in the header too.
                subtitleParts.push('→ ' + state.conversation.operator.username);
            }

            if (state.conversation.ipAddress) {
                subtitleParts.push((STR.ip || 'IP') + ': ' + state.conversation.ipAddress);
            }

            presenceLine.textContent = subtitleParts.join(' · ');
        }
        titleWrap.appendChild(titleLine);
        titleWrap.appendChild(presenceLine);
        header.appendChild(titleWrap);

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

        // Compose area.
        var compose = el('div', 'card-footer p-2 pg-chat-compose');
        var composeRow = el('div', 'd-flex align-items-end gap-2');
        var textarea = el('textarea', 'form-control');
        textarea.placeholder = STR.type_a_message;
        textarea.rows = 1;
        var sendButton = el('button', 'btn btn-primary btn-sm flex-shrink-0');
        sendButton.type = 'button';
        sendButton.title = STR.send;
        sendButton.appendChild(el('i', 'bi bi-send-fill'));

        // Attach: always visible (hidden on a draft until the conversation
        // exists — uploading requires an existing conversation).
        var attachButton = null;
        var fileInput = null;

        if (config.attach && config.attach.enabled) {
            fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.accept = '.jpg,.jpeg,.png,.gif,.webp,.pdf,.zip,.rar,.7z,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv';
            fileInput.style.display = 'none';

            attachButton = el('button', 'btn btn-outline-secondary btn-sm flex-shrink-0');
            attachButton.type = 'button';
            attachButton.title = STR.attach;
            attachButton.appendChild(el('i', 'bi bi-paperclip'));

            if (state.conversation.id === 0) {
                attachButton.style.display = 'none';
            }

            attachButton.addEventListener('click', function () {
                fileInput.click();
            });

            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                fileInput.value = '';

                if (!file || !state.conversation || state.conversation.id === 0) {
                    return;
                }

                if (file.size > config.attach.max) {
                    window.alert(STR.file_too_big);
                    return;
                }

                var reader = new FileReader();

                reader.onload = function () {
                    attachButton.disabled = true;

                    api('chat_attach', {
                        conversation_id: state.conversation.id,
                        name: file.name,
                        data: reader.result
                    }, function (response) {
                        attachButton.disabled = false;

                        if (response.status !== 'success') {
                            window.alert(response.message || '');
                            return;
                        }

                        appendMessages(body, [response.data]);
                    }, function () {
                        attachButton.disabled = false;
                    });
                };

                reader.readAsDataURL(file);
            });

            composeRow.appendChild(fileInput);
            composeRow.appendChild(attachButton);
        }

        composeRow.appendChild(textarea);
        composeRow.appendChild(sendButton);
        compose.appendChild(composeRow);
        panel.appendChild(compose);

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

                // The draft became a real conversation: take the id, start
                // the poll loop, the attach button may show now.
                if (state.conversation.id === 0 && response.data.conversation_id) {
                    state.conversation.id = response.data.conversation_id;
                    scheduleConversationPoll(body);

                    if (attachButton) {
                        attachButton.style.display = '';
                    }
                }

                textarea.value = '';
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

        // References for the typing signal (the poll reads/updates them).
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

            // Attachments: images preview inside the bubble, files stay as
            // download links (never rendered).
            if (message.attachment && message.attachment.url) {
                if (message.attachment.kind === 'image') {
                    var image = document.createElement('img');
                    image.src = message.attachment.url;
                    image.alt = message.attachment.name || '';
                    image.style.maxWidth = '100%';
                    image.style.borderRadius = '10px';
                    image.style.display = 'block';
                    image.style.cursor = 'pointer';

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
                    fileLink.className = 'd-inline-flex align-items-center gap-1';
                    fileLink.style.color = 'inherit';
                    fileLink.appendChild(el('i', 'bi bi-paperclip'));
                    fileLink.appendChild(el('span', '', message.attachment.name || ''));
                    bubble.appendChild(fileLink);
                }
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
                    state.conversation._presence.textContent = presenceLabel(response.data.peer.presence);
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

    function togglePanel() {
        state.open = !state.open;

        if (state.open) {
            panel.classList.add('pg-chat-open');
            state.tab = 'conversations';
            renderListView(null);
            refreshLists();
            stopTimer('badge');
            stopTimer('list');
            state.timers.list = setInterval(refreshLists, POLL.list * 1000);
        } else {
            panel.classList.remove('pg-chat-open');
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
