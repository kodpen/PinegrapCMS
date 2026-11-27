/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - items moved on My calendar (workspace_my_calendar.php). A plan
 * of the reader's own, or a task they may change, is dragged to another day
 * (Month, Week) or hour (Week) and saved through its own save: a plan by
 * ws_personal_plan_move (ws_personal_plan_save() underneath), a task by
 * ws_task_move as the Planning Board moves it. The saved notice offers Undo.
 * A repeating plan asks "This one" or "This and the following" as its
 * drawer does. What the reader may not change (a booking, an own-iCal
 * event, a plan item, a plan of somebody else's) does not lift.
 *
 * The item's right-click menu (long press on a phone, the menu key) has
 * "Move to…" with a date field, for the keyboard and the phone, where no
 * drag is taken.
 *
 * assets/js/workspace_my_calendar.js and workspace_my_calendar_week.js
 * call it. Served minified inside assets/js/workspace.min.js
 * (pg_js_bundles(), includes/fn/output.php); after editing run
 * `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    function utc(date) {
        var part = String(date || '').split('-');

        return Date.UTC(+part[0], +part[1] - 1, +part[2]);
    }

    // Whole days from one Y-m-d to another.
    function between(from, to) {
        return Math.round((utc(to) - utc(from)) / 86400000);
    }

    function addDays(date, count) {
        return new Date(utc(date) + count * 86400000).toISOString().slice(0, 10);
    }

    function hourOf(time) {
        return /^\d{2}:\d{2}/.test(time || '') ? parseInt(time.slice(0, 2), 10) : -1;
    }

    // h: the Workspace helpers; s(key): the screen's texts; reload(): the
    // calendar read again; dayMenu(date): the day's own menu items.
    function kit(h, s, reload, dayMenu) {
        var el = h.el;
        // What is being dragged: the item, the day it was taken from, and
        // how far below the chip's top it was held.
        var dragged = null;

        // The day a chip stands on: its cell, strip cell or hour column.
        function dayOf(node) {
            var cell = node.closest('[data-ws-date]');

            return cell ? cell.getAttribute('data-ws-date') : '';
        }

        function movable(item) {
            return item.plan ? !!(item.plan.plan_id && item.plan.mine) : !!(item.task.can_move && !item.task.upcoming);
        }

        // A repeating plan: this time alone, or it and the ones after it.
        // Resolves '' when called off.
        function askTimes() {
            var box = el('div', 'ws-ask-choice');
            var inputs = {};

            [['one', s('repeat_this_one')], ['following', s('repeat_following')]].forEach(function (choice, index) {
                var line = el('div', 'form-field');
                var radio = el('input', 'radio');

                radio.type = 'radio';
                radio.name = 'ws-move-times';
                radio.value = choice[0];
                radio.id = h.nextId('ws-move-times-');
                radio.checked = (index === 0);

                var label = el('label', '', choice[1]);

                label.htmlFor = radio.id;
                line.appendChild(radio);
                line.appendChild(label);
                box.appendChild(line);
                inputs[choice[0]] = radio;
            });

            return h.ask(s('repeat_change_q'), s('move'), false, box).then(function (yes) {
                return yes ? (inputs.following.checked ? 'following' : 'one') : '';
            });
        }

        function undoNotice(run) {
            return {
                label: s('undo'),
                delay: 8000,
                onClick: run
            };
        }

        // A plan moved by whole days and, on the week's hours, to the hour
        // its top was dropped in (minute -1 keeps its time). from: the day
        // it was taken from.
        function movePlan(plan, from, days, minute) {
            // Taken from a later day of a plan over several days: the hour
            // under it is no hour of its start, so it moves by days alone.
            var hour = (plan.time && plan.first === from && minute >= 0) ? Math.floor(minute / 60) : -1;

            if (!days && (hour < 0 || hour === hourOf(plan.time))) {
                return;
            }

            (plan.repeat ? askTimes() : Promise.resolve('all')).then(function (mode) {
                if (!mode) {
                    return;
                }

                h.api('ws_personal_plan_move', {
                    plan_id: plan.plan_id,
                    day: plan.plan_day || '',
                    days: days,
                    hour: hour,
                    mode: mode === 'all' ? '' : mode
                }).then(function (result) {
                    reload();
                    h.toast(s('plan_saved'), 'success', undoNotice(function () {
                        h.api('ws_personal_plan_move', result.undo).then(function () {
                            reload();
                            h.toast(s('move_undone'));
                        }).catch(h.fail);
                    }));
                }).catch(h.fail);
            });
        }

        // A task moved by whole days; one with a due time, on the week's
        // hours, to an hour as well: its block is the half hour before its
        // due time (WS_MY_CALENDAR_HOUR_SHORTEST), so the hour is read
        // half an hour below the block's top. The Planning Board's clash
        // question comes first when the new day clashes with somebody's.
        function moveTask(task, days, minute) {
            var hour = (task.time && minute >= 0) ? Math.min(23, Math.floor((minute + 30) / 60)) : -1;

            if (!days && (hour < 0 || hour === hourOf(task.time))) {
                return;
            }

            h.moveTask({ task_id: task.id, date: addDays(task.due_date, days), hour: hour }, reload, undoNotice(function () {
                h.moveTask({ task_id: task.id, date: task.due_date, hour: hourOf(task.time) }, reload);
            }));
        }

        function move(item, from, days, minute) {
            if (item.plan) {
                movePlan(item.plan, from, days, minute);
            } else {
                moveTask(item.task, days, minute);
            }
        }

        // "Move to…": the day asked in a dialog, from the plan's first day
        // or the task's due day.
        function moveTo(item, from) {
            var first = item.plan ? (item.plan.first || from) : item.task.due_date;
            var date = h.form.input('date', first);

            date.required = true;

            h.ask(s('move_to_q'), s('move'), false, h.form.field(s('move_day'), date)).then(function (yes) {
                if (yes && /^\d{4}-\d{2}-\d{2}$/.test(date.value)) {
                    move(item, from, between(first, date.value), -1);
                }
            });
        }

        // An item's own menu, in place of its day's: Open, "Move to…", then
        // the day's entries. For a chip and for a line of a day's list.
        function menu(node, item, open) {
            if (!movable(item)) {
                return;
            }

            h.onContext(node, function () {
                var from = dayOf(node);
                var items = [
                    { header: item.plan ? item.plan.title : item.task.title },
                    { icon: 'bi-box-arrow-up-right', label: s('open'), action: open },
                    { icon: 'bi-arrows-move', label: s('move_to'), action: function () { moveTo(item, from); } }
                ];

                return (from && dayMenu) ? items.concat(['-'], dayMenu(from).slice(1)) : items;
            });
        }

        // A chip of the calendar: lifted, and given its own menu, when the
        // reader may move it. item: { plan: event } or { task: task };
        // open(): what a click on it does.
        function lift(chip, item, open) {
            if (!movable(item)) {
                return;
            }

            chip.draggable = true;
            chip.classList.add('ws-mycal-movable');

            chip.addEventListener('dragstart', function (event) {
                event.stopPropagation();
                dragged = { item: item, from: dayOf(chip), offset: event.clientY - chip.getBoundingClientRect().top };
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', chip.title || '');
                chip.classList.add('ws-mycal-dragging');
            });

            chip.addEventListener('dragend', function () {
                dragged = null;
                chip.classList.remove('ws-mycal-dragging');
                document.querySelectorAll('.ws-mycal-drop').forEach(function (node) {
                    node.classList.remove('ws-mycal-drop');
                });
            });

            menu(chip, item, open);
        }

        // A place a chip may be dropped on: a day of the month, of the
        // week's strip, or a day's hours (minuteAt(event, offset) gives the
        // minute of the day under the dropped chip's top).
        function target(node, date, minuteAt) {
            node.setAttribute('data-ws-date', date);

            node.addEventListener('dragover', function (event) {
                if (!dragged) {
                    return;
                }

                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                node.classList.add('ws-mycal-drop');
            });

            node.addEventListener('dragleave', function (event) {
                if (!node.contains(event.relatedTarget)) {
                    node.classList.remove('ws-mycal-drop');
                }
            });

            node.addEventListener('drop', function (event) {
                var held = dragged;

                node.classList.remove('ws-mycal-drop');

                if (!held) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                dragged = null;
                move(held.item, held.from, between(held.from, date), minuteAt ? minuteAt(event, held.offset) : -1);
            });
        }

        return { lift: lift, menu: menu, target: target };
    }

    // The minute of the day under a dropped chip's top, on a column of 24
    // hours.
    function minuteAt(column) {
        return function (event, offset) {
            var rect = column.getBoundingClientRect();
            var minute = Math.floor((event.clientY - (offset || 0) - rect.top) / rect.height * 1440);

            return Math.max(0, Math.min(1439, minute));
        };
    }

    window.PGWsMyCalendarMove = { kit: kit, minuteAt: minuteAt };
})();
