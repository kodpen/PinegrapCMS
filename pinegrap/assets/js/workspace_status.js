/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - status updates and check-ins on the channel screen: the state
 * beside the channel's name, the form that writes an update, the colour of
 * an update in the conversation, the card under a check-in where everybody
 * answers, the question asked for one, and the box of a scheduled action
 * that asks it on a repeat.
 *
 * assets/js/workspace.js draws the conversation and calls in here with its
 * own helpers (api, t, el, button, avatar, offcanvas, the formatted text
 * field), so the parts look like the rest of the screen. The figures, the
 * draft and who may answer come from the server
 * (includes/workspace/status.php), which checks each again when it is sent.
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

    var HEALTHS = ['on_track', 'at_risk', 'off_track'];

    var ICONS = {
        on_track: 'bi-circle-fill',
        at_risk: 'bi-exclamation-circle-fill',
        off_track: 'bi-x-circle-fill'
    };

    // The state beside the channel's name; it opens the update it comes
    // from. Nothing until the channel has one.
    function pill(channel, view, h) {
        var state = channel.status;

        if (!state || HEALTHS.indexOf(state.health) < 0) {
            return null;
        }

        var node = h.button('ws-status-pill ws-status-' + state.health, '', ICONS[state.health]);

        node.appendChild(document.createTextNode(state.label + (state.time ? ' · ' + state.time : '')));
        node.title = h.t('st_pill_title');
        node.addEventListener('click', function () { view.jumpTo(state.message_id, true); });

        return node;
    }

    // Under a message: a status update colours the message by its state; a
    // check-in gets its card.
    function card(message, view, h, node) {
        var info = message.status_card;

        if (!info) {
            return null;
        }

        if (info.kind === 'status') {
            if (node && HEALTHS.indexOf(info.health) >= 0) {
                node.classList.add('ws-msg-status', 'ws-msg-status-' + info.health);
            }

            return null;
        }

        return (info.kind === 'checkin') ? checkinCard(message, info, view, h) : null;
    }

    function checkinCard(message, info, view, h) {
        var t = h.t;
        var el = h.el;
        var box = el('div', 'ws-checkin');
        var head = el('div', 'ws-checkin-head');

        head.appendChild(h.icon('bi-person-raised-hand'));
        head.appendChild(el('span', 'fw-semibold', t('ci_card')));
        head.appendChild(el('span', 'ws-checkin-count', t('ci_count', info.count, info.total)));
        box.appendChild(head);

        var list = el('ul', 'ws-checkin-answers');

        (info.answers || []).forEach(function (answer) {
            var row = el('li', 'ws-checkin-answer' + (answer.mine ? ' ws-checkin-mine' : ''));
            var face = h.avatar(answer);
            var text = el('div', 'ws-checkin-text');
            var who = el('div', 'ws-checkin-who');

            face.className = 'ws-checkin-face';
            row.appendChild(face);
            who.appendChild(el('b', '', answer.name));
            who.appendChild(el('span', 'ws-checkin-time', answer.time));
            text.appendChild(who);
            text.appendChild(el('div', 'ws-checkin-body', answer.text));
            row.appendChild(text);
            list.appendChild(row);
        });

        if (info.answers && info.answers.length) {
            box.appendChild(list);
        }

        if (info.waiting && info.waiting.length) {
            box.appendChild(el('div', 'ws-checkin-waiting', t('ci_waiting', info.waiting.join(', '))));
        }

        if (info.can_answer && !message.past) {
            box.appendChild(answerBox(info, view, h));
        }

        return box;
    }

    // The reader's answer: folded behind a button once given, so the card
    // reads as the list it is.
    function answerBox(info, view, h) {
        var t = h.t;
        var el = h.el;
        var wrap = el('div', 'ws-checkin-form');
        var area = el('textarea', 'form-control form-control-sm');
        var send = h.button('btn btn-sm btn-primary rounded-pill px-3', t('ci_send'), 'bi-send');
        var row = el('div', 'ws-checkin-form-foot');

        area.rows = 2;
        area.maxLength = (h.cfg.status && h.cfg.status.answer_max) || 2000;
        area.placeholder = t('ci_answer');
        area.setAttribute('aria-label', area.placeholder);
        area.value = info.mine || '';

        send.addEventListener('click', function () {
            send.disabled = true;
            h.api('ws_checkin_answer', { checkin_id: info.id, text: area.value }).then(function (data) {
                if (data && data.message) {
                    view.replaceMessage(data.message);
                }
            }).catch(function (error) {
                send.disabled = false;
                h.fail(error);
            });
        });

        row.appendChild(send);
        wrap.appendChild(area);
        wrap.appendChild(row);

        if (!info.mine) {
            return wrap;
        }

        var change = h.button('btn btn-sm btn-ghost', t('ci_change'), 'bi-pencil');
        var holder = el('div');

        wrap.hidden = true;
        change.addEventListener('click', function () {
            change.hidden = true;
            wrap.hidden = false;
            area.focus();
        });
        holder.appendChild(change);
        holder.appendChild(wrap);

        return holder;
    }

    // The form behind "Status update": the channel's figures, the state
    // they point at chosen, and a draft to change.
    function form(view, h) {
        var t = h.t;
        var el = h.el;
        var channel = view.channel;

        h.api('ws_status_facts', { channel_id: channel.id }).then(function (data) {
            var node = h.offcanvas('ws-status-form', t('st_form_title'));
            var body = h.clear(node.querySelector('.offcanvas-body'));
            var footer = h.clear(node.querySelector('.offcanvas-footer'));
            var facts = data.facts;
            var counts = facts.counts;
            var health = facts.suggest;

            footer.classList.remove('d-none');

            var figures = el('div', 'ws-status-facts');
            var chips = el('div', 'ws-status-chips');

            figures.appendChild(el('div', 'form-label', t('st_facts')));

            [
                ['bi-list-task', t('st_open', counts.open), ''],
                ['bi-alarm', t('st_overdue', counts.overdue), counts.overdue ? 'ws-status-chip-warn' : ''],
                ['bi-check2-circle', t('st_done', counts.done), ''],
                ['bi-hourglass-split', t('st_stale', counts.stale, facts.stale_days), counts.stale ? 'ws-status-chip-warn' : ''],
                ['bi-patch-check', t('st_decisions', counts.decisions), ''],
                ['bi-patch-question', t('st_approvals', counts.approvals), '']
            ].forEach(function (item) {
                var chip = el('span', 'ws-status-chip ' + item[2]);

                chip.appendChild(h.icon(item[0], 'me-1'));
                chip.appendChild(document.createTextNode(item[1]));
                chips.appendChild(chip);
            });

            figures.appendChild(chips);

            if (facts.last) {
                figures.appendChild(el('div', 'form-text mt-1', t('st_last', facts.last)));
            }

            // The tasks going stale, each a click from its drawer.
            if (facts.stale.length) {
                var staleBox = el('div', 'ws-status-stale');

                staleBox.appendChild(el('div', 'small fw-semibold mt-2', t('st_stale_list', facts.stale_days)));

                facts.stale.forEach(function (task) {
                    var line = h.button('ws-status-stale-task', '', 'bi-check2-square');

                    line.appendChild(el('span', 'ws-grow', task.title));
                    line.appendChild(el('span', 'text-body-secondary small', t('st_days', task.days)));

                    if (h.openTask) {
                        line.addEventListener('click', function () { h.openTask(task.id); });
                    }

                    staleBox.appendChild(line);
                });

                figures.appendChild(staleBox);
            }

            body.appendChild(figures);

            var healthRow = el('div', 'mb-3');
            var group = h.nextId('ws-status-health-');

            healthRow.appendChild(el('div', 'form-label', t('st_health')));

            var choices = el('div', 'ws-status-choices');

            HEALTHS.forEach(function (key) {
                var radio = el('input', 'btn-check');
                var caption = el('label', 'btn btn-sm ws-status-choice ws-status-' + key);

                radio.type = 'radio';
                radio.name = group;
                radio.id = h.nextId('ws-status-opt-');
                radio.checked = (key === health);
                radio.addEventListener('change', function () { health = key; });
                caption.htmlFor = radio.id;
                caption.appendChild(h.icon(ICONS[key], 'me-1'));
                caption.appendChild(document.createTextNode(t('st_' + key)));
                choices.appendChild(radio);
                choices.appendChild(caption);
            });

            healthRow.appendChild(choices);
            body.appendChild(healthRow);

            var wrap = el('div', 'ws-note-compose');
            var rich = h.richField(wrap, { placeholder: t('st_text') });
            var area = null;

            if (rich) {
                rich.caretPicker = true;
                rich.set(data.draft || '', {});
                wrap.appendChild(rich.input);
                wrap.appendChild(rich.tools(false));
            } else {
                area = el('textarea', 'form-control form-control-sm');
                area.rows = 8;
                area.value = data.draft || '';
                wrap.appendChild(area);
            }

            var textRow = el('div', 'mb-3');
            textRow.appendChild(el('div', 'form-label', t('st_text')));
            textRow.appendChild(wrap);
            textRow.appendChild(el('div', 'form-text', t('st_text_help')));
            body.appendChild(textRow);

            // Pinegrap AI, only where it may read the channel.
            if (data.ai) {
                var write = h.button('btn btn-sm btn-outline-secondary', t('st_ai'), 'bi-stars');

                write.addEventListener('click', function () {
                    write.disabled = true;
                    write.lastChild.textContent = t('st_ai_busy');
                    h.api('ws_status_ai', { channel_id: channel.id }).then(function (result) {
                        if (rich) {
                            rich.set(result.text || '', {});
                        } else {
                            area.value = result.text || '';
                        }
                    }).catch(h.fail).then(function () {
                        write.disabled = false;
                        write.lastChild.textContent = t('st_ai');
                    });
                });
                textRow.appendChild(write);
            }

            var cancel = h.button('btn btn-sm btn-ghost', t('cancel'));
            cancel.setAttribute('data-bs-dismiss', 'offcanvas');
            footer.appendChild(cancel);

            var send = h.button('btn btn-sm btn-primary rounded-pill px-3', t('st_send'), 'bi-send');
            send.addEventListener('click', function () {
                send.disabled = true;

                h.api('ws_status_post', {
                    channel_id: channel.id,
                    health: health,
                    text: rich ? rich.value() : area.value
                }).then(function () {
                    send.disabled = false;
                    h.hideOffcanvas(node);
                    view.open(channel.id, 0);
                }).catch(function (error) {
                    send.disabled = false;
                    h.fail(error);
                });
            });
            footer.appendChild(send);

            h.showOffcanvas(node);
        }).catch(h.fail);
    }

    // "Ask for a check-in": the question, with the usual one written in.
    function checkinForm(view, h) {
        var t = h.t;
        var el = h.el;
        var config = h.cfg.status || {};
        var question = el('input', 'form-control form-control-sm');
        var box = el('div');

        question.type = 'text';
        question.maxLength = config.question_max || 255;
        question.value = config.question || '';
        question.setAttribute('aria-label', t('ci_question'));
        box.appendChild(question);
        box.appendChild(el('div', 'form-text', t('ci_question_help')));

        h.ask(t('ci_new'), t('ci_ask'), false, box).then(function (yes) {
            if (!yes) {
                return;
            }

            h.api('ws_checkin_start', { channel_id: view.channel.id, question: question.value }).then(function () {
                view.sync(true);
            }).catch(h.fail);
        });
    }

    // The box of a scheduled action that asks for a check-in in a channel.
    // Fills `detail` and returns what the action reads as.
    function scheduledEditor(detail, data, h) {
        var config = h.cfg.status || {};

        data = data || { channel_id: null, question: config.question || '' };

        var channel = h.saChannelSelect(data.channel_id, '');
        var question = h.saText(data.question, config.question || '', config.question_max || 255);

        detail.appendChild(h.saLabelled(h.t('sa_channel'), channel));
        detail.appendChild(h.saLabelled(h.t('ci_sa_question'), question, h.t('ci_sa_help')));

        return function () {
            return {
                type: 'checkin',
                channel_id: parseInt(channel.value, 10) || 0,
                question: question.value
            };
        };
    }

    window.PGWsStatus = {
        pill: pill,
        card: card,
        form: form,
        checkinForm: checkinForm,
        scheduledEditor: scheduledEditor
    };
})();
