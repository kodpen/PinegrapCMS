/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the dates and the repeat of a task, drawn as one box in the
 * task drawer.
 *
 * assets/js/workspace.js draws the drawer, makes the start and due date
 * fields and asks this file for the box around them:
 * PGWsRecurrence.field(task, defaults, editable, helpers, dates) returns a
 * node to put in the form and a value() the drawer sends with the task as
 * `recurrence` (null when there is nothing to send). The server checks and
 * stores it (includes/workspace/recurrence.php); every text comes from the
 * #ws-config block.
 *
 * The repeat is read off the due date: the choices name the day they fall
 * on ("Every month on day 16", "Every week on Wednesday") and follow the date
 * as it changes. The next copies are counted here the way the server counts
 * them, moved off weekends and holidays by the company's calendar
 * (includes/workspace/workdays.php), so the box shows what saving will do
 * before it is saved. A date picked by hand that is a day off is pointed out,
 * with the next working day one click away.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var configNode = document.getElementById('ws-config');
    var CFG = {};

    try {
        CFG = JSON.parse((configNode && configNode.textContent) || '{}');
    } catch (error) {
        CFG = {};
    }

    var REPEAT = CFG.recurrence || {};
    var S = CFG.strings || {};
    var WEEKDAYS = [1, 2, 3, 4, 5];

    // The working calendar: the week as a bit mask (Monday bit 0) and the
    // days off, date => [{name, department}].
    var CAL = REPEAT.calendar || null;
    var OFF = {};

    if (CAL) {
        (CAL.holidays || []).forEach(function (item) {
            (OFF[item[0]] = OFF[item[0]] || []).push({ name: item[1], department: item[2] });
        });
    }

    function text(key) {
        var out = (S[key] !== undefined) ? String(S[key]) : key;

        for (var i = 1; i < arguments.length; i++) {
            out = out.split('{' + i + '}').join(String(arguments[i]));
        }

        return out;
    }

    function el(tag, className, content) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if ((content !== undefined) && (content !== null) && (content !== '')) {
            node.textContent = String(content);
        }

        return node;
    }

    function icon(name) {
        var node = el('i', 'bi ' + name);
        node.setAttribute('aria-hidden', 'true');

        return node;
    }

    // ── Dates, counted at noon UTC so no time zone moves a day ──

    function parse(value) {
        var parts = String(value || '').split('-');

        if (parts.length !== 3) {
            return null;
        }

        var date = new Date(Date.UTC(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10), 12));

        return isNaN(date.getTime()) ? null : date;
    }

    function iso(date) {
        return date.toISOString().slice(0, 10);
    }

    function addDays(value, days) {
        var date = parse(value);
        date.setUTCDate(date.getUTCDate() + days);

        return iso(date);
    }

    function weekday(value) {
        return ((parse(value).getUTCDay() + 6) % 7) + 1;
    }

    function between(from, to) {
        return Math.round((parse(to).getTime() - parse(from).getTime()) / 86400000);
    }

    function dayLabel(value) {
        var parts = String(value || '').split('-');

        return (parts.length === 3) ? (parts[2] + '.' + parts[1] + '.' + parts[0]) : '';
    }

    function shortLabel(value) {
        var parts = String(value || '').split('-');

        return (parts.length === 3) ? (parts[2] + '.' + parts[1]) : '';
    }

    // A copy's days: "13.10–16.10.2026", with the start's year when it is
    // not the due date's.
    function rangeLabel(start, due) {
        if (!start || (start === due)) {
            return dayLabel(due);
        }

        return ((start.slice(0, 4) === due.slice(0, 4)) ? shortLabel(start) : dayLabel(start)) + '–' + dayLabel(due);
    }

    function dayMonth(value) {
        var date = parse(value);

        return date.getUTCDate() + ' ' + ((REPEAT.months || {})[date.getUTCMonth() + 1] || '');
    }

    // ── Days off, the same answers as ws_day_off() and ws_next_workday() ──

    // Why a day is not worked, or '' when it is.
    function dayOff(value, department) {
        if (!CAL || !parse(value)) {
            return '';
        }

        var holidays = OFF[value] || [];

        for (var i = 0; i < holidays.length; i++) {
            if (!holidays[i].department || (holidays[i].department === department)) {
                return holidays[i].name;
            }
        }

        return (CAL.week & (1 << (weekday(value) - 1))) ? '' : ((REPEAT.weekday_names || {})[weekday(value)] || '');
    }

    function nextWorkday(value, department) {
        var cursor = value;

        for (var i = 0; i < 62; i++) {
            if (!dayOff(cursor, department)) {
                return cursor;
            }

            cursor = addDays(cursor, 1);
        }

        return value;
    }

    // A copy's days for the day of the rule it stands for.
    function copyDates(date, gap, department) {
        var due = nextWorkday(date, department);
        var start = (gap > 0) ? addDays(date, -gap) : date;
        start = nextWorkday(start, department);

        return { due: due, start: (start > due) ? due : start, date: date, moved: due !== date };
    }

    // ── The rule, the same steps as ws_recurrence_nth() and _next() ──

    function nth(rule, k) {
        var anchor = parse(rule.anchor);
        var step = rule.interval * k;

        if (rule.frequency === 'daily') {
            return addDays(rule.anchor, step);
        }

        if (rule.frequency === 'weekly') {
            return addDays(rule.anchor, step * 7);
        }

        var year = anchor.getUTCFullYear();
        var month = anchor.getUTCMonth();

        if (rule.frequency === 'yearly') {
            year += step;
        } else {
            month += step;
            year += Math.floor(month / 12);
            month = month % 12;
        }

        // A month without the day takes its last one.
        var last = new Date(Date.UTC(year, month + 1, 0, 12)).getUTCDate();

        return iso(new Date(Date.UTC(year, month, Math.min(anchor.getUTCDate(), last), 12)));
    }

    function upcoming(rule, count) {
        var out = [];

        if ((rule.frequency === 'weekly') && rule.weekdays.length) {
            var anchorWeek = addDays(rule.anchor, 1 - weekday(rule.anchor));
            var cursor = rule.anchor;

            for (var guard = 0; (guard < 800) && (out.length < count); guard++) {
                cursor = addDays(cursor, 1);

                var weeks = Math.round(between(anchorWeek, addDays(cursor, 1 - weekday(cursor))) / 7);

                if ((weeks >= 0) && ((weeks % rule.interval) === 0) && (rule.weekdays.indexOf(weekday(cursor)) !== -1)) {
                    if (rule.end && (cursor > rule.end)) {
                        break;
                    }

                    out.push(cursor);
                }
            }

            return out;
        }

        for (var k = 1; (k < 800) && (out.length < count); k++) {
            var date = nth(rule, k);

            if (rule.end && (date > rule.end)) {
                break;
            }

            out.push(date);
        }

        return out;
    }

    // The next copies one at a time, the way the server hands them out: each
    // counted as done on its due date, a copy whose start falls within the
    // days of the one before is passed over.
    function coming(rule, count, gap, held, department) {
        var out = [];
        var running = held || '';
        var dates = upcoming(rule, 400);

        for (var i = 0; (i < dates.length) && (out.length < count); i++) {
            var copy = copyDates(dates[i], gap, department);

            if (!running || (copy.start > running)) {
                out.push(copy);
                running = copy.due;
            }
        }

        return out;
    }

    function describe(rule) {
        var n = rule.interval;
        var label;

        switch (rule.frequency) {
            case 'daily':
                label = (n === 1) ? text('repeat_daily') : text('repeat_n_days', n);
                break;

            case 'weekly':
                if ((n === 1) && (rule.weekdays.join(',') === WEEKDAYS.join(','))) {
                    label = text('repeat_weekdays');
                    break;
                }

                // One day is named in full, several are listed short.
                var detail = (rule.weekdays.length > 1)
                    ? rule.weekdays.map(function (day) { return (REPEAT.weekdays || {})[day] || ''; }).join(', ')
                    : ((REPEAT.weekday_names || {})[rule.weekdays.length ? rule.weekdays[0] : weekday(rule.anchor)] || '');

                label = (n === 1) ? text('repeat_weekly_on', detail) : text('repeat_n_weeks_on', n, detail);
                break;

            case 'yearly':
                label = (n === 1) ? text('repeat_yearly_on', dayMonth(rule.anchor)) : text('repeat_n_years_on', n, dayMonth(rule.anchor));
                break;

            default:
                var day = parse(rule.anchor).getUTCDate();
                label = (n === 1) ? text('repeat_monthly_on', day) : text('repeat_n_months_on', n, day);
        }

        if (rule.end) {
            label += ' · ' + text('repeat_until_date', dayLabel(rule.end));
        }

        return label;
    }

    var uid = 0;

    /**
     * @param {Object|null} task      the drawer's task detail, null for a new one
     * @param {Object}      defaults  what a new task was opened with
     * @param {boolean}     editable
     * @param {Object}      helpers   api, ask, toast, done (close and refresh), open (another task)
     * @param {Object}      dates     start, due (the date inputs), startRow, dueRow (their form
     *                                rows), department (the department select, whose days off count)
     * @return {{node: HTMLElement, value: function}}
     */
    function field(task, defaults, editable, helpers, dates) {
        var current = (task && task.recurrence) ? task.recurrence : null;
        var active = !!(current && current.active);
        // An earlier copy of a series: the rule is changed on the newest one.
        var older = active && (current.latest === false);
        var id = 'ws-repeat-' + (++uid);
        var start = dates.start;
        var due = dates.due;

        var wrap = el('div', 'ws-when mb-3');
        var head = el('div', 'ws-when-head');
        head.appendChild(icon('bi-calendar-event'));
        head.appendChild(document.createTextNode(text('repeat_when')));
        wrap.appendChild(head);

        var dateRow = el('div', 'row g-2');
        [dates.startRow, dates.dueRow].forEach(function (row) {
            var cell = el('div', 'col-6');
            row.classList.remove('mb-3');
            row.classList.add('mb-2');
            cell.appendChild(row);
            dateRow.appendChild(cell);
        });
        wrap.appendChild(dateRow);

        // A date that is a day off, with the next working day one click away.
        var dayNote = el('div', 'ws-when-dayoff d-none');
        wrap.appendChild(dayNote);

        // ── How it repeats ──

        var label = el('label', 'form-label', text('repeat'));
        label.htmlFor = id + '-choice';
        wrap.appendChild(label);

        var choice = el('select', 'form-select form-select-sm');
        choice.id = id + '-choice';
        var options = {};

        ['none', 'daily', 'weekdays', 'weekly', 'monthly', 'yearly', 'custom'].forEach(function (key) {
            var option = el('option');
            option.value = key;
            options[key] = option;
            choice.appendChild(option);
        });

        wrap.appendChild(choice);

        // Custom: every n days, weeks, months or years, and the weekdays.
        var custom = el('div', 'ws-when-custom d-none');
        var everyGroup = el('div', 'input-group input-group-sm');
        everyGroup.appendChild(el('span', 'input-group-text', text('repeat_every')));

        var interval = el('input', 'form-control');
        interval.type = 'number';
        interval.min = '1';
        interval.max = '99';
        interval.step = '1';
        interval.value = (active && current.interval) ? String(current.interval) : '1';
        interval.setAttribute('aria-label', text('repeat_every'));
        everyGroup.appendChild(interval);

        var unit = el('select', 'form-select');
        unit.setAttribute('aria-label', text('repeat'));

        ['daily', 'weekly', 'monthly', 'yearly'].forEach(function (key) {
            var option = el('option', '', text('repeat_unit_' + key));
            option.value = key;
            unit.appendChild(option);
        });

        unit.value = active ? current.frequency : 'weekly';
        everyGroup.appendChild(unit);
        custom.appendChild(everyGroup);

        var daysBox = el('div', 'mt-2');
        daysBox.appendChild(el('div', 'small text-body-secondary mb-1', text('repeat_on_days')));
        var days = el('div', 'btn-group btn-group-sm flex-wrap');
        days.setAttribute('role', 'group');
        var chosen = (active && current.frequency === 'weekly') ? (current.weekdays || []) : [];

        Object.keys(REPEAT.weekdays || {}).forEach(function (key) {
            var day = parseInt(key, 10);
            var input = el('input', 'btn-check');
            input.type = 'checkbox';
            input.id = id + '-day-' + day;
            input.value = String(day);
            input.checked = chosen.indexOf(day) !== -1;
            input.autocomplete = 'off';

            var toggle = el('label', 'btn btn-outline-secondary', REPEAT.weekdays[key]);
            toggle.htmlFor = input.id;

            days.appendChild(input);
            days.appendChild(toggle);
        });

        daysBox.appendChild(days);
        custom.appendChild(daysBox);
        wrap.appendChild(custom);

        // When the repeat ends: never, or on a date.
        var endBox = el('div', 'ws-when-end d-none');
        var endLabel = el('label', 'form-label', text('repeat_ends'));
        endLabel.htmlFor = id + '-ends';
        endBox.appendChild(endLabel);

        var endGroup = el('div', 'd-flex gap-2');
        var ends = el('select', 'form-select form-select-sm w-auto');
        ends.id = id + '-ends';

        [['never', text('repeat_ends_never')], ['on', text('repeat_ends_on')]].forEach(function (item) {
            var option = el('option', '', item[1]);
            option.value = item[0];
            ends.appendChild(option);
        });

        var until = el('input', 'form-control form-control-sm w-auto');
        until.type = 'date';
        until.setAttribute('aria-label', text('repeat_ends_on'));
        until.value = (active && current.end_date) ? current.end_date : '';
        ends.value = until.value ? 'on' : 'never';

        endGroup.appendChild(ends);
        endGroup.appendChild(until);
        endBox.appendChild(endGroup);
        wrap.appendChild(endBox);

        // What saving will do, in words, and the next dates.
        var summary = el('div', 'ws-when-summary d-none');
        wrap.appendChild(summary);

        var notice = el('div', 'form-text d-none');
        wrap.appendChild(notice);

        // ── The series as it stands ──

        if (current) {
            var state = el('div', 'ws-when-state');

            if (!active) {
                state.appendChild(el('span', 'text-body-secondary', text('repeat_ended') + ' ' + (current.ended_label || '')));
            } else if (older) {
                state.appendChild(el('span', 'text-body-secondary', text('repeat_older_copy', current.latest_number || '')));

                if (helpers && helpers.open && current.last_task_id) {
                    var latest = el('button', 'btn btn-sm btn-ghost');
                    latest.type = 'button';
                    latest.appendChild(icon('bi-box-arrow-up-right'));
                    latest.appendChild(document.createTextNode(' ' + text('repeat_open_latest', current.latest_number || '')));
                    latest.addEventListener('click', function () {
                        helpers.open(current.last_task_id);
                    });
                    state.appendChild(latest);
                }
            }

            if (active && editable && helpers && helpers.api) {
                var finish = el('button', 'btn btn-sm btn-outline-success ms-auto');
                finish.type = 'button';
                finish.textContent = text('repeat_complete');
                finish.addEventListener('click', function () {
                    var confirm = helpers.ask ? helpers.ask(text('repeat_complete_ask'), text('repeat_complete'), false) : Promise.resolve(true);

                    confirm.then(function (yes) {
                        if (!yes) {
                            return;
                        }

                        helpers.api('ws_task_recurrence_end', { task_id: task.id, mode: 'complete' }).then(function () {
                            if (helpers.toast) {
                                helpers.toast(text('repeat_completed'), 'success');
                            }

                            if (helpers.done) {
                                helpers.done();
                            }
                        }).catch(function (error) {
                            if (helpers.toast) {
                                helpers.toast(error.message, 'danger');
                            }
                        });
                    });
                });
                state.appendChild(finish);
            }

            if (state.childNodes.length) {
                wrap.appendChild(state);
            }
        }

        var help = el('div', 'form-text d-none', text('repeat_help'));
        wrap.appendChild(help);

        // ── Reading the form ──

        var initialStart = start.value;
        var initialDue = due.value;

        function department() {
            return (dates.department && parseInt(dates.department.value, 10)) || 0;
        }

        function datesUnchanged() {
            return (start.value === initialStart) && (due.value === initialDue);
        }

        // The day of the rule: the one this copy stands for while its dates
        // are as they were (a copy moved off a day off is due later than its
        // rule says), otherwise the due date as it is now.
        function baseDate() {
            return (active && current.base_date && datesUnchanged()) ? current.base_date : dueDate();
        }

        // How long before its due date a copy starts: the series' own gap
        // for a moved copy left as it is, the dates' gap otherwise.
        function gapDays() {
            if (older || (active && current.moved_from && datesUnchanged())) {
                return current.lead_days || 0;
            }

            return lead();
        }

        function dueDate() {
            return due.value || start.value || REPEAT.today || iso(new Date());
        }

        function lead() {
            return (start.value && due.value && (start.value < due.value)) ? between(start.value, due.value) : 0;
        }

        function picked() {
            var list = [];

            Array.prototype.forEach.call(days.querySelectorAll('input:checked'), function (input) {
                list.push(parseInt(input.value, 10));
            });

            return list;
        }

        function rule() {
            var anchor = baseDate();
            var out = { frequency: 'none', interval: 1, weekdays: [], anchor: anchor, end: (ends.value === 'on') ? until.value : '' };

            switch (choice.value) {
                case 'none':
                    return null;

                case 'weekdays':
                    out.frequency = 'weekly';
                    out.weekdays = WEEKDAYS.slice();
                    break;

                case 'weekly':
                    out.frequency = 'weekly';

                    // A series kept on one weekday that is still the due
                    // date's goes on as it was saved.
                    if (active && (current.frequency === 'weekly') && ((current.weekdays || []).length === 1) && (current.weekdays[0] === weekday(anchor))) {
                        out.weekdays = current.weekdays.slice();
                    }
                    break;

                case 'custom':
                    out.frequency = unit.value;
                    out.interval = Math.max(1, Math.min(99, parseInt(interval.value, 10) || 1));
                    out.weekdays = (unit.value === 'weekly') ? picked() : [];
                    break;

                default:
                    out.frequency = choice.value;
            }

            return out;
        }

        // The choice a saved rule is shown as.
        function presetOf() {
            if (!active) {
                return 'none';
            }

            var list = current.weekdays || [];
            var anchor = baseDate();

            if (current.interval !== 1) {
                return 'custom';
            }

            if (current.frequency === 'weekly') {
                if (list.join(',') === WEEKDAYS.join(',')) {
                    return 'weekdays';
                }

                return (!list.length || ((list.length === 1) && (list[0] === weekday(anchor)))) ? 'weekly' : 'custom';
            }

            return current.frequency;
        }

        function snapshot() {
            return JSON.stringify([start.value, due.value, rule()]);
        }

        choice.value = presetOf();
        var saved = snapshot();
        var filled = '';
        var last = choice.value;

        // ── Drawing ──

        function relabel() {
            var anchor = baseDate();

            options.none.textContent = text('repeat_none');
            options.daily.textContent = text('repeat_daily');
            options.weekdays.textContent = text('repeat_weekdays');
            options.weekly.textContent = text('repeat_weekly_on', (REPEAT.weekday_names || {})[weekday(anchor)] || '');
            options.monthly.textContent = text('repeat_monthly_on', parse(anchor).getUTCDate());
            options.yearly.textContent = text('repeat_yearly_on', dayMonth(anchor));
            options.custom.textContent = text('repeat_custom');
        }

        function dayOffNote() {
            dayNote.textContent = '';

            var dept = department();
            var shown = false;

            [due, start].forEach(function (input) {
                var value = input.value;
                var reason = value ? dayOff(value, dept) : '';

                // A copy the calendar moved says so instead.
                if (!reason || shown) {
                    return;
                }

                shown = true;

                var line = el('div', 'ws-when-warn');
                line.appendChild(icon('bi-calendar-x'));
                line.appendChild(document.createTextNode(' ' + ((OFF[value] || []).length && (reason !== (REPEAT.weekday_names || {})[weekday(value)])
                    ? text('day_off_holiday', dayLabel(value), reason)
                    : text('day_off_week', reason, dayLabel(value)))));

                if (editable) {
                    var target = nextWorkday(value, dept);

                    if (target !== value) {
                        var move = el('button', 'btn btn-sm btn-link p-0 ms-1 align-baseline');
                        move.type = 'button';
                        move.textContent = text('move_to_workday', ((REPEAT.weekday_names || {})[weekday(target)] || '') + ' ' + dayLabel(target));
                        move.addEventListener('click', function () {
                            input.value = target;

                            // The start never after the due date.
                            if ((input === due) && start.value && (start.value > target)) {
                                start.value = target;
                            }

                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                        line.appendChild(move);
                    }
                }

                dayNote.appendChild(line);
            });

            // Moved off a day off by the calendar, or by hand on the board.
            if (!shown && active && current.moved_from && datesUnchanged()) {
                var moved = el('div', 'form-text mt-0');
                moved.appendChild(icon('bi-arrow-right-short'));
                moved.appendChild(document.createTextNode(' ' + text(dayOff(current.moved_from, dept) ? 'repeat_moved' : 'repeat_moved_hand', ((REPEAT.weekday_names || {})[weekday(current.moved_from)] || '') + ' ' + dayLabel(current.moved_from))));
                dayNote.appendChild(moved);
                shown = true;
            }

            dayNote.classList.toggle('d-none', !shown);
        }

        function refresh() {
            relabel();
            dayOffNote();

            var shape = rule();
            var on = !!shape;

            custom.classList.toggle('d-none', !on || (choice.value !== 'custom') || older);
            daysBox.classList.toggle('d-none', unit.value !== 'weekly');
            endBox.classList.toggle('d-none', !on || older);
            until.classList.toggle('d-none', ends.value !== 'on');
            summary.classList.toggle('d-none', !on);
            help.classList.toggle('d-none', !on);

            var notes = [];

            if (filled) {
                notes.push(text('repeat_due_filled', dayLabel(filled)));
            }

            if (active && !older && on) {
                notes.push(text('repeat_follows_date'));
            }

            notice.textContent = notes.join(' ');
            notice.classList.toggle('d-none', !notes.length);

            summary.textContent = '';

            if (!on) {
                return;
            }

            // Unchanged since the drawer opened, or an earlier copy whose
            // dates say nothing about the series: the server's own words.
            var unchanged = older || (active && (snapshot() === saved));
            var line = el('div', 'ws-when-rule');
            line.appendChild(icon('bi-arrow-repeat'));
            line.appendChild(document.createTextNode(' ' + (unchanged && current.label ? current.label : describe(shape))));
            summary.appendChild(line);

            var gap = gapDays();
            summary.appendChild(el('div', 'ws-when-span', gap > 0 ? text('repeat_opens_start', gap + 1) : text('repeat_opens_due')));

            // No copy comes within this task's days, done or not.
            var held = due.value || '';
            var next = [];

            if (unchanged && current.upcoming && current.upcoming.length) {
                next = current.upcoming.slice();
            } else {
                next = coming(shape, 3, gap, held, department());
            }

            if (next.length) {
                var list = el('div', 'ws-when-next');
                list.appendChild(el('span', 'text-body-secondary', text('repeat_upcoming')));

                var anyMoved = false;

                next.forEach(function (item) {
                    var chip = el('span', 'ws-when-date' + (item.moved ? ' ws-when-moved' : ''));

                    if (item.moved) {
                        anyMoved = true;
                        chip.appendChild(icon('bi-arrow-right-short'));
                        chip.title = text('moved_from', ((REPEAT.weekday_names || {})[weekday(item.date)] || '') + ' ' + dayLabel(item.date));
                    }

                    chip.appendChild(document.createTextNode(rangeLabel(item.start, item.due)));
                    list.appendChild(chip);
                });

                summary.appendChild(list);

                if (anyMoved) {
                    summary.appendChild(el('div', 'ws-when-span', text('repeat_moved_note')));
                }
            }

            // The newest copy is overdue: the next ones wait for it.
            if (!older && active && current.late && datesUnchanged()) {
                var late = el('div', 'ws-when-warn');
                late.appendChild(icon('bi-hourglass-split'));
                late.appendChild(document.createTextNode(' ' + text('repeat_late')));
                summary.appendChild(late);
            }

            // A copy that lasts longer than the step between copies: the
            // dates in between are passed over. Usually a date typed a year
            // off.
            var first = upcoming(shape, 1)[0];

            if (!older && (gap > 0) && first && due.value && (addDays(first, -gap) <= due.value)) {
                var warn = el('div', 'ws-when-warn');
                warn.appendChild(icon('bi-exclamation-triangle'));
                warn.appendChild(document.createTextNode(' ' + text('repeat_overlap', gap + 1)));
                summary.appendChild(warn);
            }
        }

        // A repeat counts from the due date: turning one on without it
        // writes one in, where it can be seen and changed.
        choice.addEventListener('change', function () {
            if ((choice.value !== 'none') && !due.value) {
                due.value = start.value || REPEAT.today || iso(new Date());
                filled = due.value;
                due.dispatchEvent(new Event('change', { bubbles: true }));
            }

            // Custom starts from the choice it replaces: "every week on
            // Wednesday" becomes every 1 week with Wednesday ticked.
            if (choice.value === 'custom') {
                var from = { daily: 'daily', weekdays: 'weekly', weekly: 'weekly', monthly: 'monthly', yearly: 'yearly' }[last];

                if (from) {
                    unit.value = from;
                    interval.value = '1';
                }

                if ((unit.value === 'weekly') && !picked().length) {
                    (last === 'weekdays' ? WEEKDAYS : [weekday(dueDate())]).forEach(function (day) {
                        var box = days.querySelector('input[value="' + day + '"]');

                        if (box) {
                            box.checked = true;
                        }
                    });
                }
            }

            last = choice.value;
            refresh();
        });

        ends.addEventListener('change', function () {
            if (ends.value === 'on' && !until.value) {
                until.focus();
            }

            refresh();
        });

        [start, due, interval, until].forEach(function (input) {
            input.addEventListener('input', refresh);
            input.addEventListener('change', refresh);
        });

        if (dates.department) {
            dates.department.addEventListener('change', refresh);
        }

        unit.addEventListener('change', function () {
            if ((unit.value === 'weekly') && !picked().length) {
                var box = days.querySelector('input[value="' + weekday(dueDate()) + '"]');

                if (box) {
                    box.checked = true;
                }
            }

            refresh();
        });
        days.addEventListener('change', refresh);

        // An earlier copy shows the series as it is and where to change it,
        // without a choice that could not be saved.
        if (older) {
            choice.disabled = true;
            label.classList.add('d-none');
            choice.classList.add('d-none');
        }

        if (!editable) {
            Array.prototype.forEach.call(wrap.querySelectorAll('select, input'), function (input) {
                input.disabled = true;
            });
        }

        refresh();

        return {
            node: wrap,
            value: function () {
                if (older) {
                    return null;
                }

                var out = rule();

                if (!out) {
                    return { frequency: 'none' };
                }

                return {
                    frequency: out.frequency,
                    interval: out.interval,
                    weekdays: out.weekdays,
                    end_date: out.end || ''
                };
            }
        };
    }

    window.PGWsRecurrence = { field: field };
})();
