/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - answering an e-mail conversation by e-mail on the channel
 * screen: the "Reply by e-mail" button under a conversation's card, the
 * drawer the answer is written in (with the ; list of saved replies of
 * assets/js/workspace_replies.js, put in as plain text), and the
 * conversation's status and holder, given to anybody of the channel
 * (includes/workspace/mail_status.php). On the E-mails screen the list,
 * its reading pane and the unread marks (screen()). New e-mail (write()),
 * from the screen's toolbar and a mailbox channel's menu, with its unsent
 * form kept in this browser (includes/workspace/mail_compose.php).
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

    // The unsent New e-mail form: one slot per site and person, its own
    // fields only, gone after DRAFT_DAYS days. Files are not kept.
    var DRAFT_DAYS = 14;
    var DRAFT_FIELDS = ['box', 'channel', 'to', 'cc', 'bcc', 'subject', 'text'];

    function draftKey(cfg) {
        return 'ws-mail-new:' + String(cfg.api_url || '') + '|' + String(cfg.me || 0);
    }

    // The kept form, or null when it is too old, broken or empty.
    function draftRead(raw, now) {
        var data = null;
        var draft = {};
        var filled = false;

        try {
            data = JSON.parse(raw);
        } catch (error) {
            return null;
        }

        if (!data || (typeof data !== 'object') || !(Number(data.at) > now - DRAFT_DAYS * 86400000)) {
            return null;
        }

        DRAFT_FIELDS.forEach(function (key) {
            if ((key === 'box') || (key === 'channel')) {
                draft[key] = parseInt(data[key], 10) || 0;
                return;
            }

            draft[key] = (typeof data[key] === 'string') ? data[key] : '';
            filled = filled || (draft[key].trim() !== '');
        });

        return filled ? draft : null;
    }

    // The drawer: who it goes to, the answer, Send. A draft fills the
    // answer; nothing leaves before the member presses Send.
    function compose(message, view, h, draft) {
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
            compose(message, view, h);
        });

        row.appendChild(open);

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
            compose({ mail_reply: offer.conversation_id, sender: { title: offer.to } }, view, h, offer.text);
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
                compose(message, view, h);
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
            var target = event.target.closest('[data-ws-mail-more], [data-ws-mail-read-all], [data-ws-mail-row], [data-ws-mail-new]');

            // A new tab or window keeps the link's own way.
            if (!target || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();

            if (target.hasAttribute('data-ws-mail-more')) {
                more(target);
            } else if (target.hasAttribute('data-ws-mail-new')) {
                // The sent mail's conversation opens in the pane.
                write(h, {
                    onSent: function (data) {
                        if (data.conversation_id) {
                            open(data.conversation_id);
                        }
                    }
                });
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

    // The New e-mail drawer (includes/workspace/mail_compose.php): From,
    // the channel the mail is kept in, To, Cc, Bcc, Subject, the text with
    // the mailbox's signature under it, and files. options.channel_id
    // picks the channel a channel's menu opened it from; options.onSent
    // runs with the answer of ws_mail_new. The unsent form stays in this
    // browser (draftKey()); its files do not.
    function write(h, options) {
        var opts = options || {};

        h.api('ws_mail_compose', { channel_id: opts.channel_id || 0 }).then(function (offer) {
            if (!offer.boxes || !offer.boxes.length) {
                h.toast(h.t('mail_new_none'), 'warning');
                return;
            }

            writeForm(offer, h, opts);
        }).catch(h.fail);
    }

    function writeForm(offer, h, opts) {
        var t = h.t;
        var el = h.el;
        var limits = offer.limits || {};
        var key = draftKey(h.cfg || {});
        var kept = null;

        try {
            kept = draftRead(window.localStorage.getItem(key), Date.now());
        } catch (error) {
            kept = null;
        }

        var node = h.offcanvas('ws-mail-new', t('mail_new'));
        var body = h.clear(node.querySelector('.drawer-body'));
        var footer = h.clear(node.querySelector('.drawer-footer'));
        var files = [];
        var done = false;
        var timer = 0;

        node.querySelector('.drawer-title').textContent = t('mail_new');
        node.style.width = 'min(40rem, 100vw)';
        footer.classList.remove('d-none');

        function boxById(id) {
            return offer.boxes.filter(function (item) { return item.id === id; })[0] || null;
        }

        function boxOfChannel(id) {
            return offer.boxes.filter(function (item) {
                return item.channels.some(function (channel) { return channel.id === id; });
            })[0] || null;
        }

        // A channel menu's channel comes first, then the kept form's, then
        // the server's default.
        var box = boxById(offer.box_id) || offer.boxes[0];
        var channelId = offer.channel_id;

        if (!opts.channel_id && kept && boxById(kept.box) && (boxOfChannel(kept.channel) === boxById(kept.box))) {
            box = boxById(kept.box);
            channelId = kept.channel;
        }

        function input(max, value) {
            var field = el('input', 'form-control form-control-sm');

            field.type = 'text';
            field.maxLength = max;
            field.value = value || '';
            field.autocomplete = 'off';

            return field;
        }

        var from = h.select(offer.boxes.map(function (item) {
            return [item.id, item.address ? item.name + ' <' + item.address + '>' : item.name];
        }), box.id);
        var channel = el('select', 'form-control form-control-sm');
        var to = input(2000, kept ? kept.to : '');
        var cc = input(2000, kept ? kept.cc : '');
        var bcc = input(2000, kept ? kept.bcc : '');
        var subject = input(200, kept ? kept.subject : '');
        var text = el('textarea', 'form-control');
        var sign = el('div', 'small fg-secondary');
        var picker = el('input', 'form-control form-control-sm');
        var list = el('ul', 'list-unstyled small mb-0 mt-2');

        [to, cc, bcc].forEach(function (field) {
            field.inputMode = 'email';
            field.spellcheck = false;
        });

        text.rows = 10;
        text.maxLength = limits.text || 50000;
        text.value = kept ? kept.text : '';
        sign.style.whiteSpace = 'pre-wrap';
        picker.type = 'file';
        picker.multiple = true;

        function channels() {
            h.clear(channel);

            if (!box.channels.some(function (item) { return item.id === channelId; })) {
                channelId = box.channels[0].id;
            }

            box.channels.forEach(function (item) {
                var option = el('option', '', item.name);

                option.value = String(item.id);
                option.selected = (item.id === channelId);
                channel.appendChild(option);
            });
        }

        // What the server puts under the text (ws_mail_reply_body()).
        function signature() {
            sign.textContent = '-- \n' + (offer.writer || '') + '\n' + (box.signature || box.name);
        }

        function save() {
            clearTimeout(timer);
            timer = 0;

            if (done) {
                return;
            }

            try {
                if (![to, cc, bcc, subject, text].some(function (field) { return field.value.trim() !== ''; })) {
                    window.localStorage.removeItem(key);
                    return;
                }

                window.localStorage.setItem(key, JSON.stringify({
                    box: box.id, channel: channelId, to: to.value, cc: cc.value, bcc: bcc.value,
                    subject: subject.value, text: text.value, at: Date.now()
                }));
            } catch (error) {
                // A full or closed storage keeps nothing; the form still works.
            }
        }

        function later() {
            clearTimeout(timer);
            timer = setTimeout(save, 400);
        }

        function forget() {
            done = true;
            clearTimeout(timer);

            try {
                window.localStorage.removeItem(key);
            } catch (error) {
                // Nothing was kept.
            }
        }

        function mb(bytes) {
            return Math.round(bytes / 104857.6) / 10;
        }

        function drawFiles() {
            h.clear(list);
            files.forEach(function (file, index) {
                var row = el('li', 'd-flex align-items-center gap-2 py-1');
                var remove = h.button('btn btn-sm btn-text theme-secondary ms-auto', '', 'bi-x-lg', t('mail_new_file_remove', file.name));

                row.appendChild(h.icon('bi-paperclip', 'fg-secondary'));
                row.appendChild(el('span', 'text-truncate', file.name));
                row.appendChild(el('span', 'fg-secondary text-nowrap', mb(file.size) + ' MB'));
                remove.addEventListener('click', function () {
                    files.splice(index, 1);
                    drawFiles();
                    picker.focus();
                });
                row.appendChild(remove);
                list.appendChild(row);
            });
        }

        // The server checks them again (ws_mail_send()).
        picker.addEventListener('change', function () {
            var total = files.reduce(function (sum, file) { return sum + file.size; }, 0);

            Array.prototype.forEach.call(picker.files || [], function (file) {
                if (files.length >= limits.files) {
                    h.toast(t('mail_new_files_many', limits.files), 'warning');
                } else if (limits.file && (file.size > limits.file)) {
                    h.toast(t('mail_new_file_large', file.name, mb(limits.file)), 'warning');
                } else if (total + file.size > limits.bytes) {
                    h.toast(t('mail_new_files_large', mb(limits.bytes)), 'warning');
                } else {
                    total += file.size;
                    files.push(file);
                }
            });
            picker.value = '';
            drawFiles();
        });

        from.addEventListener('change', function () {
            box = boxById(parseInt(from.value, 10)) || box;
            channels();
            signature();
            later();
        });
        channel.addEventListener('change', function () {
            channelId = parseInt(channel.value, 10) || channelId;
            later();
        });
        [to, cc, bcc, subject, text].forEach(function (field) {
            field.addEventListener('input', later);
        });

        if (kept) {
            body.appendChild(el('div', 'alert theme-secondary small py-3', t('mail_new_restored')));
        }

        var textBox = el('div', 'ws-mail-reply-box position-relative');
        var fileBox = el('div', '');

        textBox.appendChild(text);
        fileBox.appendChild(picker);
        fileBox.appendChild(list);

        body.appendChild(h.formRow(t('mail_new_from'), from));
        body.appendChild(h.formRow(t('mail_new_channel'), channel, t('mail_new_channel_hint')));
        body.appendChild(h.formRow(t('mail_new_to'), to, t('mail_new_to_hint', limits.addresses)));
        body.appendChild(h.formRow(t('mail_new_cc'), cc));
        body.appendChild(h.formRow(t('mail_new_bcc'), bcc));
        body.appendChild(h.formRow(t('mail_new_subject'), subject));
        body.appendChild(h.formRow(t('mail_new_text'), textBox));
        body.appendChild(h.formRow(t('mail_new_signature'), sign));
        body.appendChild(h.formRow(t('mail_new_files'), fileBox, t('mail_new_files_hint', limits.files, mb(limits.bytes))));
        channels();
        signature();
        savedReplies(text, textBox, { channel: { id: channelId } }, h);

        var discard = h.button('btn btn-text theme-secondary', t('mail_new_discard'), 'bi-trash');
        var send = h.button('btn btn-solid theme-primary rounded-pill px-5', t('mail_new_send'), 'bi-send');
        var fields = { to: to, subject: subject, text: text, files: picker, box: from, channel: channel };

        discard.addEventListener('click', function () {
            h.ask(t('mail_new_discard_ask'), t('mail_new_discard'), true).then(function (yes) {
                if (yes) {
                    forget();
                    h.hideOffcanvas(node);
                }
            });
        });

        send.addEventListener('click', function () {
            if (!to.value.trim()) {
                to.focus();
                return;
            }

            if (!subject.value.trim()) {
                subject.focus();
                return;
            }

            var form = new FormData();

            send.disabled = true;
            save();
            form.append('action', 'ws_mail_new');
            form.append('token', h.cfg.token);
            form.append('box_id', String(box.id));
            form.append('channel_id', String(channelId));
            form.append('to', to.value);
            form.append('cc', cc.value);
            form.append('bcc', bcc.value);
            form.append('subject', subject.value);
            form.append('text', text.value);
            files.forEach(function (file) {
                form.append('files[]', file, file.name);
            });

            post(form, h).then(function (data) {
                var states = { queued: ['mail_new_queued', 'success'], sent: ['mail_new_sent', 'success'], failed: ['mail_new_failed', 'warning'] };
                var told = states[data.state] || states.queued;

                forget();
                h.hideOffcanvas(node);
                h.toast(t(told[0]), told[1]);

                if (data.refused && data.refused.length) {
                    h.toast(t('mail_new_refused_files', data.refused.join(', ')), 'warning');
                }

                if (opts.onSent) {
                    opts.onSent(data);
                }
            }).catch(function (error) {
                send.disabled = false;
                h.fail(error);

                if (error.field && fields[error.field]) {
                    fields[error.field].focus();
                }
            });
        });

        // Closing the drawer keeps what was written at once; the listener
        // goes on once, the drawer itself is kept for the next form.
        node.wsMailNewSave = save;

        if (!node.wsMailNewHooked) {
            node.wsMailNewHooked = true;
            window.pgUI.drawer.on(node, 'hidden', function () {
                node.wsMailNewSave();
            });
        }

        footer.appendChild(discard);
        footer.appendChild(send);
        h.showOffcanvas(node);
        (to.value ? (subject.value ? text : subject) : to).focus();
    }

    // The form with its files as parts, its answer read as workspace.js's
    // api() reads one; a web server that refused the body before PHP saw
    // it answers 413 (nginx) or 404 (IIS 404.13).
    function post(form, h) {
        return fetch(h.cfg.api_url, { method: 'POST', credentials: 'same-origin', body: form }).then(function (response) {
            return response.json().catch(function () {
                throw new Error(((response.status === 413) || (response.status === 404)) ? h.t('upload_too_large_server') : h.t('error_generic'));
            });
        }).then(function (json) {
            if (!json || json.status !== 'success') {
                var error = new Error((json && json.message) ? json.message : h.t('error_generic'));

                error.field = (json && json.field) ? json.field : '';
                throw error;
            }

            return json.data || {};
        });
    }

    window.PGWsMail = {
        node: node,
        draft: draftNode,
        plain: plain,
        screen: screen,
        write: write
    };
})();
