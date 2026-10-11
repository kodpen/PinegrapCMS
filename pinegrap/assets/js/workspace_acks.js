/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - read receipts on the channel screen: the "I have read it"
 * button and the "N / M read" count under a message that asks for them, the
 * same in the bar of the pinned message, the list of who has read it and who
 * has not, and the overview's list of messages waiting for the reader.
 *
 * assets/js/workspace.js draws the conversation and calls in here with its
 * own helpers. What may be done - ask, answer, see the list - is decided by
 * the server (includes/workspace/acks.php) and checked again when it is
 * sent. Every text comes from the #ws-config block.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Who has read the message and who has not, in a drawer.
    function people(messageId, h) {
        var t = h.t;
        var el = h.el;
        var node = h.offcanvas('ws-ack-people', t('ack_people'));
        var body = h.clear(node.querySelector('.offcanvas-body'));

        h.clear(node.querySelector('.offcanvas-footer')).classList.add('d-none');

        h.api('ws_ack_people', { message_id: messageId }).then(function (data) {
            [['read', t('ack_read')], ['unread', t('ack_unread')]].forEach(function (part) {
                var list = data[part[0]] || [];

                body.appendChild(el('div', 'form-label mt-2', part[1] + ' (' + list.length + ')'));

                if (!list.length && part[0] === 'unread') {
                    body.appendChild(el('div', 'small text-body-secondary', t('ack_nobody_left')));
                }

                list.forEach(function (person) {
                    var row = el('div', 'ws-ack-person');
                    var face = h.avatar(person, '1.6rem');

                    face.style.borderRadius = '50%';
                    row.appendChild(face);
                    row.appendChild(el('span', 'flex-grow-1', person.name));

                    if (person.time) {
                        row.appendChild(el('small', 'text-body-secondary', person.time));
                    }

                    body.appendChild(row);
                });
            });
        }).catch(function (error) {
            h.hideOffcanvas(node);
            h.fail(error);
        });

        h.showOffcanvas(node);
    }

    // The row under a message (or in the pinned bar): the button for the
    // people asked, the count, the list for those who may see it.
    // target: {id, ack}; done(data) gets the answer of ws_ack.
    function node(target, h, done) {
        var t = h.t;
        var el = h.el;
        var ack = target.ack;
        var row = el('div', 'ws-ack' + (ack.done ? ' ws-ack-done' : ''));

        if (ack.can_ack) {
            var read = h.button('btn btn-sm btn-outline-primary py-0', t('ack_button'), 'bi-eye');

            read.addEventListener('click', function (event) {
                event.stopPropagation();
                read.disabled = true;

                h.api('ws_ack', { message_id: target.id }).then(function (data) {
                    done(data);
                }).catch(function (error) {
                    read.disabled = false;
                    h.fail(error);
                });
            });
            row.appendChild(read);
        } else if (ack.mine) {
            var mine = el('span', 'ws-ack-mine');
            mine.appendChild(h.icon('bi-check2', 'me-1'));
            mine.appendChild(document.createTextNode(t('ack_done_mine')));
            row.appendChild(mine);
        }

        var count = ack.can_list ? h.button('ws-ack-count', t('ack_count', ack.count, ack.total), ack.done ? 'bi-check2-all' : 'bi-eye')
            : el('span', 'ws-ack-count', t('ack_count', ack.count, ack.total));

        if (ack.can_list) {
            count.title = t('ack_people');
            count.addEventListener('click', function (event) {
                event.stopPropagation();
                people(target.id, h);
            });
        }

        row.appendChild(count);

        return row;
    }

    // Under a message of the conversation.
    function messageNode(message, view, h) {
        return node(message, h, function (data) {
            if (data && data.message) {
                view.replaceMessage(data.message);
            }

            view.sync();
        });
    }

    // Ask for read receipts on a message, or stop asking.
    function request(message, on, view, h, after) {
        var run = function () {
            h.api('ws_ack_request', { message_id: message.id, on: on ? 1 : 0 }).then(function (data) {
                if (data && data.message) {
                    view.replaceMessage(data.message);
                }

                if (after) {
                    after(data);
                }

                view.sync();
            }).catch(h.fail);
        };

        if (on) {
            run();
            return;
        }

        h.ask(h.t('ack_unrequest_confirm'), h.t('ack_unrequest'), true).then(function (yes) {
            if (yes) {
                run();
            }
        });
    }

    // In the bar of the pinned message: the same row, or a way to ask.
    function pinNode(pin, view, h) {
        var redraw = function (data) {
            if (data && data.message) {
                pin.ack = data.message.ack;
                pin.ack_can_request = !!data.message.ack_can_request;

                if (view.findMessage(data.message.id)) {
                    view.replaceMessage(data.message);
                }
            }

            view.drawPinBar();
        };

        if (pin.ack) {
            return node(pin, h, redraw);
        }

        if (!pin.ack_can_request) {
            return null;
        }

        var ask = h.button('btn btn-sm btn-ghost py-0', '', 'bi-eye', h.t('ack_request'));

        ask.addEventListener('click', function () {
            request({ id: pin.id }, true, view, h, redraw);
        });

        return ask;
    }

    // The overview's list of messages waiting for the reader's receipt;
    // nothing when there are none.
    function home(data, view, h) {
        var items = (data && data.acks) || [];

        if (!items.length) {
            return null;
        }

        var t = h.t;
        var el = h.el;
        var section = h.homeCard(t('ack_home'), 'bi-eye');

        items.forEach(function (item) {
            var row = h.button('ws-home-row ws-home-unread');
            var text = el('span', 'ws-home-row-text');
            var meta = el('small', 'ws-home-row-meta');

            row.appendChild(h.icon('bi-eye', 'text-body-secondary'));
            meta.appendChild(el('b', '', t('ack_home_asked', item.asker)));
            meta.appendChild(document.createTextNode(' · '));
            meta.appendChild(h.icon(item.channel['private'] ? 'bi-lock' : 'bi-hash'));
            meta.appendChild(document.createTextNode(item.channel.name + ' · ' + item.time));
            text.appendChild(meta);
            text.appendChild(el('span', 'ws-home-row-quote', item.text));
            row.appendChild(text);
            row.addEventListener('click', function () { view.open(item.channel.id, item.message_id); });
            section.body.appendChild(row);
        });

        return section.node;
    }

    window.PGWsAcks = {
        node: messageNode,
        request: request,
        pinNode: pinNode,
        home: home
    };
})();
