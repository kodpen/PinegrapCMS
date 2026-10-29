/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - answering an e-mail conversation by e-mail on the channel
 * screen: the "Reply by e-mail" button under a conversation's card, the
 * drawer the answer is written in, and the conversation's status and
 * holder, given to anybody of the channel (includes/workspace/mail_status.php).
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

    // The drawer: who it goes to, the answer, Send.
    function compose(message, view, h) {
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
        body.appendChild(h.formRow(t('mail_reply_text'), text, t('mail_reply_hint')));

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

    window.PGWsMail = {
        node: node
    };
})();
