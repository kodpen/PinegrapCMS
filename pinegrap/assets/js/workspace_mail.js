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

        if (message.mail_state) {
            state(row, message, view, h);
        }

        return row;
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
            var target = event.target.closest('[data-ws-mail-more], [data-ws-mail-read-all], [data-ws-mail-row]');

            // A new tab or window keeps the link's own way.
            if (!target || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();

            if (target.hasAttribute('data-ws-mail-more')) {
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

    window.PGWsMail = {
        node: node,
        draft: draftNode,
        plain: plain,
        screen: screen
    };
})();
