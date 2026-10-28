/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the customer asked to approve a task, in the task's drawer.
 *
 * assets/js/workspace.js asks this file for its part and lends it its own
 * helpers (api, t, el, button, toast, ...):
 *   PGWsTaskApproval.drawer(task, helpers)  the Customer approval part
 *
 * The part lists the approval requests about the task - who was asked and
 * how each answered - and, for staff who may post in the task's channel,
 * asks a customer: a name, an e-mail address, what is to be approved and an
 * optional message and deadline. The server writes the request into the
 * task's channel and sends the link (includes/workspace/approval_guests.php);
 * what it sends back is drawn again.
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

    var STATES = {
        pending: ['bi-hourglass-split', 'apv_pending'],
        approved: ['bi-check-circle-fill', 'apv_has_approved'],
        rejected: ['bi-x-circle-fill', 'apv_has_rejected']
    };

    var OUTCOMES = {
        approved: 'bi-patch-check-fill fg-success',
        rejected: 'bi-patch-exclamation-fill fg-danger',
        expired: 'bi-hourglass-bottom fg-secondary',
        withdrawn: 'bi-slash-circle fg-secondary'
    };

    // Today as the date field reads it, in the reader's own time.
    function today() {
        var now = new Date();
        var pad = function (value) { return (value < 10 ? '0' : '') + value; };

        return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
    }

    function drawer(task, h) {
        var t = h.t;
        var el = h.el;
        var wrap = el('div', 'ws-task-approvals');

        function changed(approvals) {
            if (approvals) {
                task.approvals = approvals;
            }

            draw();
        }

        function person(item, who) {
            var state = STATES[who.decision] || STATES.pending;
            var row = el('div', 'ws-task-approvals-person ws-approval-' + who.decision);
            var face = h.avatar(who);

            face.classList.add('ws-approval-face');
            row.appendChild(face);
            row.appendChild(el('span', 'ws-approval-name', who.name));

            if (who.guest) {
                row.appendChild(el('span', 'badge rounded-pill badge-subtle theme-secondary ws-approval-guest', t('apv_guest_badge')));

                var detail = [who.email, who.audit].filter(function (part) { return part; }).join(' · ');

                if (detail) {
                    row.title = detail;
                }
            }

            var badge = el('span', 'ws-approval-state');
            badge.appendChild(h.icon(state[0], 'me-1'));
            badge.appendChild(document.createTextNode(t(state[1]) + (who.time ? ' · ' + who.time : '')));
            row.appendChild(badge);

            if (who.can_resend) {
                var again = h.button('btn btn-sm btn-text theme-secondary py-0 px-1', '', 'bi-envelope', t('apv_guest_resend'));

                again.addEventListener('click', function () {
                    again.disabled = true;
                    h.api('ws_approval_guest_resend', { approval_id: item.id, guest_id: who.guest_id, task_id: task.id }).then(function (data) {
                        h.toast(t('apv_guest_resent'));
                        changed(data.approvals);
                    }).catch(function (error) {
                        again.disabled = false;
                        h.fail(error);
                    });
                });
                row.appendChild(again);
            }

            if (who.note) {
                row.appendChild(el('span', 'ws-approval-note', who.note));
            }

            return row;
        }

        function request(item) {
            var box = el('div', 'ws-task-approvals-item');
            var head = el('div', 'ws-task-approvals-item-head');

            head.appendChild(h.icon(item.open ? 'bi-patch-question fg-warning' : (OUTCOMES[item.outcome] || OUTCOMES.withdrawn)));
            head.appendChild(el('span', 'fw-semibold ws-grow text-truncate', item.title));
            head.appendChild(el('span', 'ws-task-links-tag', item.state + ((item.open && item.closes) ? ' · ' + t('apv_closes_on', item.closes) : '')));
            box.appendChild(head);

            (item.people || []).forEach(function (who) {
                box.appendChild(person(item, who));
            });

            if (item.url) {
                var open = el('a', 'small', t('tap_open'));
                open.href = item.url;
                box.appendChild(open);
            }

            return box;
        }

        function form(ask) {
            var box = el('div', 'ws-task-approvals-form');
            var field = function (label, input, help) {
                var row = el('div', 'mb-3');
                var caption = el('label', 'form-label small mb-1', label);

                input.id = h.nextId('ws-tap-');
                caption.htmlFor = input.id;
                row.appendChild(caption);
                row.appendChild(input);

                if (help) {
                    row.appendChild(el('div', 'form-text', help));
                }

                return row;
            };
            var input = function (type, max, value) {
                var node = el('input', 'form-control form-control-sm');

                node.type = type;
                node.maxLength = max;
                node.value = value || '';

                return node;
            };

            var subject = input('text', 255, task.title);
            var name = input('text', 60);
            var mail = input('email', 255);
            var text = el('textarea', 'form-control form-control-sm');
            var date = h.dateField(input('text', 10), 'date');
            var grid = el('div', 'row g-3');
            var nameCell = el('div', 'sm:col-5');
            var mailCell = el('div', 'sm:col-7');

            mail.autocomplete = 'off';
            text.rows = 3;
            text.maxLength = 3000;
            date.min = today();

            nameCell.appendChild(field(t('apv_guest_name'), name));
            mailCell.appendChild(field(t('apv_guest_email'), mail));
            grid.appendChild(nameCell);
            grid.appendChild(mailCell);

            box.appendChild(field(t('tap_subject'), subject));
            box.appendChild(grid);
            box.appendChild(field(t('tap_text'), text, t('tap_text_help')));
            box.appendChild(field(t('tap_deadline'), date, t('tap_deadline_help')));
            box.appendChild(el('div', 'form-text mb-3', t('tap_help')));

            var buttons = el('div', 'd-flex gap-1 justify-content-end');
            var cancel = h.button('btn btn-sm btn-text theme-secondary', t('cancel'));
            var send = h.button('btn btn-sm btn-solid theme-primary rounded-pill px-5', t('tap_send'), 'bi-send');

            cancel.addEventListener('click', function () {
                box.remove();
                ask.classList.remove('d-none');
            });

            send.addEventListener('click', function () {
                send.disabled = true;
                h.api('ws_task_approval_create', {
                    task_id: task.id,
                    title: subject.value,
                    text: text.value,
                    guest_name: name.value,
                    guest_email: mail.value,
                    closes_date: date.value,
                    closes_time: '18:00'
                }).then(function (data) {
                    h.toast(t('tap_sent'));
                    changed(data.approvals);
                }).catch(function (error) {
                    send.disabled = false;
                    h.fail(error);
                });
            });

            buttons.appendChild(cancel);
            buttons.appendChild(send);
            box.appendChild(buttons);

            return { node: box, focus: function () { name.focus(); } };
        }

        function draw() {
            var data = task.approvals;

            h.clear(wrap);

            var head = el('div', 'ws-task-links-head');
            head.appendChild(h.icon('bi-patch-check', 'me-1'));
            head.appendChild(el('span', 'fw-semibold', t('tap_title')));
            wrap.appendChild(head);

            if (!data.items.length) {
                wrap.appendChild(el('div', 'small fg-secondary mt-1', t('tap_none')));
            }

            data.items.forEach(function (item) {
                wrap.appendChild(request(item));
            });

            if (data.can_ask) {
                var ask = h.button('btn btn-sm btn-outline theme-secondary mt-3', t('tap_ask'), 'bi-envelope-paper');

                ask.addEventListener('click', function () {
                    var opened = form(ask);

                    ask.classList.add('d-none');
                    wrap.appendChild(opened.node);
                    opened.focus();
                });
                wrap.appendChild(ask);
            }
        }

        draw();

        return wrap;
    }

    window.PGWsTaskApproval = {
        drawer: drawer
    };
})();
