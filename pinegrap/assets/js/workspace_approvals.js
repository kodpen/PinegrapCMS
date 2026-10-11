/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - approval requests on the channel screen: the card under a
 * request, the form that asks for one, the overview's list of requests
 * waiting for the reader, and the box of a scheduled action that asks for
 * one in a channel.
 *
 * assets/js/workspace.js draws the conversation and calls in here with its
 * own helpers (api, t, el, button, avatar, offcanvas, the people picker and
 * the formatted text field), so the card looks like the rest of the screen.
 * Everything the card offers is decided by the server
 * (includes/workspace/approvals.php): approve and reject only for the people
 * asked, take back for the person who asked, close for staff; the server
 * checks each again when it is sent. Every text comes from the #ws-config
 * block.
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
        approved: ['bi-patch-check-fill', 'apv_approved'],
        rejected: ['bi-patch-exclamation-fill', 'apv_rejected'],
        expired: ['bi-hourglass-bottom', 'apv_expired'],
        withdrawn: ['bi-slash-circle', 'apv_withdrawn']
    };

    // After an answer: the card drawn again from the server's word, and the
    // conversation read for the decision it may have written.
    function refreshed(view, data) {
        if (data && data.message) {
            view.replaceMessage(data.message);
        }

        view.sync(true);
    }

    // The card under a request message.
    function card(message, view, h) {
        var approval = message.approval;
        var t = h.t;
        var el = h.el;
        var box = el('div', 'ws-approval ws-approval-' + (approval.open ? 'open' : approval.outcome));
        var head = el('div', 'ws-approval-head');

        head.appendChild(h.icon(approval.open ? 'bi-patch-question' : (OUTCOMES[approval.outcome] || OUTCOMES.withdrawn)[0]));
        head.appendChild(el('span', 'fw-semibold', t('apv_card')));
        head.appendChild(el('span', 'ws-approval-tag', approval.rule_label));

        if (approval.asker) {
            head.appendChild(el('span', 'ws-approval-asker', t('apv_asked_by', approval.asker.name)));
        }

        var when = approval.open
            ? (approval.closes ? t('apv_closes_on', approval.closes) : '')
            : t((OUTCOMES[approval.outcome] || OUTCOMES.withdrawn)[1]) + ' · ' + t('apv_closed_on', approval.closed_on);

        if (when) {
            head.appendChild(el('span', 'ws-approval-when', when));
        }

        box.appendChild(head);

        // The record it is about: its chip opens where else it came up.
        if (approval.record) {
            var record = h.button('ws-approval-record', '', approval.record.icon || 'bi-link-45deg');

            record.setAttribute('data-ws-record', approval.record.type + ':' + approval.record.id);
            record.setAttribute('data-ws-label', approval.record.label);
            record.appendChild(el('span', 'ws-approval-record-label', approval.record.label));

            if (approval.record.meta) {
                record.appendChild(el('span', 'ws-approval-record-meta', approval.record.meta));
            }

            box.appendChild(record);
        }

        var list = el('ul', 'ws-approval-people');

        (approval.people || []).forEach(function (person) {
            var state = STATES[person.decision] || STATES.pending;
            var row = el('li', 'ws-approval-person ws-approval-' + person.decision);
            var face = h.avatar(person);

            face.className = 'ws-approval-face';
            row.appendChild(face);
            row.appendChild(el('span', 'ws-approval-name', person.name));

            var badge = el('span', 'ws-approval-state');
            badge.appendChild(h.icon(state[0], 'me-1'));
            badge.appendChild(document.createTextNode(t(state[1]) + (person.time ? ' · ' + person.time : '')));
            row.appendChild(badge);

            if (person.note) {
                row.appendChild(el('span', 'ws-approval-note', person.note));
            }

            list.appendChild(row);
        });

        box.appendChild(list);

        var foot = el('div', 'ws-approval-foot');
        foot.appendChild(el('span', 'ws-grow', t('apv_count', approval.counts.approved, (approval.people || []).length)));

        if (approval.can_decide) {
            var approve = h.button('btn btn-sm btn-primary rounded-pill px-3', t('apv_approve'), 'bi-check2');
            var reject = h.button('btn btn-sm btn-outline-warning', t('apv_reject'), 'bi-x-lg');

            approve.addEventListener('click', function () {
                approve.disabled = true;
                h.api('ws_approval_decide', { approval_id: approval.id, decision: 'approve' }).then(function (data) {
                    refreshed(view, data);
                }).catch(function (error) {
                    approve.disabled = false;
                    h.fail(error);
                });
            });

            reject.addEventListener('click', function () {
                var note = el('input', 'form-control form-control-sm');

                note.type = 'text';
                note.maxLength = 255;
                note.placeholder = t('apv_reject_note');
                note.setAttribute('aria-label', note.placeholder);

                h.ask(t('apv_reject_confirm'), t('apv_reject'), true, note).then(function (yes) {
                    if (!yes) {
                        return;
                    }

                    h.api('ws_approval_decide', { approval_id: approval.id, decision: 'reject', note: note.value }).then(function (data) {
                        refreshed(view, data);
                    }).catch(h.fail);
                });
            });

            foot.appendChild(approve);
            foot.appendChild(reject);
        }

        if (approval.result_id) {
            var result = h.button('btn btn-sm btn-ghost', t('apv_result'), 'bi-patch-check');
            result.addEventListener('click', function () { view.jumpTo(approval.result_id, true); });
            foot.appendChild(result);
        }

        if (approval.can_withdraw || approval.can_close) {
            var label = approval.can_withdraw ? t('apv_withdraw') : t('apv_close');
            var shut = h.button('btn btn-sm btn-ghost', label, 'bi-slash-circle');

            shut.addEventListener('click', function () {
                h.ask(approval.can_withdraw ? t('apv_withdraw_confirm') : t('apv_close_confirm'), label, true).then(function (yes) {
                    if (!yes) {
                        return;
                    }

                    h.api('ws_approval_close', { approval_id: approval.id }).then(function (data) {
                        refreshed(view, data);
                    }).catch(h.fail);
                });
            });
            foot.appendChild(shut);
        }

        box.appendChild(foot);

        return box;
    }

    // The form behind "Ask for approval" in the writing box's + menu: the
    // people are the channel's (a discussion's own people in a discussion).
    function form(view, h) {
        var t = h.t;
        var el = h.el;
        var channel = view.channel;
        var node = h.offcanvas('ws-approval-form', t('apv_form_title'));
        var body = h.clear(node.querySelector('.offcanvas-body'));
        var footer = h.clear(node.querySelector('.offcanvas-footer'));

        footer.classList.remove('d-none');

        var title = el('input', 'form-control form-control-sm');
        title.type = 'text';
        title.maxLength = 255;
        title.id = h.nextId('ws-apv-title-');
        body.appendChild(h.formRow(t('apv_title'), title, t('apv_title_help')));

        // The details take @ and # like the writing box does.
        var wrap = el('div', 'ws-note-compose');
        var rich = h.richField(wrap, { placeholder: t('apv_text') });
        var area = null;

        if (rich) {
            rich.caretPicker = true;
            rich.set('', {});
            wrap.appendChild(rich.input);
            wrap.appendChild(rich.tools(false));
        } else {
            area = el('textarea', 'form-control form-control-sm');
            area.rows = 3;
            wrap.appendChild(area);
        }

        var textRow = el('div', 'mb-3');
        textRow.appendChild(el('div', 'form-label', t('apv_text')));
        textRow.appendChild(wrap);
        textRow.appendChild(el('div', 'form-text', t('apv_text_help')));
        body.appendChild(textRow);

        var members = (channel.members || []).map(function (member) { return member.id; });
        var people = h.peoplePicker([], members);
        var peopleRow = el('div', 'mb-3');

        peopleRow.appendChild(el('div', 'form-label', t('apv_people')));
        peopleRow.appendChild(people);
        peopleRow.appendChild(el('div', 'form-text', t('apv_people_help')));
        body.appendChild(peopleRow);

        var ruleRow = el('div', 'mb-3');
        var group = h.nextId('ws-apv-rule-');
        var rule = 'any';

        ruleRow.appendChild(el('div', 'form-label', t('apv_rule')));

        [['any', t('apv_rule_any')], ['all', t('apv_rule_all')]].forEach(function (item) {
            var line = el('div', 'form-check');
            var radio = el('input', 'form-check-input');
            var caption = el('label', 'form-check-label', item[1]);

            radio.type = 'radio';
            radio.name = group;
            radio.id = h.nextId('ws-apv-rule-opt-');
            radio.checked = (item[0] === rule);
            radio.addEventListener('change', function () { rule = item[0]; });
            caption.htmlFor = radio.id;
            line.appendChild(radio);
            line.appendChild(caption);
            ruleRow.appendChild(line);
        });

        body.appendChild(ruleRow);

        var grid = el('div', 'row g-2');
        var dateCell = el('div', 'col-7');
        var timeCell = el('div', 'col-5');
        var date = el('input', 'form-control form-control-sm');
        var time = el('input', 'form-control form-control-sm');

        date.type = 'date';
        date.id = h.nextId('ws-apv-date-');
        date.min = h.cfg.today;
        time.type = 'time';
        time.id = h.nextId('ws-apv-time-');
        time.value = '18:00';
        dateCell.appendChild(h.formRow(t('apv_closes'), date, t('apv_closes_help')));
        timeCell.appendChild(h.formRow(t('apv_closes_time'), time));
        grid.appendChild(dateCell);
        grid.appendChild(timeCell);
        body.appendChild(grid);

        var outcome = el('div', 'ws-poll-note');
        outcome.appendChild(h.icon('bi-patch-check', 'me-1'));
        outcome.appendChild(document.createTextNode(t('apv_result_help')));
        body.appendChild(outcome);

        var cancel = h.button('btn btn-sm btn-ghost', t('cancel'));
        cancel.setAttribute('data-bs-dismiss', 'offcanvas');
        footer.appendChild(cancel);

        var send = h.button('btn btn-sm btn-primary rounded-pill px-3', t('apv_send'), 'bi-send');
        send.addEventListener('click', function () {
            send.disabled = true;

            h.api('ws_approval_create', {
                channel_id: channel.id,
                title: title.value,
                text: rich ? rich.value() : area.value,
                approvers: people.value(),
                rule: rule,
                closes_date: date.value,
                closes_time: time.value || '18:00'
            }).then(function () {
                send.disabled = false;
                h.hideOffcanvas(node);
                view.sync(true);
            }).catch(function (error) {
                send.disabled = false;
                h.fail(error);
            });
        });
        footer.appendChild(send);

        h.showOffcanvas(node);

        if (!h.touchScreen()) {
            setTimeout(function () { title.focus(); }, 250);
        }
    }

    // The overview's list of requests waiting for the reader's answer;
    // nothing when there are none.
    function home(data, view, h) {
        var items = (data && data.approvals) || [];

        if (!items.length) {
            return null;
        }

        var t = h.t;
        var el = h.el;
        var section = h.homeCard(t('apv_home'), 'bi-patch-question');

        items.forEach(function (item) {
            var row = h.button('ws-home-row ws-home-unread');
            var text = el('span', 'ws-home-row-text');
            var meta = el('small', 'ws-home-row-meta');

            row.appendChild(h.icon('bi-patch-question', 'text-body-secondary'));
            meta.appendChild(el('b', '', item.asker));
            meta.appendChild(document.createTextNode(' · '));
            meta.appendChild(h.icon(item.channel['private'] ? 'bi-lock' : 'bi-hash'));
            meta.appendChild(document.createTextNode(item.channel.name + ' · ' + item.time + (item.closes ? ' · ' + t('apv_home_until', item.closes) : '')));
            text.appendChild(meta);
            text.appendChild(el('span', 'ws-home-row-quote', item.title));
            row.appendChild(text);
            row.addEventListener('click', function () { view.open(item.channel.id, item.message_id); });
            section.body.appendChild(row);
        });

        return section.node;
    }

    // The box of a scheduled action that asks for approval in a channel.
    // Fills `detail` and returns what the action reads as.
    function scheduledEditor(detail, data, h) {
        var t = h.t;
        var el = h.el;

        data = data || { channel_id: null, title: '', text: '', approvers: [], rule: 'any' };

        var channel = h.saChannelSelect(data.channel_id, '');
        var title = h.saText(data.title, '', 255);
        var wrap = el('div', 'ws-note-compose ws-sa-body');
        var rich = h.richField(wrap, { placeholder: t('apv_sa_text'), previews: true });
        var area = null;
        var peopleBox = el('div');
        var people = null;
        var rule = h.select([['any', t('apv_rule_any')], ['all', t('apv_rule_all')]], data.rule || 'any');
        var chosen = data.approvers || [];

        detail.appendChild(h.saLabelled(t('sa_channel'), channel));
        detail.appendChild(h.saLabelled(t('apv_sa_title'), title));

        if (rich) {
            rich.caretPicker = true;
            rich.set(data.text || '', {});
            wrap.appendChild(rich.input);
            wrap.appendChild(rich.tools(false));
        } else {
            area = el('textarea', 'form-control form-control-sm');
            area.rows = 3;
            area.value = data.text || '';
            wrap.appendChild(area);
        }

        detail.appendChild(h.saLabelled(t('apv_sa_text'), wrap));
        detail.appendChild(h.saLabelled(t('apv_sa_people'), peopleBox, t('apv_sa_people_help')));
        detail.appendChild(h.saLabelled(t('apv_sa_rule'), rule));

        // The people offered are the members of the channel chosen.
        function drawPeople() {
            if (people) {
                chosen = people.value();
            }

            h.api('ws_channel_get', { channel_id: parseInt(channel.value, 10) || 0 }).then(function (result) {
                var members = ((result.channel || {}).members || []).map(function (member) { return member.id; });

                people = h.peoplePicker(chosen, members);
                h.clear(peopleBox);
                peopleBox.appendChild(people);
            }).catch(h.fail);
        }

        channel.addEventListener('change', drawPeople);
        drawPeople();

        return function () {
            return {
                type: 'approval',
                channel_id: parseInt(channel.value, 10) || 0,
                title: title.value,
                text: rich ? rich.value() : area.value,
                approvers: people ? people.value() : chosen,
                rule: rule.value
            };
        };
    }

    window.PGWsApprovals = {
        card: card,
        form: form,
        home: home,
        scheduledEditor: scheduledEditor
    };
})();
