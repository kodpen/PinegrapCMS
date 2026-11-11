/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - answering an e-mail conversation by e-mail on the channel
 * screen: the "Reply by e-mail" button under a conversation's card, the
 * drawer the answer is written in (with the ; list of saved replies of
 * assets/js/workspace_replies.js, put in as plain text), and the
 * conversation's status and holder, given to anybody of the channel
 * (includes/workspace/mail_status.php). On the E-mails screen the list,
 * its reading pane and the unread marks (screen()).
 *
 * assets/js/workspace.js draws the conversation and calls in here with its
 * own helpers. Whether a card may be answered is decided by the server
 * (includes/workspace/mail_reply.php) and checked again when the answer is
 * sent; the answer then arrives in the card's thread like any message.
 * Every text comes from the #ws-config block.
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The drawer: who it goes to, the answer, Send. A draft fills the
    // answer; nothing leaves before the member presses Send.
    function replyForm(message, view, h, draft) {
        var t = h.t;
        var el = h.el;
        var node = h.offcanvas('ws-mail-reply', t('mail_reply'));
        var body = h.clear(node.querySelector('.drawer-body'));
        var footer = h.clear(node.querySelector('.drawer-footer'));

        node.querySelector('.drawer-title').textContent = t('mail_reply');
        footer.classList.remove('d-none');

        if (message.sender && message.sender.title) {
            body.appendChild(el('div', 'small fg-secondary mb-3', t('mail_reply_to', message.sender.title)));
        }

        var text = el('textarea', 'form-control');
        text.id = h.nextId('ws-mail-reply-');
        text.rows = 10;
        text.maxLength = 50000;
        text.value = draft || '';

        var box = el('div', 'ws-mail-reply-box position-relative');
        box.appendChild(text);
        body.appendChild(h.formRow(t('mail_reply_text'), box, t('mail_reply_hint')));
        savedReplies(text, box, view, h);

        var send = h.button('btn btn-solid theme-primary rounded-pill px-5', t('mail_reply_send'), 'bi-send');

        send.addEventListener('click', function () {
            if (!text.value.trim()) {
                text.focus();
                return;
            }

            send.disabled = true;

            h.api('ws_mail_reply', { conversation_id: message.mail_reply, text: text.value }).then(function (data) {
                h.hideOffcanvas(node);
                // Sent, but the mailbox's sent folder kept no copy.
                h.toast(data.copy_note || t('mail_reply_sent'), data.copy_note ? 'warning' : 'success');
                view.sync();
            }).catch(function (error) {
                send.disabled = false;
                h.fail(error);
            });
        });

        footer.appendChild(send);
        h.showOffcanvas(node);
        text.focus();
    }

    // A saved reply as plain text for an e-mail: its tags by their names
    // (the server's labels), its lines kept. {customer} and {order} were
    // already filled for the channel by the server.
    function plain(item) {
        var labels = item.labels || {};

        return String(item.text || item.body || '').replace(/<[@#][a-z_]+:[0-9]{1,10}>/g, function (token) {
            return labels[token] || '';
        });
    }

    // The ; list of the person's saved replies under the answer, as in the
    // channel's composer (window.PGWsReplies): ";" and the start of a
    // shortcut suggest them, a pick puts the reply in as plain text in
    // place of ";word". Arrows move, Enter or Tab picks, Escape leaves
    // that ";" alone.
    function savedReplies(text, box, view, h) {
        var R = window.PGWsReplies;

        if (!R || !h.cfg || !h.cfg.saved_replies) {
            return;
        }

        var channelId = view.channel ? view.channel.id : 0;
        var list = null;
        var items = [];
        var active = 0;
        var semi = null;
        // The ; Escape closed, by its place: it does not open again.
        var skip = -1;

        function close() {
            if (list && list.parentNode) {
                list.parentNode.removeChild(list);
            }

            list = null;
            items = [];
        }

        function mark() {
            Array.prototype.forEach.call(list.querySelectorAll('.ws-picker-item'), function (row, index) {
                row.classList.toggle('active', index === active);
            });
        }

        function pick(item) {
            var caret = text.selectionStart;
            var start = semi.start;
            var value = plain(item);

            close();
            text.focus();
            text.setSelectionRange(start, caret);

            // insertText keeps the browser's undo; setRangeText where it is gone.
            if (!document.execCommand || !document.execCommand('insertText', false, value)) {
                text.setRangeText(value, start, caret, 'end');
            }
        }

        function draw() {
            close();
            list = h.el('div', 'ws-picker');
            var inner = h.el('div', 'ws-picker-list');

            items.forEach(function (item, index) {
                var row = h.el('button', 'ws-picker-item');
                row.type = 'button';
                row.appendChild(h.icon('bi-chat-square-quote'));
                row.appendChild(h.el('span', 'ws-picker-label', item.title));
                row.appendChild(h.el('span', 'ws-picker-meta', ';' + item.shortcut));
                row.title = R.preview(item, 160);
                // Keep the caret in the answer until the pick.
                row.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
                row.addEventListener('click', function () {
                    pick(item);
                });

                if (index === active) {
                    row.classList.add('active');
                }

                inner.appendChild(row);
            });

            list.appendChild(inner);
            box.appendChild(list);
        }

        function check() {
            var caret = text.selectionStart;

            semi = (caret === text.selectionEnd && document.activeElement === text) ? R.at(text.value.slice(0, caret)) : null;

            if (!semi || semi.start === skip) {
                close();
                return;
            }

            var replies = R.cached(channelId, h.api, check);
            var found = R.match(replies || [], semi.word, 8);

            if (!found.length) {
                close();
                return;
            }

            items = found;
            active = 0;
            draw();
        }

        text.addEventListener('input', function () {
            if (skip >= text.value.length || text.value.charAt(skip) !== ';') {
                skip = -1;
            }

            check();
        });
        text.addEventListener('click', check);
        text.addEventListener('blur', close);
        text.addEventListener('keydown', function (event) {
            if (!list || !items.length || event.isComposing) {
                return;
            }

            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                active = (active + (event.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length;
                mark();
            } else if (event.key === 'Enter' || event.key === 'Tab') {
                event.preventDefault();
                pick(items[active]);
            } else if (event.key === 'Escape') {
                // The drawer stays open; only the list closes.
                event.preventDefault();
                event.stopPropagation();
                skip = semi ? semi.start : -1;
                close();
            }
        });
    }

    // Under a conversation's card.
    function node(message, view, h) {
        var row = h.el('div', 'ws-mail-reply mt-1');
        var open = h.button('btn btn-sm btn-outline theme-primary py-0', h.t('mail_reply'), 'bi-reply');

        open.addEventListener('click', function (event) {
            event.stopPropagation();
            replyForm(message, view, h);
        });

        row.appendChild(open);

        // Forward as e-mail: the New e-mail form filled with this mail.
        if (message.mail_forward && h.cfg.mail_forward) {
            var pass = h.button('btn btn-sm btn-outline theme-secondary py-0 ms-2', h.t('mail_fwd'), 'bi-envelope-arrow-up');

            pass.addEventListener('click', function (event) {
                event.stopPropagation();
                forward(h, { kind: 'mail', ids: [message.mail_forward] }, { sent: function () { view.sync(); } });
            });
            row.appendChild(pass);
        }

        if (message.mail_resend) {
            row.appendChild(resend(message, view, h));
        }

        if (message.mail_state) {
            state(row, message, view, h);
        }

        return row;
    }

    // Send again, under a mail that could not leave: pressed once, the
    // button stays off until the screen draws the card again.
    function resend(message, view, h) {
        var again = h.button('btn btn-sm btn-outline theme-danger py-0 ms-2', h.t('mail_send_again'), 'bi-arrow-repeat');

        again.addEventListener('click', function (event) {
            event.stopPropagation();
            again.disabled = true;

            h.api('ws_mail_resend', { item_id: message.mail_resend }).then(function () {
                h.toast(h.t('mail_send_again_done'), 'success');
                view.sync();
            }).catch(function (error) {
                again.disabled = false;
                h.fail(error);
            });
        });

        return again;
    }

    // The conversation's status and holder, changed at once.
    function state(row, message, view, h) {
        var t = h.t;
        var current = message.mail_state;
        var change = function (data, control) {
            data.conversation_id = message.mail_reply;
            control.disabled = true;

            h.api('ws_mail_conversation', data).then(function () {
                view.sync();
            }).catch(function (error) {
                control.disabled = false;
                h.fail(error);
            });
        };

        var status = h.select(['open', 'waiting', 'closed'].map(function (key) {
            return [key, t('mail_status_' + key)];
        }), current.status);

        status.classList.add('d-inline-block', 'w-auto', 'ms-2', 'py-0');
        status.setAttribute('aria-label', t('mail_status'));
        status.addEventListener('change', function () {
            change({ status: status.value }, status);
        });

        // Who holds it: nobody, the viewer, or a member of the channel.
        var people = [['', t('mail_nobody')], [String(current.me), t('mail_me')]];
        var seen = {};

        seen[people[1][0]] = true;
        ((view.channel && view.channel.members) || []).forEach(function (member) {
            var id = String(member.id);

            if (!seen[id]) {
                seen[id] = true;
                people.push([id, member.name]);
            }
        });

        // A holder who is not among the members; the card names them.
        if (current.assignee && !seen[String(current.assignee)]) {
            people.push([String(current.assignee), t('mail_other')]);
        }

        var hold = h.select(people, current.assignee ? String(current.assignee) : '');

        hold.classList.add('d-inline-block', 'w-auto', 'ms-2', 'py-0');
        hold.setAttribute('aria-label', t('mail_holder'));
        hold.addEventListener('change', function () {
            change({ assign: hold.value === '' ? 'none' : hold.value }, hold);
        });

        row.appendChild(status);
        row.appendChild(hold);
    }

    // Under Pinegrap AI's suggestion: its reply draft, opened in the drawer.
    function draftNode(message, view, h) {
        var row = h.el('div', 'ws-mail-draft mt-1');
        var open = h.button('btn btn-sm btn-outline theme-primary py-0', h.t('mail_draft_open'), 'bi-pencil-square');
        var offer = message.mail_draft;

        open.addEventListener('click', function (event) {
            event.stopPropagation();
            replyForm({ mail_reply: offer.conversation_id, sender: { title: offer.to } }, view, h, offer.text);
        });

        row.appendChild(open);

        return row;
    }

    // The E-mails screen (workspace_mail.php): a row opens its conversation
    // in the reading pane beside the list, Load more reads the next page
    // under the rows, Mark all as read clears the marks and the menu's
    // number. Without the script a row opens its card in the channel. The
    // pane's HTML is the server's, its texts escaped there
    // (includes/workspace/mail_pane.php).
    function screen(root, h) {
        var words = (h.cfg && h.cfg.mail && h.cfg.mail.strings) || {};
        var pane = root.querySelector('[data-ws-mail-pane]');
        var current = 0;

        function w(key) {
            return words[key] || key;
        }

        function each(selector, callback) {
            Array.prototype.forEach.call(document.querySelectorAll(selector), callback);
        }

        // The E-mails tile of the menu (workspace_side.js) shows the number.
        function badge(count) {
            each('[data-ws-nav="mail"]', function (tile) {
                var node = tile.querySelector('.ws-count');

                if (!count) {
                    if (node) {
                        node.parentNode.removeChild(node);
                    }

                    return;
                }

                if (!node) {
                    node = h.el('span', 'ws-count');
                    tile.appendChild(node);
                }

                node.textContent = (count > 99) ? '99+' : String(count);
            });
        }

        function read(row) {
            var hidden = row.querySelector('.ws-mail-who + .visually-hidden');

            row.classList.remove('ws-mail-unread');

            if (hidden) {
                hidden.parentNode.removeChild(hidden);
            }
        }

        // The open conversation stays in the address, for a reload.
        function address(id) {
            if (!window.history || !window.URL) {
                return;
            }

            var url = new URL(window.location.href);

            if (id) {
                url.searchParams.set('conversation', String(id));
            } else {
                url.searchParams.delete('conversation');
            }

            window.history.replaceState(window.history.state, '', url.toString());
        }

        function mark(row) {
            each('.ws-mail-row[aria-current]', function (node) {
                node.removeAttribute('aria-current');
            });

            if (row) {
                row.setAttribute('aria-current', 'true');
            }
        }

        function close() {
            current = 0;
            pane.hidden = true;
            h.clear(pane);
            root.classList.remove('ws-mail-reading');
            mark(null);
            address(0);
        }

        // The answer and the status controls, for whoever may write in the
        // conversation's channel; a change draws the pane again.
        function tools(data) {
            var place = pane.querySelector('.ws-mail-pane-tools');
            var shut = h.button('btn btn-sm btn-text theme-secondary', w('close'), 'bi-x-lg');

            shut.addEventListener('click', close);
            place.appendChild(shut);

            if (!data.reply) {
                return;
            }

            var message = { mail_reply: data.reply, sender: { title: data.to }, mail_state: data.state };
            var view = {
                channel: { id: data.channel_id, members: data.members || [] },
                sync: function () {
                    open(data.reply);
                }
            };
            var answer = h.button('btn btn-sm btn-outline theme-primary', h.t('mail_reply'), 'bi-reply');

            answer.addEventListener('click', function () {
                replyForm(message, view, h);
            });
            place.insertBefore(answer, place.firstChild);

            if (data.state) {
                state(place, message, view, h);
            }
        }

        function open(id) {
            var row = root.querySelector('[data-ws-mail-row="' + id + '"]');
            var again = (current === id);

            current = id;
            mark(row);
            pane.hidden = false;
            root.classList.add('ws-mail-reading');

            if (!again) {
                h.clear(pane).appendChild(h.el('div', 'ws-empty', w('loading')));
            }

            h.api('ws_mail_pane', { conversation_id: id }).then(function (data) {
                if (current !== id) {
                    return;
                }

                pane.innerHTML = data.html;
                tools(data);
                address(id);
                badge(data.unread || 0);

                if (row) {
                    read(row);
                }

                if (!again) {
                    pane.scrollTop = 0;
                    pane.querySelector('h2').focus();
                }
            }).catch(function (error) {
                if (current === id) {
                    close();
                }

                h.fail(error);
            });
        }

        function more(link) {
            var data = {};

            new URLSearchParams(link.search).forEach(function (value, key) {
                data[key] = value;
            });

            link.classList.add('disabled');
            link.setAttribute('aria-disabled', 'true');

            h.api('ws_mail_rows', data).then(function (result) {
                var holder = link.parentNode;

                root.querySelector('[data-ws-mail-rows]').insertAdjacentHTML('beforeend', result.html);
                holder.insertAdjacentHTML('afterend', result.more || '');
                holder.parentNode.removeChild(holder);
            }).catch(function (error) {
                link.classList.remove('disabled');
                link.removeAttribute('aria-disabled');
                h.fail(error);
            });
        }

        root.addEventListener('click', function (event) {
            var target = event.target.closest('[data-ws-mail-more], [data-ws-mail-read-all], [data-ws-mail-row], [data-ws-mail-new], [data-ws-mail-forward]');

            // A new tab or window keeps the link's own way.
            if (!target || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();

            if (target.hasAttribute('data-ws-mail-new')) {
                // The new conversation opens in the pane once sent.
                compose(h, { sent: function (data) { open(data.conversation_id); } });
            } else if (target.hasAttribute('data-ws-mail-forward')) {
                // A mail of the pane forwarded; the new conversation opens.
                forward(h, { kind: 'mail', ids: [parseInt(target.getAttribute('data-ws-mail-forward'), 10)] }, { sent: function (data) { open(data.conversation_id); } });
            } else if (target.hasAttribute('data-ws-mail-more')) {
                more(target);
            } else if (target.hasAttribute('data-ws-mail-row')) {
                open(parseInt(target.getAttribute('data-ws-mail-row'), 10));
            } else {
                target.disabled = true;

                h.api('ws_mail_read_all').then(function () {
                    target.disabled = false;
                    each('.ws-mail-unread', read);
                    badge(0);
                    h.toast(w('read_all'), 'success');
                }).catch(function (error) {
                    target.disabled = false;
                    h.fail(error);
                });
            }
        });

        var start = parseInt(new URLSearchParams(window.location.search).get('conversation') || '0', 10);

        if (start > 0) {
            open(start);
        }
    }

    // ── New e-mail ─────────────────────────────────────────────────────
    //
    // The form (From, Kept in, To, Cc, Bcc, Subject, the text, files) in a
    // drawer, opened from the E-mails screen's toolbar and from the menu of
    // a channel a mailbox keeps its mail in. The mailboxes, their channels
    // and Signatures come from the server (ws_mail_compose); Send posts the
    // form with its files as form parts (ws_mail_new), and the mail leaves
    // through the mail queue (includes/workspace/mail_send.php). The unsent
    // form is kept in this browser, one slot per site and person, like the
    // composer's unsent text (workspace.js draftKey()).
    //
    // Forward as e-mail opens the same form filled from chosen channel
    // messages or one mail (ws_mail_forward, includes/workspace/
    // mail_forward.php): a subject, the original quoted under the person's
    // words and its files carried. Send names the source again
    // (forward_kind, forward_ids, forward_files) and the server reads it
    // anew. An unsent forward has a slot of its own, kept for that source.

    var NEW_PREFIX = 'ws-mail-new:';
    var NEW_DAYS = 14;
    var NEW_FIELDS = ['to', 'cc', 'bcc', 'subject', 'text'];

    function newDraftKey(site, viewer) {
        return NEW_PREFIX + String(site || '') + '|' + (parseInt(viewer, 10) || 0);
    }

    // A kept form as { box, channel, to, cc, bcc, subject, text, at } while
    // it is younger than NEW_DAYS and holds words; null otherwise. A kept
    // forward also names its source.
    function newDraftFresh(stored, now) {
        var value = null;

        try {
            value = JSON.parse(String(stored || ''));
        } catch (error) {
            return null;
        }

        if (!value || (typeof value !== 'object') || (typeof value.at !== 'number') || !(value.at > now - NEW_DAYS * 86400000)) {
            return null;
        }

        var out = { box: parseInt(value.box, 10) || 0, channel: parseInt(value.channel, 10) || 0, at: value.at };
        var words = false;

        if (typeof value.source === 'string') {
            out.source = value.source;
        }

        NEW_FIELDS.forEach(function (field) {
            out[field] = (typeof value[field] === 'string') ? value[field] : '';
            words = words || (out[field].trim() !== '');
        });

        return words ? out : null;
    }

    // Every read and write of the slot may fail (a private window, blocked
    // storage, a full quota): the form then works without it.
    function newDraftStore(key, value) {
        try {
            if (value) {
                window.localStorage.setItem(key, JSON.stringify(value));
            } else {
                window.localStorage.removeItem(key);
            }
        } catch (error) {
            // Nothing kept.
        }
    }

    function newDraftRead(key) {
        try {
            var stored = window.localStorage.getItem(key);
            var value = newDraftFresh(stored, Date.now());

            if (stored && !value) {
                window.localStorage.removeItem(key);
            }

            return value;
        } catch (error) {
            return null;
        }
    }

    // The form posted with its files as form parts; read as api() reads an
    // answer.
    function newPost(h, fields, files) {
        var form = new FormData();

        form.append('action', 'ws_mail_new');
        form.append('token', h.cfg.token);
        Object.keys(fields).forEach(function (key) {
            form.append(key, fields[key]);
        });
        files.forEach(function (file) {
            form.append('files[]', file, file.name);
        });

        return fetch(h.cfg.api_url, { method: 'POST', credentials: 'same-origin', body: form }).then(function (response) {
            return response.json().catch(function () {
                throw new Error(h.t('error_generic'));
            });
        }).then(function (json) {
            if (!json || json.status !== 'success') {
                var error = new Error((json && json.message) ? json.message : h.t('error_generic'));

                error.field = json ? json.field : '';
                throw error;
            }

            return json.data || {};
        });
    }

    // options.channel: the channel the form was opened from; its first
    // mailbox is chosen. options.sent(data) runs after a send.
    function compose(h, options) {
        options = options || {};

        h.api('ws_mail_compose').then(function (data) {
            newForm(h, data.boxes || [], data.writer || '', options);
        }).catch(function (error) {
            h.fail(error);
        });
    }

    // source: { kind: 'messages' | 'mail', ids }. options.channel and
    // options.sent as compose() takes them; the source's channel is the
    // default.
    function forward(h, source, options) {
        options = options || {};

        h.api('ws_mail_forward', { kind: source.kind, ids: source.ids.join(',') }).then(function (data) {
            var fill = data.forward || null;

            newForm(h, data.boxes || [], data.writer || '', {
                channel: options.channel || (fill ? fill.channel_id : 0),
                sent: options.sent,
                fill: fill
            });
        }).catch(function (error) {
            h.fail(error);
        });
    }

    function newForm(h, boxes, writer, options) {
        var t = h.t;
        var el = h.el;
        var fill = options.fill || null;
        var title = fill ? t('mail_fwd') : t('mail_new');
        var node = h.offcanvas('ws-mail-compose', title);
        var body = h.clear(node.querySelector('.drawer-body'));
        var footer = h.clear(node.querySelector('.drawer-footer'));
        // A forward keeps its unsent form apart, and only for its source.
        var key = newDraftKey(h.cfg.api_url, h.cfg.me) + (fill ? '|fwd' : '');
        var kept = newDraftRead(key);
        var files = [];
        // The source's files the forward carries; each can be taken out.
        var carried = fill ? fill.files.slice() : [];
        var timer = 0;

        if (fill && kept && (kept.source !== fill.source)) {
            kept = null;
        }

        node.querySelector('.drawer-title').textContent = title;

        if (!boxes.length) {
            footer.classList.add('d-none');
            body.appendChild(el('div', 'ws-empty', t('mail_new_none')));
            h.showOffcanvas(node);
            return;
        }

        footer.classList.remove('d-none');

        var form = el('form', 'ws-mail-new');
        form.noValidate = true;
        form.setAttribute('data-ws-mail-new-form', '');
        body.appendChild(form);

        function boxOf(id) {
            return boxes.filter(function (box) { return box.id === id; })[0] || null;
        }

        // The mailbox: the one keeping its mail in the channel the form was
        // opened from, else the kept form's, else the first. A kept forward
        // keeps the mailbox it was given.
        var start = (fill && kept) ? boxOf(kept.box) : null;

        if (!start && options.channel) {
            start = boxes.filter(function (box) {
                return box.channels.some(function (channel) { return channel.id === options.channel; });
            })[0] || null;
        }

        start = start || (kept && boxOf(kept.box)) || boxes[0];

        var from = h.select(boxes.map(function (box) {
            return [String(box.id), box.name + ' <' + box.address + '>'];
        }), String(start.id));
        from.name = 'box_id';
        form.appendChild(h.formRow(t('mail_new_from'), from));

        var where = el('select', 'form-control form-control-sm');
        where.name = 'channel_id';
        form.appendChild(h.formRow(t('mail_new_channel'), where, t('mail_new_channel_hint')));

        function input(name, label, type, hint) {
            var field = el('input', 'form-control form-control-sm');

            field.type = type || 'text';
            field.name = name;
            field.autocomplete = 'off';

            if (name !== 'subject') {
                // Several addresses, separated by commas.
                field.inputMode = 'email';
                field.spellcheck = false;
                field.setAttribute('autocapitalize', 'off');
            } else {
                field.maxLength = 200;
            }

            var row = h.formRow(label, field, hint);

            form.appendChild(row);

            return { field: field, row: row };
        }

        var to = input('to', t('mail_new_to'), 'text', t('mail_new_addresses'));
        var copies = el('div', 'ws-mail-new-copies');
        var showCopies = h.button('btn btn-sm btn-text theme-secondary ws-mail-new-cc-toggle', t('mail_new_cc_bcc'), 'bi-plus-lg');

        form.appendChild(showCopies);
        form.appendChild(copies);

        var cc = input('cc', t('mail_new_cc'));
        var bcc = input('bcc', t('mail_new_bcc'));

        copies.appendChild(cc.row);
        copies.appendChild(bcc.row);

        var subject = input('subject', t('mail_new_subject'));
        var text = el('textarea', 'form-control');

        text.name = 'text';
        text.rows = 10;
        text.maxLength = 50000;
        form.appendChild(h.formRow(t('mail_new_text'), text));

        // The mailbox's Signature (or its name), as the mail will carry it.
        var signature = el('div', 'ws-mail-new-signature small fg-secondary');

        text.parentNode.appendChild(signature);

        var picker = el('input', 'form-control form-control-sm');
        var list = el('ul', 'ws-mail-new-files list-unstyled mb-0');

        picker.type = 'file';
        picker.multiple = true;

        var fileRow = h.formRow(t('mail_new_files'), picker, t('mail_new_files_hint'));

        fileRow.appendChild(list);
        form.appendChild(fileRow);

        function drawFiles() {
            h.clear(list);
            carried.forEach(function (file, index) {
                var row = el('li', 'd-flex align-items-center gap-2 small mt-2');
                var drop = h.button('btn btn-sm btn-text theme-secondary py-0', '', 'bi-x-lg', t('mail_new_remove') + ': ' + file.name);

                row.appendChild(h.icon('bi-paperclip'));
                row.appendChild(el('span', 'text-truncate', file.name));
                drop.addEventListener('click', function () {
                    carried.splice(index, 1);
                    drawFiles();
                });
                row.appendChild(drop);
                list.appendChild(row);
            });
            files.forEach(function (file, index) {
                var row = el('li', 'd-flex align-items-center gap-2 small mt-2');
                var drop = h.button('btn btn-sm btn-text theme-secondary py-0', '', 'bi-x-lg', t('mail_new_remove') + ': ' + file.name);

                row.appendChild(h.icon('bi-paperclip'));
                row.appendChild(el('span', 'text-truncate', file.name));
                drop.addEventListener('click', function () {
                    files.splice(index, 1);
                    drawFiles();
                });
                row.appendChild(drop);
                list.appendChild(row);
            });
        }

        picker.addEventListener('change', function () {
            Array.prototype.forEach.call(picker.files || [], function (file) {
                files.push(file);
            });
            picker.value = '';
            drawFiles();
        });

        function drawBox(channelId) {
            var box = boxOf(parseInt(from.value, 10)) || boxes[0];
            var chosen = channelId || parseInt(where.value, 10) || 0;

            h.clear(where);
            box.channels.forEach(function (channel) {
                var option = el('option', '', '#' + channel.name);

                option.value = String(channel.id);
                option.selected = (channel.id === chosen);
                where.appendChild(option);
            });

            h.clear(signature);
            signature.appendChild(el('div', '', t('mail_new_signature')));
            signature.appendChild(el('div', 'ws-mail-new-signature-text', '-- \n' + (writer ? writer + '\n' : '') + (box.signature || box.name)));
        }

        function current() {
            var value = {
                box: parseInt(from.value, 10) || 0,
                channel: parseInt(where.value, 10) || 0,
                to: to.field.value,
                cc: cc.field.value,
                bcc: bcc.field.value,
                subject: subject.field.value,
                text: text.value,
                at: Date.now()
            };

            if (fill) {
                value.source = fill.source;
            }

            return value;
        }

        // The forward's subject and quote, the person's words to go above.
        function filled() {
            subject.field.value = fill.subject;
            text.value = fill.text;
            text.setSelectionRange(0, 0);
        }

        // Saved 400 ms after typing pauses; an empty form drops the slot.
        function keep() {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                var value = current();

                newDraftStore(key, newDraftFresh(JSON.stringify(value), value.at) ? value : null);
            }, 400);
        }

        function setCopies(open) {
            copies.hidden = !open;
            showCopies.hidden = open;
        }

        if (kept) {
            to.field.value = kept.to;
            cc.field.value = kept.cc;
            bcc.field.value = kept.bcc;
            subject.field.value = kept.subject;
            text.value = kept.text;
        } else if (fill) {
            filled();
        }

        var keptChannel = (kept && (start.id === kept.box)) ? kept.channel : 0;

        drawBox(fill ? (keptChannel || options.channel || 0) : (options.channel || keptChannel));
        setCopies(!!(cc.field.value || bcc.field.value));
        drawFiles();

        // The source's files that do not fit a mail are named, not carried.
        if (fill && fill.left.length) {
            form.insertBefore(el('div', 'alert theme-warning small mb-5', t('mail_fwd_left', fill.left.join(', '))), form.firstChild);
        }

        from.addEventListener('change', function () {
            drawBox(0);
            keep();
        });
        where.addEventListener('change', keep);
        showCopies.addEventListener('click', function () {
            setCopies(true);
            cc.field.focus();
        });
        form.addEventListener('input', keep);

        if (kept) {
            var note = el('div', 'alert theme-info small d-flex align-items-center gap-3 mb-5', '');
            var discard = h.button('btn btn-sm btn-text theme-secondary ms-auto py-0', t('mail_new_discard'), 'bi-trash');

            note.appendChild(el('span', '', t('mail_new_draft')));
            discard.addEventListener('click', function () {
                window.clearTimeout(timer);
                newDraftStore(key, null);
                form.reset();
                files = [];
                carried = fill ? fill.files.slice() : [];

                if (fill) {
                    filled();
                }

                drawFiles();
                drawBox(0);
                setCopies(false);
                note.parentNode.removeChild(note);
                to.field.focus();
            });
            note.appendChild(discard);
            form.insertBefore(note, form.firstChild);
        }

        var send = h.button('btn btn-solid theme-primary rounded-pill px-5', t('mail_new_send'), 'bi-send');

        send.addEventListener('click', function () {
            var fields = current();
            var empty = [[to.field, fields.to], [subject.field, fields.subject], [text, fields.text]].filter(function (pair) {
                return !pair[1].trim();
            })[0];

            if (empty) {
                empty[0].focus();
                return;
            }

            send.disabled = true;

            var post = {
                box_id: fields.box, channel_id: fields.channel, to: fields.to, cc: fields.cc, bcc: fields.bcc,
                subject: fields.subject, text: fields.text
            };

            if (fill) {
                post.forward_kind = fill.kind;
                post.forward_ids = fill.ids.join(',');
                post.forward_files = carried.map(function (file) { return file.id; }).join(',');
            }

            newPost(h, post, files).then(function (data) {
                window.clearTimeout(timer);
                newDraftStore(key, null);
                send.disabled = false;
                h.hideOffcanvas(node);
                h.toast(data.state === 'failed' ? t('mail_new_failed') : t('mail_new_sent'), data.state === 'failed' ? 'warning' : 'success');

                if (options.sent) {
                    options.sent(data);
                }
            }).catch(function (error) {
                send.disabled = false;
                h.fail(error);
            });
        });

        footer.appendChild(send);
        h.showOffcanvas(node);
        (to.field.value ? text : to.field).focus();
    }

    window.PGWsMail = {
        node: node,
        draft: draftNode,
        plain: plain,
        screen: screen,
        compose: compose,
        forward: forward,
        newDraftKey: newDraftKey,
        newDraftFresh: newDraftFresh
    };
})();
