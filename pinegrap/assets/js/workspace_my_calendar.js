/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - My calendar (workspace_my_calendar.php): the reader's own
 * tasks, the plan items they are in, their bookings and their own iCal
 * layer, by month, by week or as a list of days. The data comes from the
 * ws_my_calendar action (includes/workspace/my_calendar.php), always the
 * reader's own; the Work Calendar's cell classes (.ws-cal-*) draw the grid.
 *
 * assets/js/workspace.js calls screen() with its helpers. The screen's own
 * texts come from the #ws-config block's my_calendar.strings.
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

    var VIEW_KEY = 'pg.ws.myCalendar.view';

    function readView() {
        try {
            return window.localStorage.getItem(VIEW_KEY) || '';
        } catch (error) {
            return '';
        }
    }

    function keepView(view) {
        try {
            window.localStorage.setItem(VIEW_KEY, view);
        } catch (error) {
            // A private window keeps no choice; the month is drawn next time.
        }
    }

    function screen(root, h) {
        var S = (h.cfg.my_calendar && h.cfg.my_calendar.strings) || {};
        var el = h.el;
        var query = new URLSearchParams(window.location.search);
        var state = {
            view: query.get('view') || readView() || 'month',
            date: /^\d{4}-\d{2}-\d{2}$/.test(query.get('date') || '') ? query.get('date') : ''
        };
        var data = null;

        function s(key) {
            return Object.prototype.hasOwnProperty.call(S, key) ? S[key] : key;
        }

        h.clear(root);
        root.classList.add('ws-mycal-screen');

        // ── toolbar: previous, Today, next, the range, the view, the Work Calendar ──

        var bar = el('nav', 'pg-toolbar navigation mb-5 ws-cal-toolbar ws-mycal-toolbar');
        var steps = el('div', 'ws-mycal-steps');
        var prev = el('button', 'pg-icon-button pg-icon-button-sm');
        var today = el('button', 'pg-button pg-button-quiet pg-button-sm', h.t('today'));
        var next = el('button', 'pg-icon-button pg-icon-button-sm');

        [prev, today, next].forEach(function (node) { node.type = 'button'; });
        prev.appendChild(h.icon('bi-chevron-left'));
        prev.setAttribute('aria-label', s('previous'));
        prev.title = s('previous');
        next.appendChild(h.icon('bi-chevron-right'));
        next.setAttribute('aria-label', s('next'));
        next.title = s('next');
        prev.addEventListener('click', function () { if (data) { state.date = data.prev; load(); } });
        next.addEventListener('click', function () { if (data) { state.date = data.next; load(); } });
        today.addEventListener('click', function () { state.date = ''; load(); });
        steps.appendChild(prev);
        steps.appendChild(today);
        steps.appendChild(next);
        bar.appendChild(steps);

        var title = el('h2', 'ws-cal-title pg-title-section');
        bar.appendChild(title);
        bar.appendChild(el('div', 'pg-toolbar-grow'));

        var seg = el('div', 'pg-seg');
        var segItems = {};

        seg.setAttribute('role', 'group');
        seg.setAttribute('aria-label', s('view'));

        [['month', 'bi-calendar3'], ['week', 'bi-calendar-week'], ['list', 'bi-list-ul']].forEach(function (option) {
            var item = el('button', 'pg-seg-item');
            item.type = 'button';
            item.appendChild(h.icon(option[1], 'me-1'));
            item.appendChild(document.createTextNode(s(option[0])));
            item.addEventListener('click', function () {
                state.view = option[0];
                keepView(state.view);
                load();
            });
            segItems[option[0]] = item;
            seg.appendChild(item);
        });

        bar.appendChild(seg);

        var work = el('a', 'pg-button pg-button-quiet pg-button-sm');
        work.href = h.cfg.urls.calendar;
        work.appendChild(h.icon('bi-calendar3'));
        work.appendChild(document.createTextNode(s('work_calendar')));
        bar.appendChild(work);

        var card = el('div', 'ws-cal card ws-mycal');

        root.appendChild(bar);
        root.appendChild(card);

        // ── loading ──

        function load() {
            h.api('ws_my_calendar', { view: state.view, date: state.date }).then(function (result) {
                data = result;
                state.view = result.view;
                state.date = result.anchor;

                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', h.cfg.urls.my_calendar + '?view=' + result.view + '&date=' + result.anchor);
                }

                draw();
            }).catch(h.fail);
        }

        // ── one item of a day ──

        function openTask(task) {
            h.taskDrawer(task.id, null, load);
        }

        function taskChip(task, seg) {
            var chip = el('button', 'ws-cal-task' + (task.upcoming ? ' ws-cal-upcoming' : '') + (task.overdue ? ' ws-cal-late' : '') + (task.open ? '' : ' ws-cal-closed')
                + ((task.priority === 'urgent' || task.priority === 'high') ? ' ws-cal-hot' : '')
                + (seg ? ' ws-cal-span' + (seg.left ? ' ws-cal-span-l' : '') + (seg.right ? ' ws-cal-span-r' : '') + (seg.label ? '' : ' ws-cal-span-rest') : ''));
            chip.type = 'button';

            if (seg && !seg.label) {
                chip.appendChild(el('span', 'ws-cal-task-title visually-hidden', task.title));
            } else {
                chip.appendChild(task.upcoming ? h.icon('bi-arrow-repeat') : el('span', 'ws-status-dot ws-s-' + task.status));
                chip.appendChild(el('span', 'ws-cal-task-title', (task.time ? task.time + ' ' : '') + task.title));
            }

            chip.title = (task.upcoming ? task.repeat : task.number + ' · ' + h.statusName(task.status)) + ' · ' + task.title
                + (task.span_start ? '\n' + h.dmy(task.span_start) + ' – ' + h.dmy(task.span_end) : '');
            chip.addEventListener('click', function (event) {
                event.stopPropagation();
                openTask(task);
            });

            return chip;
        }

        function openEvent(event) {
            if (event.url) {
                window.location.href = event.url;
            } else if (!event.readonly) {
                h.planItem(event.id, null, load);
            }
        }

        function eventChip(event) {
            var item = el(event.readonly && !event.url ? 'div' : 'button', 'ws-cal-event ws-kind-' + event.kind);

            if (item.tagName === 'BUTTON') {
                item.type = 'button';
                item.addEventListener('click', function (clicked) {
                    clicked.stopPropagation();
                    openEvent(event);
                });
            }

            if (event.readonly && !event.url) {
                item.appendChild(h.icon('bi-calendar2-heart', 'me-1'));
            }

            if (event.meeting_url) {
                item.appendChild(h.icon('bi-camera-video', 'me-1'));
            }

            item.appendChild(document.createTextNode((event.time ? event.time + ' ' : '') + event.title));
            item.title = event.title + (event.layer ? ' · ' + event.layer : '');

            return item;
        }

        function dayMenu(day) {
            return [
                { header: day.label },
                { icon: 'bi-check2-square', label: h.t('new_task_here'), action: function () { h.taskDrawer(0, { due_date: day.date, assignees: [h.cfg.me] }, load); } },
                { icon: 'bi-calendar-plus', label: h.t('add_plan_item'), action: function () { h.planItem(0, { start: day.date, people: [h.cfg.me] }, load); } }
            ];
        }

        // ── month and week: the grid ──

        function lanesFor(week) {
            var weekFrom = week[0].date;
            var weekTo = week[week.length - 1].date;
            var lanes = [];

            (data.spans || []).filter(function (task) {
                return task.span_start <= weekTo && task.span_end >= weekFrom;
            }).sort(function (a, b) {
                return a.span_start < b.span_start ? -1 : (a.span_start > b.span_start ? 1 : (a.span_end > b.span_end ? -1 : (a.span_end < b.span_end ? 1 : a.id - b.id)));
            }).forEach(function (task) {
                var from = task.span_start < weekFrom ? weekFrom : task.span_start;
                var lane = 0;

                while (lane < lanes.length && lanes[lane].end >= from) {
                    lane++;
                }

                if (lane === lanes.length) {
                    lanes.push({ end: '', tasks: [] });
                }

                lanes[lane].end = task.span_end;
                lanes[lane].tasks.push(task);
            });

            return lanes;
        }

        function drawGrid(box) {
            var head = el('div', 'ws-cal-head');

            data.weekdays.forEach(function (name, index) {
                head.appendChild(el('div', index >= 5 ? 'ws-cal-weekend' : '', name));
            });

            box.appendChild(head);

            var grid = el('div', 'ws-cal-grid');
            var limit = (data.view === 'week') ? 1000 : 4;
            var weeks = [];

            for (var i = 0; i < data.days.length; i += 7) {
                weeks.push(data.days.slice(i, i + 7));
            }

            weeks.forEach(function (week) {
                var lanes = lanesFor(week);

                week.forEach(function (day, column) {
                    var cell = el('div', 'ws-cal-day' + (day.in_range ? '' : ' ws-cal-out') + (day.today ? ' ws-cal-today' : '') + (day.weekend ? ' ws-cal-weekend' : ''));
                    var top = el('div', 'ws-cal-day-top');
                    var shown = 0;
                    var hidden = 0;
                    var used = 0;

                    cell.setAttribute('aria-label', day.label);
                    top.appendChild(el('span', 'ws-cal-num', day.day));
                    cell.appendChild(top);

                    lanes.forEach(function (lane, index) {
                        if (lane.tasks.some(function (task) { return task.span_start <= day.date && task.span_end >= day.date; })) {
                            used = index + 1;
                        }
                    });

                    lanes.slice(0, used).forEach(function (lane) {
                        var task = null;

                        lane.tasks.forEach(function (item) {
                            if (item.span_start <= day.date && item.span_end >= day.date) {
                                task = item;
                            }
                        });

                        if (shown >= limit) {
                            hidden += task ? 1 : 0;
                            return;
                        }

                        cell.appendChild(task ? taskChip(task, {
                            left: column > 0 && task.span_start < day.date,
                            right: column < week.length - 1 && task.span_end > day.date,
                            label: task.span_start === day.date || column === 0
                        }) : el('div', 'ws-cal-lane-gap'));
                        shown++;
                    });

                    day.events.forEach(function (event) {
                        if (shown >= limit) {
                            hidden++;
                            return;
                        }

                        cell.appendChild(eventChip(event));
                        shown++;
                    });

                    day.tasks.forEach(function (task) {
                        if (shown >= limit) {
                            hidden++;
                            return;
                        }

                        cell.appendChild(taskChip(task));
                        shown++;
                    });

                    if (hidden > 0) {
                        var more = el('button', 'ws-cal-more', h.t('more_count', hidden));
                        more.type = 'button';
                        cell.appendChild(more);
                    }

                    // A day opens as the list from that day: on a phone the
                    // cells show only bars.
                    cell.addEventListener('click', function () {
                        state.view = 'list';
                        state.date = day.date;
                        load();
                    });
                    h.pressKeys(cell, function () {
                        state.view = 'list';
                        state.date = day.date;
                        load();
                    });
                    h.onContext(cell, function () { return dayMenu(day); });
                    grid.appendChild(cell);
                });
            });

            box.appendChild(grid);
        }

        // ── list: the days that hold something, one section each ──

        function listRow(iconName, titleText, sub, time, open, extra) {
            var row = el('div', 'pg-list-row ws-mycal-row' + (extra || ''));
            var main = el('div', 'pg-list-row-main');
            var head = el('div', 'pg-list-row-head');

            row.appendChild(iconName);
            head.appendChild(el('span', 'pg-list-row-title', titleText));

            if (time) {
                head.appendChild(el('span', 'pg-list-row-time pg-tabular', time));
            }

            main.appendChild(head);

            if (sub) {
                main.appendChild(el('div', 'pg-list-row-text', sub));
            }

            row.appendChild(main);

            if (open) {
                row.addEventListener('click', open);
                h.pressKeys(row, open);
            }

            return row;
        }

        function drawList(box) {
            var any = false;
            var body = el('div', 'ws-mycal-list');

            data.days.forEach(function (day) {
                // A task over several days is listed once, on its first day
                // in the range; its row names the whole span.
                var spans = (data.spans || []).filter(function (task) {
                    return (task.span_start < data.from ? data.from : task.span_start) === day.date;
                });

                if (!day.events.length && !day.tasks.length && !spans.length) {
                    return;
                }

                any = true;

                var section = el('section', 'ws-mycal-day' + (day.today ? ' is-today' : ''));
                section.appendChild(el('h3', 'pg-group-label ws-mycal-day-label', day.label));

                day.events.forEach(function (event) {
                    section.appendChild(listRow(el('span', 'ws-cal-event-dot ws-kind-' + event.kind), event.title,
                        event.layer || (event.readonly ? s('own_calendar') : (h.cfg.event_kinds && h.cfg.event_kinds[event.kind]) || ''),
                        event.time || h.t('all_day'),
                        (event.url || !event.readonly) ? function () { openEvent(event); } : null));
                });

                spans.concat(day.tasks).forEach(function (task) {
                    section.appendChild(listRow(task.upcoming ? h.icon('bi-arrow-repeat') : el('span', 'ws-status-dot ws-s-' + task.status),
                        (task.upcoming ? '' : task.number + ' · ') + task.title,
                        task.upcoming ? task.repeat : h.statusName(task.status) + (task.span_start ? ' · ' + h.dmy(task.span_start) + ' – ' + h.dmy(task.span_end) : ''),
                        task.time || '', function () { openTask(task); },
                        (task.open ? '' : ' is-read') + (task.overdue ? ' ws-mycal-late' : '')));
                });

                body.appendChild(section);
            });

            if (!any) {
                body.appendChild(el('p', 'ws-mycal-empty pg-text-2', s('nothing')));
            }

            box.appendChild(body);
        }

        function draw() {
            title.textContent = data.label;
            Object.keys(segItems).forEach(function (key) {
                segItems[key].setAttribute('aria-pressed', key === data.view ? 'true' : 'false');
            });

            var box = h.clear(card);

            card.classList.toggle('ws-mycal-week', data.view === 'week');
            card.classList.toggle('ws-mycal-as-list', data.view === 'list');

            if (data.view === 'list') {
                drawList(box);
            } else {
                drawGrid(box);
            }
        }

        load();
    }

    window.PGWsMyCalendar = { screen: screen };
})();
