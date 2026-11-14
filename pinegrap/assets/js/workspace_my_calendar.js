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
            date: /^\d{4}-\d{2}-\d{2}$/.test(query.get('date') || '') ? query.get('date') : '',
            // A reminder line opens its plan (?plan=N) once the days are drawn.
            plan: parseInt(query.get('plan') || 0, 10) || 0
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
                // The list starts on today; earlier days are one step back.
                if (option[0] === 'list' && state.view !== 'list') {
                    state.date = '';
                }

                state.view = option[0];
                keepView(state.view);
                load();
            });
            segItems[option[0]] = item;
            seg.appendChild(item);
        });

        bar.appendChild(seg);

        // New plan: only once the plan tables are there (data.plans).
        var newPlan = el('button', 'pg-button pg-button-fill pg-button-sm d-none');
        newPlan.type = 'button';
        newPlan.setAttribute('data-ws-new-plan', '');
        newPlan.appendChild(h.icon('bi-plus-lg'));
        newPlan.appendChild(document.createTextNode(s('new_plan')));
        newPlan.addEventListener('click', function () { planDrawer(null, { start: (data && data.today) || h.cfg.today }); });

        var work = el('a', 'pg-button pg-button-quiet pg-button-sm');
        work.href = h.cfg.urls.calendar;
        work.appendChild(h.icon('bi-calendar3'));
        work.appendChild(document.createTextNode(s('work_calendar')));
        bar.appendChild(work);
        bar.appendChild(newPlan);

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
                newPlan.classList.toggle('d-none', !result.plans);

                if (state.plan) {
                    openPlan(state.plan);
                    state.plan = 0;
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
                openPlan(event.plan_id);
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
                items.push({ icon: 'bi-journal-plus', label: s('new_plan'), action: function () { planDrawer(null, { start: day.date }); } });
            }

            return items.concat([
                { icon: 'bi-check2-square', label: h.t('new_task_here'), action: function () { h.taskDrawer(0, { due_date: day.date, assignees: [h.cfg.me] }, load); } },
                { icon: 'bi-calendar-plus', label: h.t('add_plan_item'), action: function () { h.planItem(0, { start: day.date, people: [h.cfg.me] }, load); } }
            ]);
        }

        // ── month and week: the grid ──

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
                var label = el('h3', 'pg-group-label ws-mycal-day-label', day.label);

                if (day.today) {
                    label.appendChild(el('span', 'pg-pill pg-tone-blue', h.t('today')));
                    label.setAttribute('aria-current', 'date');
                }

                section.appendChild(label);

                day.events.forEach(function (event) {
                    section.appendChild(listRow(el('span', 'ws-cal-event-dot ws-kind-' + event.kind), event.title,
                        event.plan_id ? (event.owner ? s('plan_of').replace('{1}', event.owner) : s('my_plan')) + (event.steps_total && window.PGWsRoadmap ? ' · ' + window.PGWsRoadmap.label(event, s) : '') : (event.layer || (event.readonly ? s('own_calendar') : (h.cfg.event_kinds && h.cfg.event_kinds[event.kind]) || '')),
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

                body.appendChild(section);
            });

            if (!any) {
                body.appendChild(el('p', 'ws-mycal-empty pg-text-2', s('nothing')));
            }

            box.appendChild(body);
        }

        // ── the person's own plan: the New plan drawer ──

        function openPlan(planId) {
            h.api('ws_personal_plan_get', { plan_id: planId }).then(function (result) {
                planDrawer(result.plan, {});
            }).catch(h.fail);
        }

        function field(label, control, help) {
            var row = el('div', 'ws-plan-field');
            var caption = el('label', 'pg-field-label', label);

            control.id = control.id || h.nextId('ws-plan-');
            caption.htmlFor = control.id;
            row.appendChild(caption);
            row.appendChild(control);

            if (help) {
                row.appendChild(el('div', 'pg-text-meta', help));
            }

            return row;
        }

        function input(type, value) {
            var node = el('input', 'pg-input');

            if (type === 'date' || type === 'time') {
                h.dateField(node, type);
            } else {
                node.type = type;
            }

            node.value = value || '';

            return node;
        }

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

            var name = input('text', plan ? plan.title : '');
            name.maxLength = 255;
            name.required = true;
            body.appendChild(field(s('plan_title'), name));

            var days = el('div', 'ws-plan-pair');
            var first = input('date', plan ? plan.start : (defaults.start || h.cfg.today));
            var last = input('date', plan ? plan.end : (defaults.start || h.cfg.today));
            days.appendChild(field(s('plan_first_day'), first));
            days.appendChild(field(s('plan_last_day'), last));
            body.appendChild(days);

            first.addEventListener('change', function () {
                if (!last.value || last.value < first.value) {
                    last.value = first.value;
                }
            });

            var wholeRow = el('label', 'ws-plan-switch');
            var whole = el('input', 'pg-switch');
            whole.type = 'checkbox';
            whole.checked = plan ? !!plan.all_day : !!defaults.all_day;
            wholeRow.appendChild(whole);
            wholeRow.appendChild(document.createTextNode(h.t('all_day')));
            body.appendChild(wholeRow);

            var times = el('div', 'ws-plan-pair');
            var from = input('time', plan && plan.start_time ? plan.start_time : '09:00');
            var until = input('time', plan && plan.end_time ? plan.end_time : '10:00');
            times.appendChild(field(s('plan_from'), from));
            times.appendChild(field(s('plan_until'), until));
            body.appendChild(times);

            function timeView() {
                times.classList.toggle('d-none', whole.checked);
            }

            whole.addEventListener('change', timeView);
            timeView();

            // The panel's select (its own caret), as the panel's forms draw one.
            var remind = el('select', 'form-control');
            [['', s('plan_no_remind')]].concat(Object.keys(S.remind_options || {}).map(function (key) {
                return [key, S.remind_options[key]];
            })).forEach(function (option) {
                var item = el('option', '', option[1]);
                item.value = option[0];
                remind.appendChild(item);
            });
            remind.value = plan ? plan.remind : '';

            if (remind.value !== (plan ? plan.remind : '')) {
                remind.value = '';
            }

            if (mine) {
                body.appendChild(field(s('plan_remind'), remind));
            }

            // Who can see: Only me, chosen team members, the whole team.
            var who = el('div', 'pg-seg ws-plan-who');
            var whoValue = plan && plan.visibility ? plan.visibility : 'me';
            var whoItems = {};

            who.setAttribute('role', 'group');
            who.setAttribute('aria-label', s('plan_who'));

            [['me', s('plan_only_me')], ['people', s('plan_chosen')], ['team', s('plan_team')]].forEach(function (option) {
                var item = el('button', 'pg-seg-item', option[1]);
                item.type = 'button';
                item.addEventListener('click', function () {
                    whoValue = option[0];
                    whoView();
                });
                whoItems[option[0]] = item;
                who.appendChild(item);
            });

            // The team but the owner: a plan is always its owner's to see.
            var picker = h.peoplePicker(plan ? (plan.people || []) : [], h.team().map(function (member) { return member.id; }).filter(function (id) { return id !== h.cfg.me; }));
            var pickerRow = el('div', 'ws-plan-field');
            var pickerHint = el('div', 'pg-field-help', s('plan_people_hint'));
            var pickerError = el('div', 'pg-field-help pg-field-error d-none', s('plan_people_error'));

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
                var whoRow = el('div', 'ws-plan-field');
                whoRow.appendChild(el('span', 'pg-field-label', s('plan_who')));
                whoRow.appendChild(who);
                body.appendChild(whoRow);
                body.appendChild(pickerRow);
                whoView();
            }

            var note = el('textarea', 'pg-input');
            note.rows = 4;
            note.maxLength = 2000;
            note.value = plan ? plan.note : '';
            body.appendChild(field(s('plan_note'), note));

            // The roadmap: dated steps and the plan's progress.
            var roadmap = window.PGWsRoadmap ? window.PGWsRoadmap.section(h, s, plan, {
                range: function () { return { start: first.value, end: last.value || first.value }; },
                saveAll: function () {
                    return submit().then(function (saved) {
                        h.hideOffcanvas(node);
                        load();

                        return saved;
                    });
                },
                reopen: function (planId) {
                    load();
                    openPlan(planId);
                }
            }) : null;

            if (roadmap) {
                body.appendChild(roadmap.node);
                [first, last].forEach(function (control) { control.addEventListener('change', roadmap.redraw); });
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
                var remove = el('button', 'pg-button pg-button-danger pg-button-sm me-auto', s('delete'));
                remove.type = 'button';
                remove.addEventListener('click', function () {
                    h.ask(s('plan_delete_q'), s('delete'), true).then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        h.api('ws_personal_plan_delete', { plan_id: plan.id }).then(function () {
                            h.hideOffcanvas(node);
                            h.toast(s('plan_deleted'), 'success');
                            load();
                        }).catch(h.fail);
                    });
                });
                footer.appendChild(remove);
            }

            var save = el('button', 'pg-button pg-button-fill pg-button-sm', s('save'));
            save.type = 'button';

            // Saves the plan, then its steps; resolves with the roadmap.
            function submit() {
                return h.api('ws_personal_plan_save', {
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
                    people: whoValue === 'people' ? picker.value() : []
                }).then(function (result) {
                    plan = plan || result.plan;

                    return roadmap ? roadmap.save(result.plan.id) : null;
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

                save.disabled = true;
                submit().then(function () {
                    save.disabled = false;
                    h.hideOffcanvas(node);
                    h.toast(s('plan_saved'), 'success');
                    load();
                }).catch(function (error) {
                    save.disabled = false;
                    h.fail(error);
                });
            });
            footer.appendChild(save);

            // The panel's one required marker on Title's label.
            if (window.pgInjectAriaAndRequiredMarkers) {
                window.pgInjectAriaAndRequiredMarkers(body);
            }

            h.showOffcanvas(node);
            window.setTimeout(function () { name.focus(); }, 200);
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
