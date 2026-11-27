/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - My calendar's week (workspace_my_calendar.php): the seven
 * days under a strip of what has no hour (whole-day plans and leave, tasks
 * without a due time, tasks over several days), then an hour grid, 07:00
 * to 20:00 in view and the rest of the day reached by scrolling. Where each
 * item with a time stands, and how items that overlap share a day's width,
 * comes from the server (ws_my_calendar_day_hours(), each day's hours); this
 * file only draws it. A line marks now; a click on an empty hour, or Enter
 * on a focused one, opens New plan on that day and hour.
 *
 * assets/js/workspace_my_calendar.js calls draw() with its chips and
 * actions. Served minified inside assets/js/workspace.min.js
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

    // The hours in view when the week opens: 07:00 up to 20:00.
    var FIRST_HOUR = 7;

    // The line of now moves once a minute while the week is shown.
    var nowTimer = 0;

    function pad(number) {
        return (number < 10 ? '0' : '') + number;
    }

    function clock(minutes) {
        return pad(Math.floor(minutes / 60)) + ':' + pad(minutes % 60);
    }

    // At a share of the day: the grid's height is 24 hours.
    function at(minutes) {
        return (minutes / 14.4) + '%';
    }

    // kit: h (the Workspace helpers), data (ws_my_calendar()), eventChip,
    // taskChip (chip builders of the screen), newPlan(day, hour) (null when
    // no plan may be made), dayMenu(day), openDay(day), target(node, date,
    // minuteAt) and minuteAt(column) (workspace_my_calendar_move.js, null
    // when nothing is dropped).
    function draw(box, kit) {
        var h = kit.h;
        var el = h.el;
        var data = kit.data;
        var wrap = el('div', 'ws-mycal-hours' + (data.days.length === 1 ? ' ws-mycal-one-day' : ''));

        window.clearInterval(nowTimer);

        // Seven columns, or one day at a time on a phone.
        wrap.style.setProperty('--ws-mycal-days', String(data.days.length));

        // ── the days' heads, each opening that day as a list ──

        var head = el('div', 'ws-mycal-hours-row ws-mycal-hours-head');

        head.appendChild(el('div', 'ws-mycal-gutter'));

        data.days.forEach(function (day, index) {
            var cell = el('div', 'ws-mycal-hours-day' + (day.today ? ' ws-cal-today' : '') + (day.weekend ? ' ws-cal-weekend' : ''));

            cell.appendChild(el('span', 'ws-mycal-weekday', data.weekdays[index] || ''));
            cell.appendChild(el('span', 'ws-cal-num', day.day));
            cell.setAttribute('aria-label', day.label);

            if (day.today) {
                cell.setAttribute('aria-current', 'date');
            }

            cell.addEventListener('click', function () { kit.openDay(day); });
            h.pressKeys(cell, function () { kit.openDay(day); });
            head.appendChild(cell);
        });

        wrap.appendChild(head);

        // ── the strip: what has no hour ──

        var strip = el('div', 'ws-mycal-hours-row ws-mycal-strip');
        var lanes = h.calendarLanes(data.spans || [], data.from, data.to);

        strip.appendChild(el('div', 'ws-mycal-gutter pg-text-meta', h.t('all_day')));

        data.days.forEach(function (day, column) {
            var cell = el('div', 'ws-mycal-strip-day' + (day.weekend ? ' ws-cal-weekend' : ''));
            var placed = { event: {}, task: {} };
            var used = 0;

            (day.hours || []).forEach(function (place) {
                placed[place.type][place.index] = true;
            });

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

                cell.appendChild(task ? kit.taskChip(task, {
                    left: column > 0 && task.span_start < day.date,
                    right: column < data.days.length - 1 && task.span_end > day.date,
                    label: task.span_start === day.date || column === 0
                }) : el('div', 'ws-cal-lane-gap'));
            });

            day.events.forEach(function (event, index) {
                if (!placed.event[index]) {
                    cell.appendChild(kit.eventChip(event));
                }
            });

            day.tasks.forEach(function (task, index) {
                if (!placed.task[index]) {
                    cell.appendChild(kit.taskChip(task));
                }
            });

            h.onContext(cell, function () { return kit.dayMenu(day); });

            if (kit.target) {
                kit.target(cell, day.date, null);
            }

            strip.appendChild(cell);
        });

        wrap.appendChild(strip);

        // ── the hours ──

        var scroller = el('div', 'ws-mycal-scroll');
        var grid = el('div', 'ws-mycal-hours-row ws-mycal-hours-grid');
        var gutter = el('div', 'ws-mycal-gutter ws-mycal-hour-labels');
        var slots = [];

        for (var hour = 1; hour < 24; hour++) {
            var label = el('span', 'ws-mycal-hour-label pg-text-meta pg-tabular', clock(hour * 60));

            label.style.top = at(hour * 60);
            gutter.appendChild(label);
        }

        grid.appendChild(gutter);

        data.days.forEach(function (day, column) {
            var col = el('div', 'ws-mycal-hours-col' + (day.today ? ' ws-cal-today' : '') + (day.weekend ? ' ws-cal-weekend' : ''));

            slots[column] = [];

            for (var hour = 0; hour < 24; hour++) {
                col.appendChild(slot(day, column, hour));
            }

            (day.hours || []).forEach(function (place) {
                col.appendChild(block(day, place));
            });

            if (day.today && typeof data.now === 'number' && data.now >= 0) {
                col.appendChild(nowLine(data.now));
            }

            h.onContext(col, function () { return kit.dayMenu(day); });

            // A plan or task dropped on the hours: that day, at the hour
            // under its top.
            if (kit.target) {
                kit.target(col, day.date, kit.minuteAt ? kit.minuteAt(col) : null);
            }

            grid.appendChild(col);
        });

        scroller.appendChild(grid);
        wrap.appendChild(scroller);
        box.appendChild(wrap);

        // 07:00 at the top of the view, its label whole above the line.
        scroller.scrollTop = Math.max(0, Math.round(grid.offsetHeight * FIRST_HOUR / 24) - 10);

        // One hour takes the focus from the keyboard: today's (or Monday's)
        // nine o'clock first, then the one last reached with the arrows.
        if (kit.newPlan) {
            var first = data.days.findIndex(function (day) { return day.today; });

            slots[first < 0 ? 0 : first][9].tabIndex = 0;
        }

        // An hour of a day: its line on the grid; empty, it takes a click
        // or Enter for New plan on that day and hour.
        function slot(day, column, hour) {
            var cell = el('div', 'ws-mycal-slot');

            slots[column][hour] = cell;

            if (!kit.newPlan) {
                return cell;
            }

            cell.tabIndex = -1;
            cell.setAttribute('role', 'button');
            cell.setAttribute('aria-label', kit.newPlanLabel + ' · ' + day.label + ' ' + clock(hour * 60));
            cell.addEventListener('click', function () { kit.newPlan(day, hour); });
            cell.addEventListener('keydown', function (event) {
                var to = null;

                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    kit.newPlan(day, hour);
                    return;
                }

                if (event.key === 'ArrowUp' && hour > 0) {
                    to = slots[column][hour - 1];
                } else if (event.key === 'ArrowDown' && hour < 23) {
                    to = slots[column][hour + 1];
                } else if (event.key === 'ArrowLeft' && column > 0) {
                    to = slots[column - 1][hour];
                } else if (event.key === 'ArrowRight' && column < slots.length - 1) {
                    to = slots[column + 1][hour];
                }

                if (to) {
                    event.preventDefault();
                    cell.tabIndex = -1;
                    to.tabIndex = 0;
                    to.focus();
                }
            });

            return cell;
        }

        // An item with a time: at its start, as tall as it lasts, its share
        // of the day's width beside the items it overlaps.
        function block(day, place) {
            var item = (place.type === 'task')
                ? kit.taskChip(day.tasks[place.index])
                : kit.eventChip(day.events[place.index]);
            var when = (place.type === 'task')
                ? day.tasks[place.index].time
                : clock(place.start) + ' – ' + (day.events[place.index].until || clock(place.end));

            item.classList.add('ws-mycal-block');
            item.style.top = at(place.start);
            item.style.height = at(place.end - place.start);
            item.style.left = (place.column * 100 / place.columns) + '%';
            item.style.width = (100 / place.columns) + '%';
            item.title = when + '\n' + item.title;

            return item;
        }

        // The line of now on today's column, moved once a minute.
        function nowLine(minutes) {
            var line = el('div', 'ws-mycal-now');
            var loaded = Date.now();

            line.setAttribute('aria-hidden', 'true');
            line.style.top = at(minutes);

            nowTimer = window.setInterval(function () {
                if (!line.isConnected) {
                    window.clearInterval(nowTimer);
                    return;
                }

                line.style.top = at(Math.min(1439, minutes + Math.floor((Date.now() - loaded) / 60000)));
            }, 60000);

            return line;
        }
    }

    window.PGWsMyCalendarWeek = { draw: draw, clock: clock };
})();
