/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - answering an e-mail conversation by e-mail on the channel
 * screen: the "Reply by e-mail" button under a conversation's card and the
 * drawer the answer is written in.
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

            h.api('ws_mail_reply', { conversation_id: message.mail_reply, text: text.value }).then(function () {
                h.hideOffcanvas(node);
                h.toast(t('mail_reply_sent'), 'success');
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

        return row;
    }

    window.PGWsMail = {
        node: node
    };
})();
