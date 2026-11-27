/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - My calendar (workspace_my_calendar.php): the reader's own
 * tasks, the plan items they are in, their bookings and their own iCal
 * layer, by month, by week (its hour grid drawn by
 * workspace_my_calendar_week.js) or as a list of days. The data comes from the
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

    // A phone: the month's chosen day listed under the grid, the week one
    // day at a time.
    var PHONE = '(max-width: 575.98px)';

    function onPhone() {
        return !!(window.matchMedia && window.matchMedia(PHONE).matches);
    }

    // A Y-m-d moved by a number of days.
    function addDays(date, count) {
        var at = new Date(date + 'T12:00:00Z');

        at.setUTCDate(at.getUTCDate() + count);

        return at.toISOString().slice(0, 10);
    }

    function screen(root, h) {
        var S = (h.cfg.my_calendar && h.cfg.my_calendar.strings) || {};
        var el = h.el;
        var query = new URLSearchParams(window.location.search);
        var state = {
            view: query.get('view') || readView() || 'month',
            date: /^\d{4}-\d{2}-\d{2}$/.test(query.get('date') || '') ? query.get('date') : '',
            // A reminder line opens its plan (?plan=N) once the days are drawn.
            plan: parseInt(query.get('plan') || 0, 10) || 0,
            // ... on one of its times, for a repeating plan (day=Y-m-d).
            planDay: /^\d{4}-\d{2}-\d{2}$/.test(query.get('day') || '') ? query.get('day') : '',
            // "New roadmap" on a customer's card: a new plan naming them.
            contact: query.get('new_plan') ? (parseInt(query.get('contact') || 0, 10) || 0) : 0,
            // ... filled from one of the person's own plans (from=N).
            from: parseInt(query.get('from') || 0, 10) || 0,
            // ... or from one of the team's roadmap templates (template=N).
            template: parseInt(query.get('template') || 0, 10) || 0,
            // On a phone: the month's chosen day and the week's one day.
            picked: '',
            day: ''
        };
        var data = null;
        var plans = planKit(h, S, function () { load(); });

        function s(key) {
            return Object.prototype.hasOwnProperty.call(S, key) ? S[key] : key;
        }

        h.clear(root);
        root.classList.add('ws-mycal-screen');

        // ── the bar (board S2 of plans/workspace-interface): New plan, the
        // Work Calendar; previous, Today, next, the range; the views ──

        var frame = el('div', 'ws-bar-frame');
        var bar = el('nav', 'pg-toolbar ws-bar');

        // New plan: only once the plan tables are there (data.plans).
        var newPlan = h.bar.button('pg-button pg-button-fill d-none', s('new_plan'), 'bi-plus-lg');
        newPlan.setAttribute('data-ws-new-plan', '');
        newPlan.addEventListener('click', newPlanToday);
        bar.appendChild(newPlan);

        // On a phone New plan floats at the bottom right (.ws-mycal-fab), so
        // the bar's one line holds the days and the views.
        var newPlanFloat = h.bar.button('pg-button pg-button-fill ws-mycal-fab d-none', s('new_plan'), 'bi-plus-lg');
        newPlanFloat.addEventListener('click', newPlanToday);

        function newPlanToday() {
            plans.drawer(null, { start: (data && data.today) || h.cfg.today });
        }

        var work = el('a', 'pg-button pg-button-quiet');
        work.href = h.cfg.urls.calendar;
        work.setAttribute('data-ws-work-calendar', '');
        work.appendChild(h.icon('bi-calendar3'));
        work.appendChild(document.createTextNode(s('work_calendar')));
        bar.appendChild(h.bar.label(work));

        bar.appendChild(h.bar.steps(s('previous'), h.t('today'), s('next'), function (step) {
            if (!step) {
                state.date = '';
                state.picked = '';
                state.day = '';
                load();
            } else if (data && data.view === 'week' && onPhone()) {
                stepDay(step);
            } else if (data) {
                state.date = (step < 0) ? data.prev : data.next;
                load();
            }
        }));

        var title = el('h2', 'ws-cal-title pg-title-section');
        bar.appendChild(title);

        var views = h.bar.views(s('view'), [['month', s('month')], ['week', s('week')], ['list', s('list')]], state.view, function (view) {
            // The list starts on today; earlier days are one step back.
            if (view === 'list' && state.view !== 'list') {
                state.date = '';
            }

            // The week of a month that holds today is today's week, not the
            // month's first.
            if (view === 'week' && data && data.view === 'month' && data.today.slice(0, 7) === data.anchor.slice(0, 7)) {
                state.date = data.today;
            }

            state.view = view;
            keepView(state.view);
            load();
        });

        // The views as icons (look A's .pg-seg-icons): on a phone the chosen
        // one keeps its word, the others their icon.
        var viewIcons = { month: 'bi-calendar3', week: 'bi-calendar-week', list: 'bi-list-ul' };
        var viewSeg = views.node.querySelector('.pg-seg');

        viewSeg.classList.add('pg-seg-icons');
        viewSeg.querySelectorAll('.pg-seg-item').forEach(function (item, index) {
            var word = item.textContent;

            item.textContent = '';
            item.title = word;
            item.appendChild(h.icon(viewIcons[['month', 'week', 'list'][index]]));
            item.appendChild(el('span', 'pg-seg-label', word));
        });

        bar.appendChild(views.node);
        bar.appendChild(el('div', 'pg-toolbar-grow'));
        frame.appendChild(bar);

        var card = el('div', 'ws-cal card ws-mycal');

        root.appendChild(frame);
        root.appendChild(card);
        root.appendChild(newPlanFloat);

        // Turning a phone or widening the window redraws for its width.
        if (window.matchMedia) {
            var phoneQuery = window.matchMedia(PHONE);
            var redraw = function () {
                if (data && root.isConnected) {
                    draw();
                }
            };

            if (phoneQuery.addEventListener) {
                phoneQuery.addEventListener('change', redraw);
            } else if (phoneQuery.addListener) {
                phoneQuery.addListener(redraw);
            }
        }

        // The week's one day on a phone follows a sideways swipe.
        var touch = null;

        card.addEventListener('touchstart', function (event) {
            touch = (event.touches.length === 1) ? { x: event.touches[0].clientX, y: event.touches[0].clientY } : null;
        }, { passive: true });

        card.addEventListener('touchend', function (event) {
            if (!touch || !data || data.view !== 'week' || !onPhone() || !event.changedTouches.length) {
                touch = null;
                return;
            }

            var dx = event.changedTouches[0].clientX - touch.x;
            var dy = event.changedTouches[0].clientY - touch.y;

            touch = null;

            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) {
                stepDay(dx < 0 ? 1 : -1);
            }
        }, { passive: true });

        // One day on: within the week it is drawn again, past it the next
        // (or the previous) week is read.
        function stepDay(step) {
            var index = shownDay();
            var next = index + step;

            if (next >= 0 && next < data.days.length) {
                state.day = data.days[next].date;
                draw();
                return;
            }

            state.day = addDays(data.days[index].date, step);
            state.date = state.day;
            load();
        }

        // The week's day shown on a phone: the one stepped to, else today,
        // else the week's first.
        function shownDay() {
            var index = -1;

            data.days.forEach(function (day, at) {
                if (day.date === state.day) {
                    index = at;
                }
            });

            if (index < 0) {
                data.days.forEach(function (day, at) {
                    if (day.today) {
                        index = at;
                    }
                });
            }

            return index < 0 ? 0 : index;
        }

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
                newPlan.classList.toggle('d-none', !result.plans);
                newPlanFloat.classList.toggle('d-none', !result.plans);

                if (state.plan) {
                    plans.open(state.plan, state.planDay);
                    state.plan = 0;
                } else if (state.contact && result.plans) {
                    var contact = state.contact;

                    state.contact = 0;

                    if (state.from || state.template) {
                        h.api(state.template ? 'ws_roadmap_template_draft' : 'ws_personal_plan_copy_draft',
                            state.template ? { template_id: state.template } : { plan_id: state.from }).then(function (copied) {
                            plans.drawer(null, Object.assign({}, copied.draft, { contact_id: contact }));
                        }).catch(h.fail);
                        state.from = 0;
                        state.template = 0;
                    } else {
                        plans.drawer(null, { start: result.today, contact_id: contact });
                    }
                }
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
            if (event.plan_id) {
                plans.open(event.plan_id, event.plan_day);
            } else if (event.url) {
                window.location.href = event.url;
            } else if (!event.readonly) {
                h.planItem(event.id, null, load);
            }
        }

        function eventChip(event) {
            var item = el(event.readonly && !event.url && !event.plan_id ? 'div' : 'button', 'ws-cal-event ws-kind-' + event.kind);

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

            // A plan of the reader's own: the drawer's lock, "only you".
            if (event.plan_id) {
                item.appendChild(h.icon('bi-lock-fill', 'me-1'));
            }

            if (event.meeting_url) {
                item.appendChild(h.icon('bi-camera-video', 'me-1'));
            }

            // A time of a repeating plan, marked before it is opened.
            if (event.repeat) {
                var repeatIcon = h.icon('bi-arrow-repeat', 'me-1');
                repeatIcon.setAttribute('aria-label', (S.repeat_options || {})[event.repeat] || '');
                repeatIcon.setAttribute('role', 'img');
                item.appendChild(repeatIcon);
            }

            item.appendChild(document.createTextNode((event.time ? event.time + ' ' : '') + event.title));

            // A plan with a roadmap: how many of its steps are done.
            if (event.steps_total && window.PGWsRoadmap) {
                item.appendChild(el('span', 'ws-roadmap-count pg-tabular', window.PGWsRoadmap.progress(event)));
            }

            item.title = event.title + (event.layer ? ' · ' + event.layer : '') + (event.owner ? ' · ' + s('plan_of').replace('{1}', event.owner) : '');

            return item;
        }

        function dayMenu(day) {
            var items = [{ header: day.label }];

            if (data.plans) {
                items.push({ icon: 'bi-journal-plus', label: s('new_plan'), action: function () { plans.drawer(null, { start: day.date }); } });
            }

            return items.concat([
                { icon: 'bi-check2-square', label: h.t('new_task_here'), action: function () { h.taskDrawer(0, { due_date: day.date, assignees: [h.cfg.me] }, load); } },
                { icon: 'bi-calendar-plus', label: h.t('add_plan_item'), action: function () { h.planItem(0, { start: day.date, people: [h.cfg.me] }, load); } }
            ]);
        }

        function openDay(day) {
            state.view = 'list';
            state.picked = '';
            state.date = day.date;
            load();
        }

        // ── month: the grid ──

        function drawGrid(box) {
            var head = el('div', 'ws-cal-head');

            data.weekdays.forEach(function (name, index) {
                head.appendChild(el('div', index >= 5 ? 'ws-cal-weekend' : '', name));
            });

            box.appendChild(head);

            var grid = el('div', 'ws-cal-grid');
            var limit = 4;
            var weeks = [];
            var phone = onPhone();
            var cells = {};
            var picked = phone ? pickedDay() : null;
            var dayBox = el('div', 'ws-mycal-picked');

            for (var i = 0; i < data.days.length; i += 7) {
                weeks.push(data.days.slice(i, i + 7));
            }

            weeks.forEach(function (week) {
                var lanes = h.calendarLanes(data.spans || [], week[0].date, week[week.length - 1].date);

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

                    // A day opens as the list from that day; on a phone, where
                    // the cells show only bars, a tap chooses it and its list
                    // stands under the grid.
                    var press = phone ? function () { choose(day); } : function () { openDay(day); };

                    cell.addEventListener('click', press);
                    h.pressKeys(cell, press);
                    cells[day.date] = cell;
                    h.onContext(cell, function () { return dayMenu(day); });
                    grid.appendChild(cell);
                });
            });

            box.appendChild(grid);

            if (picked) {
                box.appendChild(dayBox);
                choose(picked);
            }

            function choose(day) {
                state.picked = day.date;

                Object.keys(cells).forEach(function (date) {
                    cells[date].classList.toggle('ws-cal-chosen', date === day.date);
                    cells[date].setAttribute('aria-pressed', date === day.date ? 'true' : 'false');
                });

                var section = daySection(day, (data.spans || []).filter(function (task) {
                    return task.span_start <= day.date && task.span_end >= day.date;
                }));

                h.clear(dayBox);

                // A free day: its heading and one quiet line.
                if (!section) {
                    section = el('section', 'ws-mycal-day');
                    section.appendChild(dayHeading(day));
                    section.appendChild(el('p', 'ws-mycal-picked-empty pg-text-meta', s('nothing_day')));
                }

                dayBox.appendChild(section);
            }
        }

        // The month's day chosen on a phone: the one tapped, else today,
        // else the month's first.
        function pickedDay() {
            var found = null;
            var first = null;
            var today = null;

            data.days.forEach(function (day) {
                if (!day.in_range) {
                    return;
                }

                first = first || day;
                found = (day.date === state.picked) ? day : found;
                today = day.today ? day : today;
            });

            return found || today || first;
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

            // A line whose title is its kind (a leave day) is written once.
            if (sub && sub !== titleText) {
                main.appendChild(el('div', 'pg-list-row-text', sub));
            }

            row.appendChild(main);

            if (open) {
                row.addEventListener('click', open);
                h.pressKeys(row, open);
            }

            return row;
        }

        // A day's heading: its name, and a "Today" pill on today.
        function dayHeading(day) {
            var label = el('h3', 'pg-group-label ws-mycal-day-label', day.label);

            if (day.today) {
                label.appendChild(el('span', 'pg-pill pg-tone-blue', h.t('today')));
                label.setAttribute('aria-current', 'date');
            }

            return label;
        }

        // One day's rows under its heading; null when it holds nothing.
        // spans: the tasks over several days listed on it.
        function daySection(day, spans) {
            if (!day.events.length && !day.tasks.length && !spans.length) {
                return null;
            }

            var section = el('section', 'ws-mycal-day' + (day.today ? ' is-today' : ''));

            section.appendChild(dayHeading(day));

            day.events.forEach(function (event) {
                section.appendChild(listRow(el('span', 'ws-cal-event-dot ws-kind-' + event.kind), event.title,
                    event.plan_id ? (event.owner ? s('plan_of').replace('{1}', event.owner) : s('my_plan')) + (event.repeat && S.repeat_options ? ' · ' + S.repeat_options[event.repeat] : '') + (event.steps_total && window.PGWsRoadmap ? ' · ' + window.PGWsRoadmap.label(event, s) : '') : (event.layer || (event.readonly ? s('own_calendar') : (h.cfg.event_kinds && h.cfg.event_kinds[event.kind]) || '')),
                    event.time || h.t('all_day'),
                    (event.url || event.plan_id || !event.readonly) ? function () { openEvent(event); } : null));
            });

            spans.concat(day.tasks).forEach(function (task) {
                section.appendChild(listRow(task.upcoming ? h.icon('bi-arrow-repeat') : el('span', 'ws-status-dot ws-s-' + task.status),
                    (task.upcoming ? '' : task.number + ' · ') + task.title,
                    task.upcoming ? task.repeat : h.statusName(task.status) + (task.span_start ? ' · ' + h.dmy(task.span_start) + ' – ' + h.dmy(task.span_end) : ''),
                    task.time || '', function () { openTask(task); },
                    (task.open ? '' : ' is-read') + (task.overdue ? ' ws-mycal-late' : '')));
            });

            return section;
        }

        function drawList(box) {
            var any = false;
            var body = el('div', 'ws-mycal-list');

            data.days.forEach(function (day) {
                // A task over several days is listed once, on its first day
                // in the range; its row names the whole span.
                var section = daySection(day, (data.spans || []).filter(function (task) {
                    return (task.span_start < data.from ? data.from : task.span_start) === day.date;
                }));

                if (section) {
                    any = true;
                    body.appendChild(section);
                }
            });

            if (!any) {
                body.appendChild(el('p', 'ws-mycal-empty pg-text-2', s('nothing')));
            }

            box.appendChild(body);
        }

        function draw() {
            var phone = onPhone();
            var weekDay = (data.view === 'week' && phone) ? data.days[shownDay()] : null;

            // On a phone the short name ("Nov 2026") in place of a cut one;
            // the week's one day by its own name.
            title.textContent = weekDay ? weekDay.label : ((phone && data.label_short) ? data.label_short : data.label);
            title.title = data.label;
            views.set(data.view);

            var box = h.clear(card);

            card.classList.toggle('ws-mycal-week', data.view === 'week');
            card.classList.toggle('ws-mycal-as-list', data.view === 'list');

            if (data.view === 'list') {
                drawList(box);
            } else if (data.view === 'week' && window.PGWsMyCalendarWeek) {
                // A phone: one day of the week with its hours.
                var weekData = weekDay ? Object.assign({}, data, {
                    days: [weekDay],
                    weekdays: [data.weekdays[shownDay()]],
                    from: weekDay.date,
                    to: weekDay.date
                }) : data;

                window.PGWsMyCalendarWeek.draw(box, {
                    h: h,
                    data: weekData,
                    eventChip: eventChip,
                    taskChip: taskChip,
                    dayMenu: dayMenu,
                    openDay: openDay,
                    newPlanLabel: s('new_plan'),
                    // An empty hour: New plan on that day, for that hour.
                    newPlan: data.plans ? function (day, hour) {
                        var clock = window.PGWsMyCalendarWeek.clock;

                        plans.drawer(null, { start: day.date, start_time: clock(hour * 60), end_time: clock(Math.min(hour + 1, 23) * 60 + (hour === 23 ? 59 : 0)) });
                    } : null
                });
            } else {
                drawGrid(box);
            }
        }

        load();
    }

    // ── the person's own plan: the New plan drawer ──
    //
    // Opened by My calendar and by Add to my calendar on a mail
    // (fromMail()); done() runs after a save or a delete.

    function planKit(h, S, done) {
        var el = h.el;

        function s(key) {
            return Object.prototype.hasOwnProperty.call(S, key) ? S[key] : key;
        }


        function openPlan(planId, day) {
            h.api('ws_personal_plan_get', { plan_id: planId, day: day || '' }).then(function (result) {
                planDrawer(result.plan, {});
            }).catch(h.fail);
        }

        // Every field of the drawer comes from the kit's form parts.
        var part = h.form;

        function planDrawer(plan, defaults) {
            var node = h.offcanvas('ws-plan-drawer', '');
            var body = h.clear(node.querySelector('.drawer-body'));
            var footer = h.clear(node.querySelector('.drawer-footer'));

            footer.classList.remove('d-none');
            node.querySelector('.drawer-title').textContent = plan ? s('edit_plan') : s('new_plan');

            var lock = el('p', 'ws-plan-private pg-text-meta');
            lock.appendChild(h.icon('bi-lock', 'me-1'));
            var mine = !plan || plan.mine;

            var lockText = document.createTextNode(mine ? s('plan_private') : s('plan_shared_by').replace('{1}', plan.owner_name));

            lock.appendChild(lockText);
            body.appendChild(lock);

            // A plan made from a mail: a quiet line with its subject opening
            // the conversation, sent only to a reader who may read it.
            if (plan && plan.mail && h.cfg.urls.mail) {
                var mailLink = el('a', 'ws-plan-mail pg-text-meta d-block mb-3');
                mailLink.href = h.cfg.urls.mail + '?conversation=' + plan.mail.conversation_id;
                mailLink.title = s('plan_mail');
                mailLink.appendChild(h.icon('bi-envelope', 'me-1'));
                mailLink.appendChild(document.createTextNode(plan.mail.subject));
                body.appendChild(mailLink);
            }

            var name = part.input('text', plan ? plan.title : (defaults.title || ''));
            name.maxLength = 255;
            name.required = true;
            body.appendChild(part.field(s('plan_title'), name));

            var first = part.input('date', plan ? plan.start : (defaults.start || h.cfg.today));
            var last = part.input('date', plan ? plan.end : (defaults.end || defaults.start || h.cfg.today));
            body.appendChild(part.pair(part.field(s('plan_first_day'), first), part.field(s('plan_last_day'), last)));

            first.addEventListener('change', function () {
                if (!last.value || last.value < first.value) {
                    last.value = first.value;
                }
            });

            var wholeSwitch = part.switchRow(h.t('all_day'), plan ? !!plan.all_day : !!defaults.all_day);
            var whole = wholeSwitch.input;
            body.appendChild(wholeSwitch.row);

            var from = part.input('time', plan && plan.start_time ? plan.start_time : (defaults.start_time || '09:00'));
            var until = part.input('time', plan && plan.end_time ? plan.end_time : (defaults.end_time || '10:00'));
            var times = part.pair(part.field(s('plan_from'), from), part.field(s('plan_until'), until));
            body.appendChild(times);

            function timeView() {
                times.classList.toggle('d-none', whole.checked);
            }

            whole.addEventListener('change', timeView);
            timeView();

            // The panel's select (its own caret), as the panel's forms draw one.
            var remind = part.select([['', s('plan_no_remind')]].concat(Object.keys(S.remind_options || {}).map(function (key) {
                return [key, S.remind_options[key]];
            })), plan ? plan.remind : (defaults.remind || ''));

            if (remind.value !== (plan ? plan.remind : (defaults.remind || ''))) {
                remind.value = '';
            }

            if (mine) {
                body.appendChild(part.field(s('plan_remind'), remind));
            }

            // Repeat: every day, week, month or year; until a day or N times.
            // A roadmap does not repeat, so its section hides while one is set.
            var repeat = part.select([['', s('repeat_none')]].concat(Object.keys(S.repeat_options || {}).map(function (key) {
                return [key, S.repeat_options[key]];
            })), plan ? (plan.repeat || '') : '');

            var every = part.input('number', String(plan && plan.repeat_every ? plan.repeat_every : 1));
            every.min = '1';
            every.max = '99';
            every.inputMode = 'numeric';

            var ends = part.select([['never', s('repeat_end_never')], ['until', s('repeat_end_until')], ['count', s('repeat_end_count')]], plan ? (plan.repeat_end || 'never') : 'never');

            var endDay = part.input('date', plan && plan.repeat_until ? plan.repeat_until : '');
            var endCount = part.input('number', plan && plan.repeat_count ? plan.repeat_count : '10');
            endCount.min = '1';
            endCount.max = '999';
            endCount.inputMode = 'numeric';

            // Two rows of two, as the days and the hours: Repeat with Repeat
            // every (the unit beside the number), then Repeat ends with the
            // number of times or the last day.
            var everyRow = part.field(s('repeat_every'), every);
            var everyLine = el('div', 'ws-plan-every');
            // The unit beside "Repeat every" on one line: days, weeks,
            // months or years.
            var unitNote = el('span', 'pg-text-meta');
            everyRow.insertBefore(everyLine, every);
            everyLine.appendChild(every);
            everyLine.appendChild(unitNote);

            var repeatMore = el('div', 'ws-plan-repeat');
            var endDayRow = part.field(s('repeat_until'), endDay);
            var endCountRow = part.field(s('repeat_count'), endCount);
            repeatMore.appendChild(part.pair(part.field(s('repeat_ends'), ends), endDayRow, endCountRow));

            var roadmap = null;

            function repeatView() {
                repeatMore.classList.toggle('d-none', !repeat.value);
                everyRow.classList.toggle('d-none', !repeat.value);
                // "Never" leaves the second place of the row empty.
                endDayRow.classList.toggle('d-none', ends.value !== 'until');
                endCountRow.classList.toggle('d-none', ends.value !== 'count');
                unitNote.textContent = repeat.value ? s('repeat_unit_' + repeat.value) : '';

                if (roadmap) {
                    roadmap.node.classList.toggle('d-none', !!repeat.value);
                }
            }

            repeat.addEventListener('change', repeatView);
            ends.addEventListener('change', repeatView);

            if (mine) {
                body.appendChild(part.pair(part.field(s('repeat'), repeat), everyRow));
                body.appendChild(repeatMore);
            }

            // Who can see: Only me, chosen team members, the whole team.
            var whoValue = plan && plan.visibility ? plan.visibility : 'me';
            var whoSeg = part.seg(s('plan_who'), [['me', s('plan_only_me')], ['people', s('plan_chosen')], ['team', s('plan_team')]], function (value) {
                whoValue = value;
                whoView();
            }, 'ws-plan-who');
            var whoItems = whoSeg.items;

            // The team but the owner: a plan is always its owner's to see.
            var picker = h.peoplePicker(plan ? (plan.people || []) : [], h.team().map(function (member) { return member.id; }).filter(function (id) { return id !== h.cfg.me; }));
            var pickerRow = el('div', 'ws-plan-field');
            var pickerHint = part.help(s('plan_people_hint'));
            var pickerError = part.error(s('plan_people_error'));

            pickerHint.id = h.nextId('ws-plan-people-');
            pickerError.id = h.nextId('ws-plan-people-');
            picker.setAttribute('aria-label', s('plan_chosen'));
            picker.setAttribute('aria-describedby', pickerHint.id);
            pickerRow.appendChild(picker);
            pickerRow.appendChild(pickerHint);
            pickerRow.appendChild(pickerError);

            // The hint while nobody is chosen; a choice clears the error.
            function pickerView() {
                var none = !picker.value().length;

                pickerHint.classList.toggle('d-none', !none);

                if (!none) {
                    pickerError.classList.add('d-none');
                    picker.removeAttribute('aria-invalid');
                    picker.setAttribute('aria-describedby', pickerHint.id);
                }
            }

            picker.addEventListener('change', pickerView);
            pickerView();

            function whoView() {
                Object.keys(whoItems).forEach(function (key) {
                    whoItems[key].setAttribute('aria-pressed', key === whoValue ? 'true' : 'false');
                });
                pickerRow.classList.toggle('d-none', whoValue !== 'people');
                lockText.textContent = s({ me: 'plan_private', people: 'plan_private_people', team: 'plan_private_team' }[whoValue]);
            }

            if (mine) {
                body.appendChild(part.group(s('plan_who'), whoSeg.node));
                body.appendChild(pickerRow);
                whoView();
            }

            var note = part.textarea(plan ? plan.note : (defaults.note || ''), 4);
            note.maxLength = 2000;
            body.appendChild(part.field(s('plan_note'), note));

            // The customer the plan is made for: the visit's field, read for
            // this reader (hidden when they may not open the contact) and
            // saved only once it was changed here.
            var customer = null;
            var customerRow = part.group(s('plan_customer'));

            if (h.customerPicker && (plan ? plan.id : true)) {
                body.appendChild(customerRow);

                var contactAsked = plan ? { plan_id: plan.id } : { contact_id: defaults.contact_id || 0 };

                (plan || defaults.contact_id ? h.api('ws_personal_plan_contact', contactAsked) : Promise.resolve({ contact: null })).then(function (result) {
                    customer = h.customerPicker(result.contact, !mine);

                    // A new roadmap from the card names its customer at once.
                    if (!plan && result.contact && !result.contact.hidden) {
                        customer.changed = true;
                    }

                    customerRow.appendChild(customer.node);
                    customerRow.appendChild(el('div', 'pg-text-meta', s('plan_customer_help')));
                }).catch(h.fail);
            }

            // The roadmap: dated steps and the plan's progress.
            roadmap = window.PGWsRoadmap ? window.PGWsRoadmap.section(h, s, plan, {
                // A copy's steps, for a new plan filled from another.
                steps: plan ? null : (defaults.steps || null),
                range: function () { return { start: first.value, end: last.value || first.value }; },
                saveAll: function () {
                    return submit().then(function (saved) {
                        h.hideOffcanvas(node);
                        done();

                        return saved;
                    });
                },
                reopen: function (planId) {
                    done();
                    openPlan(planId);
                }
            }) : null;

            if (roadmap) {
                body.appendChild(roadmap.node);
                [first, last].forEach(function (control) { control.addEventListener('change', roadmap.redraw); });
            }

            repeatView();

            // A repeating plan asks which times a change or a delete is for:
            // this one, or this one and the ones after it. '' when called off.
            var series = !!(plan && plan.repeat);

            function askTimes(question, okLabel, danger) {
                if (!series) {
                    return Promise.resolve('all');
                }

                var box = el('div', 'ws-ask-choice');
                var inputs = {};

                [['one', s('repeat_this_one')], ['following', s('repeat_following')]].forEach(function (item, index) {
                    var line = el('div', 'form-field');
                    var radio = el('input', 'radio');
                    radio.type = 'radio';
                    radio.name = 'ws-plan-times';
                    radio.value = item[0];
                    radio.id = h.nextId('ws-plan-times-');
                    radio.checked = (index === 0);

                    var label = el('label', '', item[1]);
                    label.htmlFor = radio.id;
                    line.appendChild(radio);
                    line.appendChild(label);
                    box.appendChild(line);
                    inputs[item[0]] = radio;
                });

                return h.ask(question, okLabel, danger, box).then(function (yes) {
                    return yes ? (inputs.following.checked ? 'following' : 'one') : '';
                });
            }

            // Somebody else's plan opened to the reader: read, not changed.
            if (!mine) {
                // The panel's read-only state: every field dimmed, nothing to type in.
                body.classList.add('ws-plan-readonly');
                body.querySelectorAll('input, textarea, select').forEach(function (control) {
                    control.disabled = true;
                });
                footer.classList.add('d-none');
                h.showOffcanvas(node);
                return;
            }

            if (plan) {
                var remove = part.remove(s('delete'));
                remove.addEventListener('click', function () {
                    (series ? askTimes(s('repeat_delete_q'), s('delete'), true) : h.ask(s('plan_delete_q'), s('delete'), true)).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        h.api('ws_personal_plan_delete', { plan_id: plan.id, mode: series ? yes : '', day: plan.time_day || plan.start }).then(function () {
                            h.hideOffcanvas(node);
                            h.toast(s('plan_deleted'), 'success');
                            done();
                        }).catch(h.fail);
                    });
                });
                footer.appendChild(remove);
            }

            var save = part.save(s('save'));

            // Saves the plan, then its steps; resolves with the roadmap.
            // mode: one or following for a time of a repeating plan.
            function submit(mode) {
                return h.api('ws_personal_plan_save', {
                    mode: mode || '',
                    time_day: plan ? (plan.time_day || plan.start) : '',
                    repeat: repeat.value,
                    repeat_every: every.value,
                    repeat_end: ends.value,
                    repeat_until: endDay.value,
                    repeat_count: endCount.value,
                    plan_id: plan ? plan.id : 0,
                    title: name.value,
                    start: first.value,
                    end: last.value,
                    all_day: whole.checked ? 1 : 0,
                    start_time: from.value,
                    end_time: until.value,
                    remind: remind.value,
                    note: note.value,
                    visibility: whoValue,
                    // Add to my calendar: the mail the new plan is tied to.
                    mail_item: plan ? 0 : (defaults.mail_item || 0),
                    people: whoValue === 'people' ? picker.value() : []
                }).then(function (result) {
                    plan = plan || result.plan;

                    if (!customer || !customer.changed) {
                        return result;
                    }

                    return h.api('ws_personal_plan_contact_save', { plan_id: result.plan.id, contact_id: customer.id }).then(function () {
                        customer.changed = false;

                        return result;
                    });
                }).then(function (result) {
                    return (roadmap && !repeat.value) ? roadmap.save(result.plan.id) : null;
                });
            }

            save.addEventListener('click', function () {
                // Chosen team members with nobody chosen: said at the field.
                if (whoValue === 'people' && !picker.value().length) {
                    pickerError.classList.remove('d-none');
                    pickerHint.classList.add('d-none');
                    picker.setAttribute('aria-invalid', 'true');
                    picker.setAttribute('aria-describedby', pickerError.id);
                    picker.scrollIntoView({ block: 'nearest' });
                    return;
                }

                askTimes(s('repeat_change_q'), s('save'), false).then(function (mode) {
                    if (!mode) {
                        return;
                    }

                    save.disabled = true;
                    submit(mode === 'all' ? '' : mode).then(doneSaving).catch(function (error) {
                        save.disabled = false;
                        h.fail(error);
                    });
                });
            });

            function doneSaving() {
                save.disabled = false;
                h.hideOffcanvas(node);
                h.toast(s('plan_saved'), 'success');
                done();
            }

            footer.appendChild(save);

            // The panel's one required marker on Title's label.
            if (window.pgInjectAriaAndRequiredMarkers) {
                window.pgInjectAriaAndRequiredMarkers(body);
            }

            h.showOffcanvas(node);
            window.setTimeout(function () { name.focus(); }, 200);
        }
        return { open: openPlan, drawer: planDrawer };
    }

    // Add to my calendar on a mail (workspace.js, workspace_mail.js): the
    // New plan drawer filled from the mail, or the person's plan of it (one
    // a person), its words sent with it. mode: '' (the menu, Add to my
    // calendar), open, update (a changed invitation filled in), remove (a
    // cancelled one: deleted after a confirmation). done() after a change.
    function fromMail(h, itemId, mode, done) {
        done = done || function () {};

        h.api('ws_personal_plan_mail', { item_id: itemId, mode: mode || '' }).then(function (result) {
            var strings = result.strings || {};

            if (result.remove && result.plan) {
                h.ask(strings.plan_delete_q || '', strings.delete || '', true).then(function (yes) {
                    if (!yes) {
                        return;
                    }

                    h.api('ws_personal_plan_delete', { plan_id: result.plan.id }).then(function () {
                        h.toast(strings.plan_deleted || '', 'success');
                        done();
                    }).catch(h.fail);
                });

                return;
            }

            planKit(h, strings, done).drawer(result.plan || null, result.fill || {});
        }).catch(h.fail);
    }

    // /remind without a time (workspace.js): the New plan drawer opened on
    // the channel's screen with what was typed, its texts sent with it.
    // done() once the plan is saved.
    function fill(h, values, strings, done) {
        planKit(h, strings || {}, done || function () {}).drawer(null, values || {});
    }

    window.PGWsMyCalendar = { screen: screen, fromMail: fromMail, fill: fill };
})();
