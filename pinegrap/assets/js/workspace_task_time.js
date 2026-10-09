/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the time spent on tasks, on the screens.
 *
 * assets/js/workspace.js asks this file for its parts and lends it its own
 * helpers (api, ask, toast, t, el, ...), so nothing here talks to the server
 * or shows a text any other way:
 *   PGWsTaskTime.drawer(task, helpers, onChange)   the Time part of the drawer
 *   PGWsTaskTime.rowTags(container, task, helpers)  the time on a task row
 *   PGWsTaskTime.channelCard(channel, helpers)      the card on a channel's Summary tab
 *   PGWsTaskTime.myTime(container, helpers)         My time on the tasks screen
 *   PGWsTaskTime.boardTag(time, helpers)            planned and spent on a board row
 *
 * A running timer counts here, from the moment the server started it; the
 * server counts it again when it is stopped (includes/workspace/task_time.php),
 * so what is kept never depends on this page having stayed open.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // h:mm:ss of a timer that has run so many seconds.
    function clock(seconds) {
        seconds = Math.max(0, Math.floor(seconds));

        var hours = Math.floor(seconds / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var rest = seconds % 60;

        return hours + ':' + (minutes < 10 ? '0' : '') + minutes + ':' + (rest < 10 ? '0' : '') + rest;
    }

    // Ticks once a second while the node is on the page, and stops by itself
    // once it is gone (the drawer drawn again, or closed and emptied).
    function ticker(node, startedAt, serverNow) {
        var offset = serverNow - (Date.now() / 1000);

        function draw() {
            node.textContent = clock((Date.now() / 1000) + offset - startedAt);
        }

        draw();

        var timer = setInterval(function () {
            if (!document.body.contains(node)) {
                clearInterval(timer);
                return;
            }

            draw();
        }, 1000);
    }

    function shareBar(h, share) {
        var bar = h.el('div', 'progress ws-progress ws-task-time-bar' + (share.over ? ' ws-task-time-over' : ''));
        var fill = h.el('div', 'progress-bar');

        bar.setAttribute('role', 'progressbar');
        bar.setAttribute('aria-valuemin', '0');
        bar.setAttribute('aria-valuemax', '100');
        bar.setAttribute('aria-valuenow', String(share.percent));
        fill.style.width = share.percent + '%';
        bar.appendChild(fill);

        return bar;
    }

    // ── The drawer ─────────────────────────────────────────────────────

    function drawer(task, h, onChange) {
        var wrap = h.el('div', 'ws-task-time');

        function changed(time) {
            task.time = time;
            draw();

            if (onChange) {
                onChange();
            }
        }

        function draw() {
            var time = task.time;

            h.clear(wrap);

            var head = h.el('div', 'ws-task-time-head');
            head.appendChild(h.icon('bi-stopwatch', 'me-1'));
            head.appendChild(h.el('span', 'fw-semibold', h.t('tt_title')));
            head.appendChild(h.el('span', 'ws-grow'));

            var compare = h.el('span', 'text-body-secondary' + (time.share && time.share.over ? ' text-danger' : ''), time.compare);

            if (time.share && time.share.over) {
                compare.title = h.t('tt_over');
            }

            head.appendChild(compare);
            wrap.appendChild(head);

            if (time.share) {
                wrap.appendChild(shareBar(h, time.share));
            }

            if (time.can_log) {
                wrap.appendChild(timerRow(time));
                wrap.appendChild(addForm(time));
            }

            wrap.appendChild(entryList(time));
        }

        function timerRow(time) {
            var row = h.el('div', 'ws-task-time-timer');
            var timer = time.timer;

            if (timer && timer.here) {
                var counter = h.el('span', 'ws-task-time-clock');
                row.appendChild(h.icon('bi-record-circle', 'text-danger me-1'));
                row.appendChild(h.el('span', 'me-2', h.t('tt_running_here')));
                row.appendChild(counter);
                ticker(counter, timer.started_at, time.now);

                var stop = h.button('btn btn-sm btn-outline-danger rounded-pill px-3 ms-auto', h.t('tt_stop'), 'bi-stop-circle');
                stop.addEventListener('click', function () {
                    stop.disabled = true;
                    h.api('ws_task_time_stop', { task_id: task.id }).then(function (data) {
                        h.toast(h.t('tt_saved'), 'success');
                        changed(data.time);
                    }).catch(function (error) {
                        stop.disabled = false;
                        h.fail(error);
                    });
                });
                row.appendChild(stop);

                return row;
            }

            if (timer) {
                row.appendChild(h.el('span', 'small text-body-secondary', h.t('tt_running_other', timer.number)));
            }

            var start = h.button('btn btn-sm btn-outline-primary rounded-pill px-3 ms-auto', h.t('tt_start'), 'bi-play-circle');
            start.addEventListener('click', function () {
                start.disabled = true;
                h.api('ws_task_time_start', { task_id: task.id }).then(function (data) {
                    if (data.stopped) {
                        h.toast(h.t('tt_stopped_other', data.stopped), 'info');
                    }

                    changed(data.time);
                }).catch(function (error) {
                    start.disabled = false;
                    h.fail(error);
                });
            });
            row.appendChild(start);

            return row;
        }

        function addForm(time) {
            var form = h.el('div', 'ws-task-time-add row g-2 align-items-end');

            function cell(className, label, control) {
                var box = h.el('div', className);
                var caption = h.el('label', 'form-label small mb-1', label);

                control.id = h.nextId('ws-task-time-');
                caption.htmlFor = control.id;
                box.appendChild(caption);
                box.appendChild(control);
                form.appendChild(box);

                return control;
            }

            var amount = h.el('input', 'form-control form-control-sm');
            amount.type = 'text';
            amount.placeholder = h.t('tt_placeholder');
            amount.maxLength = 20;
            cell('col-4', h.t('tt_time'), amount);

            var day = h.el('input', 'form-control form-control-sm');
            day.type = 'date';
            day.value = time.today;
            day.max = time.today;
            cell('col-4', h.t('tt_day'), day);

            var person = null;

            if (time.for_others) {
                var people = ((h.boot() || {}).people || []).map(function (member) { return [member.id, member.name]; });
                person = cell('col-4', h.t('tt_person'), h.select(people, h.boot().me.id));
            }

            var note = h.el('input', 'form-control form-control-sm');
            note.type = 'text';
            note.maxLength = 255;
            cell(person ? 'col-12' : 'col-4', h.t('tt_note'), note);

            var foot = h.el('div', 'col-12 d-flex align-items-center gap-2');
            var check = h.el('div', 'form-check mb-0');
            var billable = h.el('input', 'form-check-input');
            var billableLabel = h.el('label', 'form-check-label small', h.t('tt_billable'));

            billable.type = 'checkbox';
            billable.checked = true;
            billable.id = h.nextId('ws-task-time-bill-');
            billableLabel.htmlFor = billable.id;
            check.appendChild(billable);
            check.appendChild(billableLabel);
            foot.appendChild(check);

            var add = h.button('btn btn-sm btn-primary rounded-pill px-3 ms-auto', h.t('tt_add'), 'bi-plus-lg');

            function submit() {
                if (amount.value.trim() === '') {
                    amount.focus();
                    return;
                }

                add.disabled = true;

                h.api('ws_task_time_add', {
                    task_id: task.id,
                    text: amount.value.trim(),
                    worked_on: day.value,
                    note: note.value.trim(),
                    billable: billable.checked ? 1 : 0,
                    user_id: person ? (parseInt(person.value, 10) || 0) : 0
                }).then(function (data) {
                    h.toast(h.t('tt_saved'), 'success');
                    changed(data.time);
                }).catch(function (error) {
                    add.disabled = false;
                    h.fail(error);

                    if (error.field === 'minutes') {
                        amount.focus();
                    }
                });
            }

            add.addEventListener('click', submit);
            amount.addEventListener('keydown', function (event) {
                if ((event.key === 'Enter') && !event.isComposing) {
                    event.preventDefault();
                    submit();
                }
            });
            foot.appendChild(add);
            form.appendChild(foot);

            return form;
        }

        function entryList(time) {
            var list = h.el('div', 'ws-task-time-list');

            if (!time.entries.length) {
                list.appendChild(h.el('div', 'small text-body-secondary', h.t('tt_none')));
                return list;
            }

            time.entries.forEach(function (entry) {
                var row = h.el('div', 'ws-task-time-row');

                row.appendChild(h.avatar(entry.person || {}));

                var main = h.el('div', 'ws-grow');
                var line = h.el('div', 'ws-task-time-row-head');
                line.appendChild(h.el('b', '', entry.person ? entry.person.name : ''));
                line.appendChild(h.el('span', 'text-body-secondary', entry.day_label));

                if (!entry.billable) {
                    line.appendChild(h.el('span', 'badge text-bg-light', h.t('tt_not_billable')));
                }

                if (entry.invoice_id) {
                    line.appendChild(h.el('span', 'badge text-bg-secondary', h.t('tt_on_invoice')));
                }

                main.appendChild(line);

                if (entry.note) {
                    main.appendChild(h.el('div', 'small', entry.note));
                }

                row.appendChild(main);
                row.appendChild(h.el('span', 'ws-task-time-amount' + (entry.running ? ' text-danger' : ''), entry.label));

                if (entry.can_delete) {
                    var remove = h.button('btn btn-sm btn-ghost py-0 px-1 ws-tool-danger', '', 'bi-trash', h.t('delete'));
                    remove.addEventListener('click', function () {
                        h.ask(h.t('tt_delete_confirm'), h.t('delete'), true).then(function (yes) {
                            if (!yes) {
                                return;
                            }

                            h.api('ws_task_time_delete', { entry_id: entry.id }).then(function (data) {
                                changed(data.time);
                            }).catch(h.fail);
                        });
                    });
                    row.appendChild(remove);
                }

                list.appendChild(row);
            });

            list.appendChild(h.el('div', 'ws-task-time-total', h.t('tt_total', time.total_label)));

            return list;
        }

        draw();

        return wrap;
    }

    // ── A task row ─────────────────────────────────────────────────────

    function rowTags(container, task, h) {
        if (!task.time_label) {
            return;
        }

        var tag = h.el('span', 'ws-task-time-tag ms-2');
        tag.appendChild(h.icon('bi-stopwatch', 'me-1'));
        tag.appendChild(document.createTextNode(task.time_label));
        tag.title = h.t('tt_title');
        container.appendChild(tag);
    }

    // ── A channel's Summary tab ────────────────────────────────────────

    function channelCard(channel, h) {
        var card = h.el('div', 'card ws-task-time-card mt-4');
        var header = h.el('div', 'card-header d-flex justify-content-between align-items-center');
        var body = h.el('div', 'card-body');

        header.appendChild(h.el('span', 'text-uppercase h5 text-primary fw-bold mb-0', h.t('tt_card')));
        card.appendChild(header);
        card.appendChild(body);

        function draw(data) {
            h.clear(body);

            var stats = h.el('div', 'ws-task-time-stats');

            [[h.t('tt_month'), data.month_label], [h.t('tt_all'), data.total_label], [h.t('tt_unbilled'), data.unbilled_label]].forEach(function (item) {
                var stat = h.el('div', 'ws-task-time-stat');
                stat.appendChild(h.el('div', 'small text-body-secondary', item[0]));
                stat.appendChild(h.el('div', 'fw-semibold', item[1]));
                stats.appendChild(stat);
            });

            body.appendChild(stats);

            if (data.people.length) {
                body.appendChild(h.el('div', 'small fw-semibold mt-3 mb-1', h.t('tt_by_person')));

                data.people.forEach(function (item) {
                    var line = h.el('div', 'ws-task-time-row');
                    line.appendChild(h.avatar(item.person || {}));
                    line.appendChild(h.el('span', 'ws-grow', item.person ? item.person.name : ''));
                    line.appendChild(h.el('span', 'ws-task-time-amount', item.label));
                    body.appendChild(line);
                });
            } else {
                body.appendChild(h.el('div', 'small text-body-secondary mt-3', h.t('tt_none')));
            }

            if (data.invoices.length) {
                body.appendChild(h.el('div', 'small fw-semibold mt-3 mb-1', h.t('tt_invoices')));

                data.invoices.forEach(function (invoice) {
                    var line = h.el('div', 'ws-task-time-row');
                    var label = h.el(invoice.url ? 'a' : 'span', 'ws-grow', invoice.label);

                    if (invoice.url) {
                        label.href = invoice.url;
                    }

                    line.appendChild(label);
                    line.appendChild(h.el('span', 'ws-task-time-amount', invoice.time_label));

                    if (invoice.can_unlink) {
                        var unlink = h.button('btn btn-sm btn-ghost py-0 px-1', '', 'bi-link-45deg', h.t('tt_unlink'));
                        unlink.addEventListener('click', function () {
                            h.ask(h.t('tt_unlink_confirm'), h.t('tt_unlink'), true).then(function (yes) {
                                if (!yes) {
                                    return;
                                }

                                h.api('ws_task_time_unlink', { channel_id: channel.id, invoice_id: invoice.id }).then(function (result) {
                                    h.toast(h.t('tt_unlinked'), 'success');
                                    draw(result.card);
                                }).catch(h.fail);
                            });
                        });
                        line.appendChild(unlink);
                    }

                    body.appendChild(line);
                });
            }

            // Only where the ERP, the customer and the right are all there;
            // the server asks again when the draft is made.
            if (data.can_invoice && data.unbilled > 0) {
                var open = h.button('btn btn-sm btn-outline-secondary mt-3', h.t('tt_invoice'), 'bi-receipt');
                open.addEventListener('click', function () {
                    open.remove();
                    body.appendChild(invoiceForm(data));
                });
                body.appendChild(open);
            }
        }

        function invoiceForm(data) {
            var form = h.el('div', 'ws-task-time-invoice row g-2 mt-2');

            function cell(label, control) {
                var box = h.el('div', 'col-6 col-lg-3');
                var caption = h.el('label', 'form-label small mb-1', label);

                control.id = h.nextId('ws-task-time-inv-');
                caption.htmlFor = control.id;
                box.appendChild(caption);
                box.appendChild(control);
                form.appendChild(box);

                return control;
            }

            function input(type, value) {
                var node = h.el('input', 'form-control form-control-sm');
                node.type = type;
                node.value = value || '';
                return node;
            }

            form.appendChild(h.el('div', 'col-12 small text-body-secondary', h.t('tt_invoice_help')));

            var from = cell(h.t('tt_from'), input('date', data.month_start));
            var to = cell(h.t('tt_to'), input('date', data.today));
            var rate = cell(h.t('tt_rate'), input('text', ''));
            var tax = cell(h.t('tt_tax'), input('text', '20'));

            rate.inputMode = 'decimal';
            tax.inputMode = 'decimal';

            var actions = h.el('div', 'col-12 d-flex gap-2');
            var save = h.button('btn btn-sm btn-primary rounded-pill px-3', h.t('tt_invoice'), 'bi-receipt');

            save.addEventListener('click', function () {
                save.disabled = true;

                h.api('ws_task_time_invoice', { channel_id: channel.id, from: from.value, to: to.value, rate: rate.value, tax_rate: tax.value }).then(function (result) {
                    h.toast(h.t('tt_invoiced', result.invoice_id, result.entries), 'success', { href: result.url, label: h.t('tt_open_draft') });
                    draw(result.card);
                }).catch(function (error) {
                    save.disabled = false;
                    h.fail(error);

                    var field = { rate: rate, tax_rate: tax, from: from, to: to }[error.field];

                    if (field) {
                        field.focus();
                    }
                });
            });
            actions.appendChild(save);
            form.appendChild(actions);

            return form;
        }

        h.api('ws_task_time_channel', { channel_id: channel.id }).then(function (data) {
            if (data.card) {
                draw(data.card);
            } else {
                card.remove();
            }
        }).catch(h.fail);

        return card;
    }

    // ── My time ────────────────────────────────────────────────────────

    function myTime(container, h) {
        h.api('ws_task_time_mine', { weeks: 8 }).then(function (data) {
            var mine = data.mine;
            var box = h.clear(container);

            if (mine.running) {
                var running = h.el('div', 'ws-list-row');
                var counter = h.el('span', 'ws-task-time-clock ms-2');

                running.appendChild(h.icon('bi-record-circle', 'text-danger'));
                running.appendChild(h.el('span', 'ws-grow', h.t('tt_running_other', mine.running.number)));
                running.appendChild(counter);
                ticker(counter, mine.running.started_at, mine.now);
                running.addEventListener('click', function () { h.openTask(mine.running.task_id); });
                box.appendChild(running);
            }

            if (!mine.weeks.length) {
                var empty = h.el('div', 'ws-empty');
                empty.appendChild(h.icon('bi-stopwatch'));
                empty.appendChild(document.createTextNode(h.t('tt_my_time_empty')));
                box.appendChild(empty);
                return;
            }

            mine.weeks.forEach(function (week) {
                var head = h.el('div', 'ws-task-time-week');
                head.appendChild(h.el('span', 'ws-grow fw-semibold', week.label));
                head.appendChild(h.el('span', 'fw-semibold', week.total_label));
                box.appendChild(head);

                week.tasks.forEach(function (item) {
                    var row = h.el('div', 'ws-list-row');
                    var title = h.el('div', 'ws-grow ws-row-title');

                    title.appendChild(h.el('span', 'text-body-secondary me-1', item.number));
                    title.appendChild(document.createTextNode(item.title));
                    row.appendChild(title);
                    row.appendChild(h.el('span', 'ws-task-time-amount', item.label));

                    if (item.visible) {
                        row.tabIndex = 0;
                        row.setAttribute('role', 'button');
                        row.addEventListener('click', function () { h.openTask(item.id); });
                    }

                    box.appendChild(row);
                });
            });
        }).catch(h.fail);
    }

    // ── The board ──────────────────────────────────────────────────────

    function boardTag(time, h) {
        var tag = h.el('small', 'ws-task-time-board' + (time.spent > time.planned ? ' text-danger' : ''), h.t('tt_board', time.planned_label, time.spent_label));
        return tag;
    }

    window.PGWsTaskTime = {
        drawer: drawer,
        rowTags: rowTags,
        channelCard: channelCard,
        myTime: myTime,
        boardTag: boardTag
    };
})();
