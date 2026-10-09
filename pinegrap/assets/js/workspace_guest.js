/**
 * Pinegrap - Enterprise Website Platform
 *
 * The guest's side of a conversation in the workspace (workspace_guest.php):
 * the token is taken from behind the "#" and claimed, then the room is read
 * and asked for news every few seconds. The guest writes text with bold,
 * italics and strike-through, answers a message and leaves an emoji.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function () {
    'use strict';

    var CONF = JSON.parse(document.getElementById('pg-guest-config').textContent || '{}');
    var S = CONF.strings || {};
    var log = document.getElementById('pg-guest-log');
    var foot = document.getElementById('pg-guest-foot');
    var text = document.getElementById('pg-guest-text');
    var sendButton = document.getElementById('pg-guest-send');
    var replyBar = document.getElementById('pg-guest-reply');

    var state = {
        csrf: '',
        signature: '',
        messages: [],
        replyTo: null,
        sending: false,
        timer: null,
        failures: 0,
        closed: false,
        picker: null
    };

    // Light or dark, as the device is.
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
    }

    function t(key) {
        var text = Object.prototype.hasOwnProperty.call(S, key) ? S[key] : key;
        var values = Array.prototype.slice.call(arguments, 1);

        values.forEach(function (value, index) {
            text = text.split('{' + (index + 1) + '}').join(String(value));
        });

        return text;
    }

    function el(tag, className, content) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if ((content !== undefined) && (content !== null) && (content !== '')) {
            node.textContent = String(content);
        }

        return node;
    }

    function icon(name) {
        var node = el('i', 'bi ' + name);
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    function post(action, data) {
        var payload = data || {};

        payload.action = action;

        if (state.csrf) {
            payload.csrf = state.csrf;
        }

        return fetch(CONF.endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json();
        });
    }

    // A line in place of the conversation: opening, refused, ended.
    function note(message, kind) {
        log.textContent = '';
        log.setAttribute('aria-busy', 'false');

        var box = el('div', 'pg-guest-note' + (kind ? ' pg-guest-note-' + kind : ''));

        if (kind) {
            box.appendChild(icon(kind === 'closed' ? 'bi-door-closed' : 'bi-info-circle'));
        }

        box.appendChild(el('div', '', message));
        log.appendChild(box);
    }

    function close(message) {
        state.closed = true;
        clearTimeout(state.timer);
        foot.classList.add('d-none');
        note(message || t('error'), 'closed');
    }

    var toastTimer = null;

    function toast(message) {
        var box = document.getElementById('pg-guest-toast');

        if (!box) {
            box = el('div', 'pg-guest-toast');
            box.id = 'pg-guest-toast';
            box.setAttribute('role', 'status');
            document.body.appendChild(box);
        }

        box.textContent = message;
        box.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { box.classList.remove('show'); }, 4000);
    }

    // ── The room ─────────────────────────────────────────────────────────

    function drawHead(data) {
        var title = document.getElementById('pg-guest-title');

        // A channel of the team shared with the guest carries its name; a
        // room of their own its topic.
        title.textContent = data.title || data.topic || t('title');
        title.title = (data.title && data.topic) ? data.topic : '';

        var hint = document.querySelector('.pg-guest-hint');

        if (hint && data.privacy) {
            hint.textContent = data.privacy;
        }

        var badge = document.getElementById('pg-guest-readonly');

        if (data.read_only && !badge) {
            badge = el('span', 'pg-guest-readonly-badge', t('readonly_badge'));
            badge.id = 'pg-guest-readonly';
            badge.title = t('read_only');
            title.parentNode.appendChild(badge);
        } else if (!data.read_only && badge) {
            badge.remove();
        }

        var staff = document.getElementById('pg-guest-staff');
        staff.textContent = '';

        (data.staff || []).slice(0, 4).forEach(function (person) {
            var face = el('img', 'pg-guest-face');
            face.src = person.avatar;
            face.alt = person.name;
            face.title = person.name;
            staff.appendChild(face);
        });
    }

    function nearEnd() {
        return (log.scrollHeight - log.scrollTop - log.clientHeight) < 80;
    }

    function drawMessages(messages) {
        var stay = nearEnd() || !state.messages.length;
        var before = log.scrollTop;

        state.messages = messages;
        log.textContent = '';
        log.setAttribute('aria-busy', 'false');

        if (!messages.length) {
            log.appendChild(el('div', 'pg-guest-note', t('empty')));
            return;
        }

        messages.forEach(function (message) {
            log.appendChild(drawMessage(message));
        });

        if (stay) {
            log.scrollTop = log.scrollHeight;
        } else {
            log.scrollTop = before;
        }
    }

    function drawMessage(message) {
        var row = el('div', 'pg-guest-msg' + (message.mine ? ' pg-guest-mine' : ''));
        row.setAttribute('data-id', message.id);

        if (!message.mine) {
            var face = el('img', 'pg-guest-face');
            face.src = message.avatar;
            face.alt = '';
            row.appendChild(face);
        }

        var bubble = el('div', 'pg-guest-bubble');
        var meta = el('div', 'pg-guest-meta');
        meta.appendChild(el('b', '', message.mine ? t('you') : message.name));
        meta.appendChild(el('span', '', message.time + (message.edited ? ' · ' + t('edited') : '')));
        bubble.appendChild(meta);

        if (message.parent) {
            var quote = el('button', 'pg-guest-quote');
            quote.type = 'button';
            quote.appendChild(el('b', '', message.parent.name));
            quote.appendChild(el('span', '', message.parent.text));
            quote.addEventListener('click', function () {
                var target = log.querySelector('[data-id="' + message.parent.id + '"]');

                if (target) {
                    target.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    target.classList.add('pg-guest-flash');
                    setTimeout(function () { target.classList.remove('pg-guest-flash'); }, 1600);
                }
            });
            bubble.appendChild(quote);
        }

        // Written by the server from the kept words, by the same engine the
        // staff's screen uses (tables, sums, code, checklists); what the guest
        // wrote with the few marks a guest may use.
        var body = el('div', 'pg-guest-body');
        body.innerHTML = message.html;
        wireBody(body, message);
        bubble.appendChild(body);

        if (message.file) {
            bubble.appendChild(drawFile(message.file));
        }

        if (message.poll) {
            bubble.appendChild(drawPoll(message.poll));
        }

        if (message.approval) {
            bubble.appendChild(drawApproval(message.approval));
        }

        if (message.task) {
            bubble.appendChild(drawTask(message.task));
        }

        var actions = el('div', 'pg-guest-actions');

        // Reading only: the emoji are shown, nothing is offered.
        (message.reactions || []).forEach(function (reaction) {
            var chip = el(state.readOnly ? 'span' : 'button', 'pg-guest-reaction' + (reaction.mine ? ' mine' : ''));
            chip.textContent = reaction.emoji + ' ' + reaction.count;

            if (!state.readOnly) {
                chip.type = 'button';
                chip.setAttribute('aria-pressed', reaction.mine ? 'true' : 'false');
                chip.addEventListener('click', function () { react(message.id, reaction.emoji); });
            }

            actions.appendChild(chip);
        });

        if (state.readOnly) {
            if (actions.children.length) {
                bubble.appendChild(actions);
            }

            row.appendChild(bubble);

            return row;
        }

        var add = el('button', 'pg-guest-tool');
        add.type = 'button';
        add.title = t('react');
        add.setAttribute('aria-label', t('react'));
        add.appendChild(icon('bi-emoji-smile'));
        add.addEventListener('click', function (event) {
            event.stopPropagation();
            openPicker(add, message.id);
        });
        actions.appendChild(add);

        var answer = el('button', 'pg-guest-tool');
        answer.type = 'button';
        answer.title = t('reply');
        answer.setAttribute('aria-label', t('reply'));
        answer.appendChild(icon('bi-reply'));
        answer.addEventListener('click', function () { startReply(message); });
        actions.appendChild(answer);

        bubble.appendChild(actions);
        row.appendChild(bubble);

        return row;
    }

    // A checklist staff wrote is ticked here too (not one that became
    // tasks: the server leaves its boxes disabled); a block of code copies.
    function wireBody(body, message) {
        Array.prototype.forEach.call(body.querySelectorAll('input[data-ws-check]'), function (box) {
            if (state.readOnly) {
                box.disabled = true;
            }

            if (box.disabled) {
                return;
            }

            box.addEventListener('change', function () {
                box.disabled = true;
                post('check', { message_id: message.id, item: parseInt(box.getAttribute('data-ws-check'), 10) || 0, checked: box.checked ? 1 : 0 }).then(function (data) {
                    if (data && data.status === 'ok') {
                        state.signature = '';
                    }

                    apply(data);
                }).catch(function () {
                    box.disabled = false;
                    toast(t('error'));
                });
            });
        });

        Array.prototype.forEach.call(body.querySelectorAll('[data-ws-copy-code]'), function (button) {
            button.addEventListener('click', function () {
                var code = button.closest('.ws-code');
                var text = code ? (code.querySelector('pre') || code).textContent : '';

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(function () { button.classList.add('copied'); });
                }
            });
        });
    }

    // A picture, a video, a sound or a document staff put in the room.
    function drawFile(file) {
        var box = el('div', 'pg-guest-file');

        if (file.kind === 'image') {
            var link = el('a', 'pg-guest-image');
            link.href = file.url;
            link.target = '_blank';
            link.rel = 'noopener';

            var picture = el('img');
            picture.src = file.url;
            picture.alt = file.name;
            picture.loading = 'lazy';
            picture.addEventListener('load', function () {
                if (nearEnd()) {
                    log.scrollTop = log.scrollHeight;
                }
            });
            link.appendChild(picture);
            box.appendChild(link);

            return box;
        }

        if ((file.kind === 'video') || (file.kind === 'audio')) {
            var player = el(file.kind);
            player.src = file.url;
            player.controls = true;
            player.preload = 'metadata';
            box.appendChild(player);
        }

        var download = el('a', 'pg-guest-download');
        download.href = file.url;
        download.target = '_blank';
        download.rel = 'noopener';
        download.appendChild(icon(file.kind === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-arrow-down'));
        download.appendChild(el('span', '', file.name));
        download.title = t('download');
        box.appendChild(download);

        return box;
    }

    // A poll staff opened in the room: the guest votes like anybody in it.
    function drawPoll(poll) {
        var box = el('div', 'pg-guest-poll');
        var total = poll.options.reduce(function (sum, option) { return sum + option.count; }, 0);
        var chosen = {};
        var open = !poll.closed && !state.readOnly;

        poll.options.forEach(function (option) {
            chosen[option.id] = option.mine;
        });

        var list = el('div', 'pg-guest-poll-options');

        poll.options.forEach(function (option) {
            var row = el(open ? 'label' : 'div', 'pg-guest-poll-option' + (option.leading && total ? ' leading' : ''));
            var share = total ? Math.round(option.count * 100 / total) : 0;

            if (open) {
                var pick = el('input', 'form-check-input');
                pick.type = poll.multiple ? 'checkbox' : 'radio';
                pick.name = 'poll-' + poll.id;
                pick.checked = option.mine;
                pick.addEventListener('change', function () {
                    if (!poll.multiple) {
                        Object.keys(chosen).forEach(function (key) { chosen[key] = false; });
                    }

                    chosen[option.id] = pick.checked;
                    vote(poll.id, chosen);
                });
                row.appendChild(pick);
            }

            var text = el('span', 'pg-guest-poll-label', option.label);
            row.appendChild(text);
            row.appendChild(el('span', 'pg-guest-poll-count', option.count + (total ? ' · %' + share : '')));

            var bar = el('span', 'pg-guest-poll-bar');
            bar.style.width = share + '%';
            row.appendChild(bar);

            if (option.voters && option.voters.length) {
                row.title = option.voters.join(', ');
            }

            list.appendChild(row);
        });

        box.appendChild(list);

        var foot = [t('poll_voters', poll.voters)];

        if (poll.closed) {
            foot.push(t('poll_closed'));
        } else {
            if (poll.multiple) {
                foot.push(t('poll_multiple'));
            }

            if (poll.closes) {
                foot.push(t('poll_closes', poll.closes));
            }
        }

        box.appendChild(el('div', 'pg-guest-poll-foot', foot.join(' · ')));

        return box;
    }

    function vote(pollId, chosen) {
        var ids = Object.keys(chosen).filter(function (key) { return chosen[key]; }).map(Number);

        post('vote', { poll_id: pollId, option_ids: ids }).then(function (data) {
            if (data && data.status === 'ok') {
                state.signature = '';
            }

            apply(data);
        }).catch(function () { toast(t('error')); });
    }

    // An approval request staff made in the room, to read: the guest is
    // never one of the people asked.
    function drawApproval(approval) {
        var box = el('div', 'pg-guest-task' + (approval.open ? '' : ' done'));
        box.appendChild(icon(approval.open ? 'bi-patch-question' : 'bi-patch-check'));

        var text = el('div', 'pg-guest-task-text');
        text.appendChild(el('b', '', approval.title));
        text.appendChild(el('span', '', [t('approval'), approval.state].concat((approval.people || []).map(function (person) {
            return person.name + (person.decision === 'approved' ? ' ✓' : (person.decision === 'rejected' ? ' ✗' : ''));
        }).join(', ')).filter(Boolean).join(' · ')));
        box.appendChild(text);

        return box;
    }

    // A task staff made in the room, to read.
    function drawTask(task) {
        var box = el('div', 'pg-guest-task' + (task.open ? '' : ' done'));
        box.appendChild(icon(task.open ? 'bi-check2-square' : 'bi-check2-circle'));

        var text = el('div', 'pg-guest-task-text');
        text.appendChild(el('b', '', task.title));
        text.appendChild(el('span', '', [t('task'), task.status, task.due].filter(Boolean).join(' · ')));
        box.appendChild(text);

        return box;
    }

    function openPicker(anchor, messageId) {
        closePicker();

        var picker = el('div', 'pg-guest-picker');
        picker.setAttribute('role', 'menu');

        (CONF.emoji || []).forEach(function (emoji) {
            var choice = el('button', '', emoji);
            choice.type = 'button';
            choice.setAttribute('role', 'menuitem');
            choice.addEventListener('click', function () {
                closePicker();
                react(messageId, emoji);
            });
            picker.appendChild(choice);
        });

        anchor.parentNode.appendChild(picker);
        state.picker = picker;

        var first = picker.querySelector('button');

        if (first) {
            first.focus();
        }
    }

    function closePicker() {
        if (state.picker) {
            state.picker.remove();
            state.picker = null;
        }
    }

    document.addEventListener('click', function (event) {
        if (state.picker && !state.picker.contains(event.target)) {
            closePicker();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePicker();

            if (state.replyTo) {
                endReply();
            }
        }
    });

    function startReply(message) {
        state.replyTo = message;
        replyBar.textContent = '';

        var what = el('div', 'pg-guest-reply-text');
        what.appendChild(el('b', '', message.mine ? t('you') : message.name));
        what.appendChild(el('span', '', (message.html ? tempText(message.html) : '').slice(0, 120)));
        replyBar.appendChild(icon('bi-reply'));
        replyBar.appendChild(what);

        var cancel = el('button', 'btn btn-sm btn-link');
        cancel.type = 'button';
        cancel.setAttribute('aria-label', t('cancel'));
        cancel.appendChild(icon('bi-x-lg'));
        cancel.addEventListener('click', endReply);
        replyBar.appendChild(cancel);

        replyBar.classList.remove('d-none');
        text.focus();
    }

    function endReply() {
        state.replyTo = null;
        replyBar.classList.add('d-none');
        replyBar.textContent = '';
    }

    function tempText(html) {
        var box = document.createElement('div');
        box.innerHTML = html;
        return (box.textContent || '').replace(/\s+/g, ' ').trim();
    }

    // ── Talking to the server ────────────────────────────────────────────

    function apply(data) {
        if (!data) {
            return;
        }

        if (data.status === 'ok') {
            var readOnly = !!data.read_only;

            state.csrf = data.csrf || state.csrf;
            drawHead(data);

            // Shared to read only: no writing box; the messages are drawn
            // again without their buttons when that changes.
            if (readOnly !== !!state.readOnly) {
                state.readOnly = readOnly;
                data.same = false;
                state.signature = '';
            }

            foot.classList.toggle('d-none', readOnly);

            if (!data.same && data.messages) {
                state.signature = data.signature;
                drawMessages(data.messages);
            }

            return;
        }

        if ((data.status === 'ended') || (data.status === 'refused')) {
            close(data.message);
            return;
        }

        if (data.status === 'none') {
            close(t('no_link'));
            return;
        }

        toast(data.message || t('error'));
    }

    function schedule() {
        clearTimeout(state.timer);

        if (state.closed) {
            return;
        }

        var wait = (document.visibilityState === 'hidden') ? 15000 : 4000;

        // Lost connection: asked again later and later, up to a minute.
        if (state.failures > 0) {
            wait = Math.min(60000, wait * Math.pow(2, state.failures));
        }

        state.timer = setTimeout(poll, wait);
    }

    function poll() {
        post('state', { signature: state.signature }).then(function (data) {
            state.failures = 0;
            document.body.classList.remove('pg-guest-offline');
            apply(data);
            schedule();
        }).catch(function () {
            state.failures += 1;
            document.body.classList.add('pg-guest-offline');
            toast(t('offline'));
            schedule();
        });
    }

    document.addEventListener('visibilitychange', function () {
        if ((document.visibilityState === 'visible') && !state.closed && state.csrf) {
            poll();
        }
    });

    function send() {
        var words = text.value.trim();

        if (!words || state.sending) {
            return;
        }

        state.sending = true;
        sendButton.disabled = true;

        post('send', { text: words, parent_id: state.replyTo ? state.replyTo.id : 0 }).then(function (data) {
            state.sending = false;
            sendButton.disabled = false;

            if (data && data.status === 'ok') {
                text.value = '';
                grow();
                endReply();
                state.signature = '';
                apply(data);
                log.scrollTop = log.scrollHeight;
                text.focus();
                schedule();
                return;
            }

            apply(data);
        }).catch(function () {
            state.sending = false;
            sendButton.disabled = false;
            toast(t('error'));
        });
    }

    function react(messageId, emoji) {
        post('react', { message_id: messageId, emoji: emoji }).then(function (data) {
            if (data && data.status === 'ok') {
                state.signature = '';
            }

            apply(data);
        }).catch(function () { toast(t('error')); });
    }

    // ── The composer ─────────────────────────────────────────────────────

    function grow() {
        text.style.height = 'auto';
        text.style.height = Math.min(text.scrollHeight, 180) + 'px';
    }

    function touchScreen() {
        return !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
    }

    text.addEventListener('input', grow);
    text.addEventListener('keydown', function (event) {
        if ((event.key === 'Enter') && !event.shiftKey && !event.isComposing && !touchScreen()) {
            event.preventDefault();
            send();
        }
    });
    sendButton.addEventListener('click', send);

    // Bold, italics, strike-through: the marks around what is selected, or
    // an empty pair to write into.
    Array.prototype.forEach.call(document.querySelectorAll('[data-wrap]'), function (tool) {
        tool.addEventListener('click', function () {
            var mark = tool.getAttribute('data-wrap');
            var start = text.selectionStart;
            var end = text.selectionEnd;
            var value = text.value;
            var chosen = value.slice(start, end);

            text.value = value.slice(0, start) + mark + chosen + mark + value.slice(end);
            text.focus();
            text.setSelectionRange(start + mark.length, start + mark.length + chosen.length);
            grow();
        });
    });

    if (touchScreen()) {
        text.setAttribute('enterkeyhint', 'enter');
    } else {
        text.title = t('hint');
    }

    // ── Coming in ────────────────────────────────────────────────────────

    var found = /(?:^#|&)t=([A-Za-z0-9_-]{43})/.exec(window.location.hash || '');
    var token = found ? found[1] : '';

    // The token leaves the address at once: nothing after this carries it.
    if (window.location.hash && window.history && window.history.replaceState) {
        window.history.replaceState(null, '', window.location.pathname);
    }

    (token ? post('claim', { token: token }) : post('hello')).then(function (data) {
        apply(data);

        if (data && data.status === 'ok') {
            log.scrollTop = log.scrollHeight;
            schedule();

            if (!touchScreen()) {
                text.focus();
            }
        }
    }).catch(function () {
        close(t('error'));
    });
})();
