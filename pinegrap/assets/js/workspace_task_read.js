/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a task opened to read in the task drawer, and New task's short
 * form.
 *
 * taskDrawer.render() of assets/js/workspace.js builds today's form and then
 * hands it here with its helpers (readHelpers()):
 *   PGWsTaskRead.page(drawer, task, helpers)
 *     An existing task: the form is kept hidden and a page is drawn over it:
 *     the number and the status control in the head, the title changed in
 *     place, one value row per field, the description as it reads with its
 *     checklist ticked in place, then the parts folded under their names.
 *     A row opens its own picker under its value, writes the hidden form's
 *     field and saves the whole task with ws_task_save, as the form did, so
 *     the checks, the clashes asked about and the store's refusals stay the
 *     form's. The page is then drawn again from the stored task; a refusal
 *     puts the old value back and says the store's sentence under the row.
 *   PGWsTaskRead.newForm(drawer, body, helpers)
 *     New task: Title, Description, Responsible people, Due beside Task
 *     priority; every other field folded under "More".
 *
 * On a phone a picker is a sheet from the bottom edge and a Back arrow takes
 * Close's place (backend.src.css, .ws-tr). Served minified inside
 * assets/js/workspace.min.js (pg_js_bundles(), includes/fn/output.php);
 * after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var STATUS_TONES = { todo: 'gray', doing: 'blue', waiting: 'amber', done: 'teal', cancelled: 'rose' };
    var PRIORITY_TONES = { low: 'gray', normal: 'gray', high: 'amber', urgent: 'rose' };
    var LANG = (document.documentElement && document.documentElement.lang) || undefined;

    // What the next drawing of the page shows (a saved note, a refusal, the
    // focus): set just before the drawer draws itself again.
    var next = null;
    // The parts left open, kept while the same task is drawn again.
    var opened = { task: 0, keys: {} };
    // The open picker: { box, scrim, anchor, opener, onClose }.
    var picker = null;

    // ── Days (pure) ──────────────────────────────────────────────────────

    // A date counted in UTC as YYYY-MM-DD.
    function isoDay(date) {
        return date.toISOString().slice(0, 10);
    }

    // The day so many days after (before, when negative) a YYYY-MM-DD.
    function shiftDay(iso, days) {
        var part = String(iso).split('-');

        return isoDay(new Date(Date.UTC(+part[0], +part[1] - 1, +part[2] + days)));
    }

    // The 42 days of a month's sheet, Monday first; month counts from 0.
    function monthDays(year, month) {
        var lead = (new Date(Date.UTC(year, month, 1)).getUTCDay() + 6) % 7;
        var days = [];

        for (var i = 0; i < 42; i++) {
            var date = new Date(Date.UTC(year, month, 1 - lead + i));

            days.push({ iso: isoDay(date), day: date.getUTCDate(), inMonth: date.getUTCMonth() === month });
        }

        return days;
    }

    function localToday() {
        var now = new Date();

        return isoDay(new Date(Date.UTC(now.getFullYear(), now.getMonth(), now.getDate())));
    }

    function dateName(iso, options) {
        try {
            return new Date(iso + 'T00:00:00Z').toLocaleDateString(LANG, Object.assign({ timeZone: 'UTC' }, options));
        } catch (error) {
            return iso;
        }
    }

    // "Mon 16.11", as the list's rows write a day.
    function dayText(iso) {
        var part = String(iso).split('-');

        return dateName(iso, { weekday: 'short' }) + ' ' + part[2] + '.' + part[1];
    }

    // ── The picker under a value ─────────────────────────────────────────

    function closePicker(keepFocus) {
        if (!picker) {
            return;
        }

        var open = picker;

        picker = null;
        document.removeEventListener('mousedown', outside, true);
        open.box.remove();
        open.scrim.remove();
        open.anchor.classList.remove('is-open');
        open.opener.setAttribute('aria-expanded', 'false');

        if (open.onClose) {
            open.onClose();
        } else if (!keepFocus && document.body.contains(open.opener)) {
            open.opener.focus();
        }
    }

    function outside(event) {
        if (picker && !picker.box.contains(event.target) && !picker.opener.contains(event.target)) {
            closePicker(true);
        }
    }

    // Esc closes the picker and leaves the drawer open; the arrows move
    // between a menu's rows.
    function pickerKeys(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            closePicker();
            return;
        }

        if ((event.key !== 'ArrowDown') && (event.key !== 'ArrowUp')) {
            return;
        }

        var items = Array.prototype.slice.call(picker.box.querySelectorAll('[data-ws-tr-opt]'));

        if (!items.length || event.target.closest('.ws-tr-month')) {
            return;
        }

        var at = items.indexOf(document.activeElement);

        event.preventDefault();
        items[(event.key === 'ArrowDown') ? Math.min(at + 1, items.length - 1) : Math.max(at - 1, 0)].focus();
    }

    function openPicker(ctx, anchor, opener, fill, wide) {
        closePicker(true);

        var box = ctx.h.el('div', 'ws-tr-pick' + (wide ? ' is-wide' : ''));
        var scrim = ctx.h.el('div', 'ws-tr-scrim');

        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-label', opener.getAttribute('data-ws-tr-name') || '');
        anchor.appendChild(scrim);
        anchor.appendChild(box);
        anchor.classList.add('is-open');
        opener.setAttribute('aria-expanded', 'true');
        picker = { box: box, scrim: scrim, anchor: anchor, opener: opener, onClose: null };
        fill(box, picker);
        box.addEventListener('keydown', pickerKeys);
        scrim.addEventListener('click', function () { closePicker(); });
        document.addEventListener('mousedown', outside, true);

        var first = box.querySelector('input, [data-ws-tr-opt].is-on, [data-ws-tr-day][tabindex="0"], [data-ws-tr-opt], button');

        if (first) {
            first.focus();
        }
    }

    // A menu row: a dot or a pill, the words, a check on the current one.
    function option(ctx, content, on, choose) {
        var row = ctx.h.el('button', 'ws-tr-opt' + (on ? ' is-on' : ''));

        row.type = 'button';
        row.setAttribute('data-ws-tr-opt', '');
        row.setAttribute('aria-pressed', on ? 'true' : 'false');
        row.appendChild(content);
        row.appendChild(ctx.h.icon('bi-check-lg', 'ws-tr-opt-check'));
        row.addEventListener('click', choose);

        return row;
    }

    function dotText(ctx, tone, text) {
        var span = ctx.h.el('span', 'ws-tr-dot pg-tone-' + tone);

        span.appendChild(document.createTextNode(text));

        return span;
    }

    function pill(ctx, key) {
        var label = (ctx.h.boot().priorities || {})[key] || key;

        return ctx.h.el('span', 'pg-pill pg-tone-' + (PRIORITY_TONES[key] || 'gray'), label);
    }

    // ── Saving one value ─────────────────────────────────────────────────

    function redraw(ctx, task, state) {
        state.scroll = ctx.body.scrollTop;
        next = state;
        closePicker(true);
        ctx.drawer.render(task, {});
    }

    // The row shows its new value, quiet, beside a small spinner.
    function busy(ctx, key, shown) {
        var wrap = ctx.body.querySelector('[data-ws-tr-row="' + key + '"]');
        var value = wrap && wrap.querySelector('.ws-tr-v');

        if (!value) {
            return;
        }

        if (shown !== undefined) {
            ctx.h.clear(value).appendChild(document.createTextNode(shown));
        }

        value.className = 'ws-tr-v is-busy';
        value.appendChild(ctx.h.el('span', 'ws-tr-spin'));
    }

    // Saves the hidden form as it stands, the form's way, after one of its
    // fields was written; draws the page again from the stored task.
    function saveValue(ctx, key, shown, force) {
        var h = ctx.h;
        var task = ctx.task;
        var data = ctx.drawer.values();

        if (data.title === '') {
            redraw(ctx, task, { why: { key: key, text: h.t('title_required') }, focus: key });
            return;
        }

        data.force = force ? 1 : 0;
        busy(ctx, key, shown);

        h.api('ws_task_save', data).then(function (result) {
            if (result.needs_confirm) {
                if (result.check.hard && !result.check.may_override) {
                    h.ask(h.t('leave_blocks'), h.t('ok'), false, h.warningList(result.check));
                    redraw(ctx, task, { focus: key });
                    return;
                }

                h.ask(h.t('save_despite'), h.t('save_anyway'), true, h.warningList(result.check)).then(function (yes) {
                    if (yes) {
                        saveValue(ctx, key, shown, true);
                    } else {
                        redraw(ctx, task, { focus: key });
                    }
                });
                return;
            }

            return h.api('ws_task_get', { task_id: task.id }).then(function (got) {
                redraw(ctx, got.task, { saved: true, focus: key, reopen: ctx.reopen || '', search: ctx.search || '' });
                ctx.drawer.changed();
            });
        }).catch(function (error) {
            redraw(ctx, task, { why: { key: key, text: (error && error.message) ? error.message : h.t('error_generic') }, focus: key });
        });
    }

    // ── The head ─────────────────────────────────────────────────────────

    function reset(node) {
        node.classList.remove('ws-tr');
        Array.prototype.forEach.call(node.querySelectorAll('.ws-tr-head'), function (item) { item.remove(); });
    }

    function head(ctx) {
        var h = ctx.h;
        var task = ctx.task;
        var header = ctx.drawer.node.querySelector('.drawer-header');
        var title = header.querySelector('.drawer-title');
        var close = header.querySelector('.btn-close');
        var back = h.button('pg-icon-button ws-tr-head ws-tr-back', '', 'bi-arrow-left', h.t('tr_back'));

        back.setAttribute('data-bs-dismiss', 'drawer');
        header.insertBefore(back, title);
        title.textContent = task.number;

        var anchor = h.el('span', 'ws-tr-head ws-tr-anchor');
        var control = h.el('button', 'ws-tr-status pg-tone-' + (STATUS_TONES[task.status] || 'gray'));

        control.type = 'button';
        control.setAttribute('aria-haspopup', 'dialog');
        control.setAttribute('aria-expanded', 'false');
        control.setAttribute('data-ws-tr-key', 'status');
        control.setAttribute('data-ws-tr-name', h.t('tr_status'));
        control.appendChild(h.el('span', 'ws-tr-status-dot'));
        control.appendChild(document.createTextNode(task.status_label));
        control.appendChild(h.icon('bi-chevron-down', 'ws-tr-caret'));
        control.disabled = !ctx.editable;
        control.addEventListener('click', function () {
            if (picker && picker.opener === control) {
                closePicker();
                return;
            }

            openPicker(ctx, anchor, control, function (box) { statusMenu(ctx, box); });
        });
        anchor.appendChild(control);
        title.after(anchor);

        var end = h.el('span', 'ws-tr-head ws-tr-head-end');

        if (ctx.state.saved) {
            var saved = h.el('span', 'ws-tr-saved');

            saved.setAttribute('role', 'status');
            saved.appendChild(h.icon('bi-check2'));
            saved.appendChild(document.createTextNode(h.t('tr_saved')));
            end.appendChild(saved);
        }

        // Today's task menu: copy the number or the link, put it off.
        var more = h.button('pg-icon-button', '', 'bi-three-dots', h.t('tr_more_actions'));

        more.addEventListener('click', function (event) {
            var box = more.getBoundingClientRect();

            event.stopPropagation();
            h.ctxMenu(Math.max(8, box.right - 220), box.bottom + 4, h.taskMenu(task, function () {
                ctx.drawer.open(task.id, null, ctx.drawer.onSaved);
                ctx.drawer.changed();
            }));
        });
        end.appendChild(more);
        header.insertBefore(end, close);
    }

    // The five statuses with their dots; a finished task offers "Open it
    // again" first. The drawer's own setStatus() asks what it asked before.
    function statusMenu(ctx, box) {
        var h = ctx.h;
        var task = ctx.task;
        var statuses = h.boot().statuses || {};

        if (!task.open) {
            var reopen = h.el('span', 'ws-tr-opt-text');

            reopen.appendChild(h.icon('bi-arrow-counterclockwise'));
            reopen.appendChild(document.createTextNode(h.t('reopen')));
            box.appendChild(option(ctx, reopen, false, function () {
                closePicker(true);
                ctx.drawer.setStatus('todo');
            }));
        }

        Object.keys(statuses).forEach(function (key) {
            box.appendChild(option(ctx, dotText(ctx, STATUS_TONES[key] || 'gray', statuses[key]), key === task.status, function () {
                closePicker();

                if (key !== task.status) {
                    ctx.drawer.setStatus(key);
                }
            }));
        });
    }

    // ── The title ────────────────────────────────────────────────────────

    function titlePart(ctx) {
        var h = ctx.h;
        var task = ctx.task;
        var wrap = h.el('div', 'ws-tr-title-wrap');

        wrap.setAttribute('data-ws-tr-row', 'title');

        if (!ctx.editable) {
            wrap.appendChild(h.el('h2', 'ws-tr-title', task.title));
            return wrap;
        }

        var area = h.el('textarea', 'ws-tr-title');
        var done = false;

        area.rows = 1;
        area.maxLength = 255;
        area.value = task.title;
        area.setAttribute('aria-label', h.t('title'));
        area.setAttribute('data-ws-tr-key', 'title');

        function fit() {
            area.style.height = 'auto';
            area.style.height = area.scrollHeight + 'px';
        }

        function commit() {
            var value = area.value.replace(/\s+/g, ' ').trim();

            if (done || (value === task.title)) {
                return;
            }

            done = true;
            ctx.drawer.fields.title.value = value;
            saveValue(ctx, 'title');
        }

        area.addEventListener('input', fit);
        area.addEventListener('blur', commit);
        area.addEventListener('keydown', function (event) {
            if ((event.key === 'Enter') && !event.isComposing) {
                event.preventDefault();
                commit();
            } else if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                area.value = task.title;
                fit();
                area.blur();
            }
        });

        ctx.fit = fit;
        wrap.appendChild(area);
        wrap.appendChild(h.el('div', 'ws-tr-hint', h.t('tr_title_hint')));
        why(ctx, wrap, 'title', area);

        return wrap;
    }

    // The store's sentence under a row it refused.
    function why(ctx, wrap, key, field) {
        if (!ctx.state.why || (ctx.state.why.key !== key)) {
            return;
        }

        var line = ctx.h.el('div', 'ws-tr-why');

        line.id = ctx.h.nextId('ws-tr-why-');
        line.appendChild(ctx.h.icon('bi-exclamation-circle'));
        line.appendChild(ctx.h.el('span', '', ctx.state.why.text));
        wrap.appendChild(line);

        if (field) {
            field.setAttribute('aria-describedby', line.id);
        }
    }

    // ── The value rows ───────────────────────────────────────────────────

    // One row: the label, the value, and when it may be changed a pencil
    // that turns into a chevron while its picker is open.
    function valueRow(ctx, key, label, value, opts) {
        var h = ctx.h;
        var wrap = h.el('div', 'ws-tr-row-wrap');
        var pick = ctx.editable && opts.pick;
        var row = h.el(pick ? 'button' : 'div', 'ws-tr-row');
        var shown = h.el('span', 'ws-tr-v' + (opts.empty ? ' is-none' : '') + (opts.late ? ' is-late' : ''));

        wrap.setAttribute('data-ws-tr-row', key);
        row.appendChild(h.el('span', 'ws-tr-k', label));
        shown.appendChild((typeof value === 'string') ? document.createTextNode(value) : value);
        row.appendChild(shown);
        wrap.appendChild(row);

        if (pick) {
            row.type = 'button';
            row.setAttribute('aria-haspopup', 'dialog');
            row.setAttribute('aria-expanded', 'false');
            row.setAttribute('data-ws-tr-key', key);
            row.setAttribute('data-ws-tr-name', label);
            row.appendChild(h.icon('bi-pencil', 'ws-tr-pen'));
            row.appendChild(h.icon('bi-chevron-up', 'ws-tr-chev'));
            row.addEventListener('click', function () {
                if (picker && picker.opener === row) {
                    closePicker();
                    return;
                }

                ctx.reopen = '';
                openPicker(ctx, wrap, row, function (box, open) { opts.pick(box, open); }, opts.wide);
            });
        }

        why(ctx, wrap, key, pick ? row : null);

        return wrap;
    }

    function peopleValue(ctx) {
        var h = ctx.h;
        var list = h.el('span', 'ws-tr-people');

        ctx.task.assignees.forEach(function (person) {
            var who = h.el('span', 'ws-tr-person');

            who.appendChild(h.avatar(person));
            who.appendChild(document.createTextNode(person.name));
            list.appendChild(who);
        });

        return list;
    }

    // Everyone the reader may hand the task to, with a search; a person
    // ticked or unticked is saved at once and the list stays open.
    function pickPeople(ctx, box) {
        var h = ctx.h;
        var field = ctx.drawer.fields.people;
        var allowed = ctx.drawer.allowed;
        var people = (h.boot().people || []).filter(function (member) { return !allowed || allowed.indexOf(member.id) !== -1; });
        var search = h.el('input', 'pg-input pg-input-sm ws-tr-search');
        var list = h.el('div', 'ws-tr-options');

        search.type = 'search';
        search.placeholder = h.t('tr_search_people');
        search.setAttribute('aria-label', h.t('tr_search_people'));
        search.value = ctx.state.search || '';

        function draw() {
            var words = search.value.trim().toLowerCase();
            var chosen = field.value();

            h.clear(list);
            people.forEach(function (member) {
                if (words && member.name.toLowerCase().indexOf(words) === -1) {
                    return;
                }

                var face = h.el('span', 'ws-tr-person');

                face.appendChild(h.avatar(member));
                face.appendChild(document.createTextNode(member.name));
                list.appendChild(option(ctx, face, chosen.indexOf(member.id) !== -1, function () {
                    var ids = field.value();
                    var at = ids.indexOf(member.id);

                    if (at === -1) {
                        ids.push(member.id);
                    } else {
                        ids.splice(at, 1);
                    }

                    field.set(ids);
                    ctx.reopen = 'people';
                    ctx.search = search.value;
                    saveValue(ctx, 'people');
                }));
            });
        }

        search.addEventListener('input', draw);
        box.appendChild(search);
        box.appendChild(list);
        draw();
    }

    // The month with its days; Due adds the due time; "Clear" empties it.
    // The arrows move a day and a week, Enter chooses.
    function pickDate(ctx, box, key) {
        var h = ctx.h;
        var input = ctx.drawer.fields[key];
        var remind = ctx.drawer.fields.remind;
        var today = localToday();
        var focus = input.value || today;
        var view = focus.split('-').map(Number);
        var sheet = h.el('div', 'ws-tr-month');

        function choose(iso) {
            input.value = iso;
            h.dateShown(input);
            input.dispatchEvent(new Event('change'));
            closePicker(true);
            saveValue(ctx, key, iso ? dayText(iso) : '');
        }

        function draw() {
            var top = h.el('div', 'ws-tr-month-head');
            var grid = h.el('div', 'ws-tr-days');
            var prev = h.button('pg-icon-button pg-icon-button-sm', '', 'bi-chevron-left', h.t('tr_prev_month'));
            var later = h.button('pg-icon-button pg-icon-button-sm', '', 'bi-chevron-right', h.t('tr_next_month'));
            var first = view[0] + '-' + (view[1] < 10 ? '0' : '') + view[1] + '-01';

            h.clear(sheet);
            top.appendChild(h.el('b', '', dateName(first, { month: 'long', year: 'numeric' })));
            prev.addEventListener('click', function () { move(shiftDay(first, -1).slice(0, 8) + '01'); });
            later.addEventListener('click', function () { move(shiftDay(first, 32).slice(0, 8) + '01'); });
            top.appendChild(prev);
            top.appendChild(later);
            sheet.appendChild(top);
            grid.setAttribute('role', 'grid');

            monthDays(view[0], view[1] - 1).forEach(function (day, index) {
                if (index < 7) {
                    grid.appendChild(h.el('span', 'ws-tr-wd', dateName(day.iso, { weekday: 'short' }).slice(0, 2)));
                }
            });

            monthDays(view[0], view[1] - 1).forEach(function (day) {
                var cell = h.el('button', 'ws-tr-day' + (day.inMonth ? '' : ' is-out') + (day.iso === input.value ? ' is-on' : '') + (day.iso === today ? ' is-today' : ''), String(day.day));

                cell.type = 'button';
                cell.tabIndex = (day.iso === focus) ? 0 : -1;
                cell.setAttribute('data-ws-tr-day', day.iso);
                cell.setAttribute('aria-label', dateName(day.iso, { dateStyle: 'full' }));
                cell.setAttribute('aria-pressed', (day.iso === input.value) ? 'true' : 'false');
                cell.addEventListener('click', function () { choose(day.iso); });
                grid.appendChild(cell);
            });

            sheet.appendChild(grid);
        }

        // Shows the month holding a day and puts the focus on that day.
        function move(iso) {
            focus = iso;
            view = iso.split('-').map(Number);
            draw();

            var cell = sheet.querySelector('[data-ws-tr-day="' + iso + '"]');

            if (cell) {
                cell.focus();
            }
        }

        sheet.addEventListener('keydown', function (event) {
            var steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
            var day = event.target.getAttribute && event.target.getAttribute('data-ws-tr-day');

            if (day && steps[event.key]) {
                event.preventDefault();
                move(shiftDay(day, steps[event.key]));
            }
        });

        draw();
        box.appendChild(sheet);

        var foot = h.el('div', 'ws-tr-pick-foot');

        // The time on the due date lives in the reminder box of the form.
        if ((key === 'due') && remind && remind.time) {
            var time = h.dateField(h.el('input', 'pg-input pg-input-sm ws-tr-time'), 'time');

            time.id = h.nextId('ws-tr-time-');
            time.value = remind.time.value;
            foot.appendChild(h.el('label', 'ws-tr-time-label', h.t('tr_due_time')));
            foot.lastChild.htmlFor = time.id;
            foot.appendChild(time);
            time.addEventListener('change', function () {
                remind.time.value = time.value;
                remind.time.dispatchEvent(new Event('change'));

                if (input.value) {
                    closePicker(true);
                    saveValue(ctx, key, dayText(input.value) + (time.value ? ' ' + time.value : ''));
                }
            });
        }

        if (input.value) {
            var clearIt = h.button('pg-button pg-button-ghost pg-button-sm ws-tr-clear', h.t('tr_clear'));

            clearIt.addEventListener('click', function () { choose(''); });
            foot.appendChild(clearIt);
        }

        if (foot.firstChild) {
            box.appendChild(foot);
        }
    }

    function pickMenu(ctx, box, items, current, write) {
        items.forEach(function (item) {
            box.appendChild(option(ctx, item.node, String(item.key) === String(current), function () {
                closePicker(true);

                if (String(item.key) !== String(current)) {
                    write(item.key);
                    saveValue(ctx, item.save, item.text);
                }
            }));
        });
    }

    // A box of the form shown under its row (Repeat, Remind) with "Save" and
    // "Cancel"; closed without saving, the page is drawn again as stored, so
    // nothing half-changed rides along with the next value saved.
    function pickBox(ctx, box, open, key, part) {
        var h = ctx.h;
        var home = part.parentNode;
        var foot = h.el('div', 'ws-tr-pick-foot');
        var cancel = h.button('pg-button pg-button-ghost pg-button-sm', h.t('cancel'));
        var save = h.button('pg-button pg-button-fill pg-button-sm', h.t('save'), 'bi-check2');

        part.classList.add('ws-tr-hosted');
        box.appendChild(part);
        cancel.addEventListener('click', function () { closePicker(); });
        save.addEventListener('click', function () {
            open.onClose = null;
            closePicker(true);
            saveValue(ctx, key);
        });
        foot.appendChild(h.el('span', 'ws-grow'));
        foot.appendChild(cancel);
        foot.appendChild(save);
        box.appendChild(foot);
        open.onClose = function () {
            part.classList.remove('ws-tr-hosted');
            home.appendChild(part);
            redraw(ctx, ctx.task, { focus: key });
        };
    }

    function pickEstimate(ctx, box) {
        var h = ctx.h;
        var field = ctx.drawer.fields.estimate;
        var hours = h.el('input', 'pg-input pg-input-sm');
        var save = h.button('pg-button pg-button-fill pg-button-sm', h.t('save'), 'bi-check2');
        var row = h.el('div', 'ws-tr-pick-foot');

        hours.type = 'number';
        hours.min = '0';
        hours.step = '0.25';
        hours.value = field.value;
        hours.setAttribute('aria-label', h.t('estimate_hours'));

        function write() {
            field.value = hours.value;
            closePicker(true);
            saveValue(ctx, 'estimate', hours.value);
        }

        hours.addEventListener('keydown', function (event) {
            if ((event.key === 'Enter') && !event.isComposing) {
                event.preventDefault();
                write();
            }
        });
        save.addEventListener('click', write);
        box.appendChild(h.el('div', 'ws-tr-pick-label', h.t('estimate_hours')));
        row.appendChild(hours);
        row.appendChild(save);
        box.appendChild(row);
        box.appendChild(h.el('div', 'pg-text-meta', h.t('estimate_help')));
    }

    function rows(ctx) {
        var h = ctx.h;
        var t = h.t;
        var task = ctx.task;
        var f = ctx.drawer.fields;
        var list = h.el('div', 'ws-tr-rows');
        var boot = h.boot();

        list.appendChild(valueRow(ctx, 'people', t('people'), task.assignees.length ? peopleValue(ctx) : t('tr_none'), {
            empty: !task.assignees.length,
            pick: function (box) { pickPeople(ctx, box); }
        }));

        list.appendChild(valueRow(ctx, 'start', t('start_date'), task.start_date ? dayText(task.start_date) : t('tr_no_date'), {
            empty: !task.start_date,
            pick: function (box) { pickDate(ctx, box, 'start'); }
        }));

        list.appendChild(valueRow(ctx, 'due', t('due_date'), task.due_date ? task.due_label : t('tr_no_date'), {
            empty: !task.due_date,
            late: task.overdue,
            pick: function (box) { pickDate(ctx, box, 'due'); }
        }));

        list.appendChild(valueRow(ctx, 'priority', t('priority'), pill(ctx, task.priority), {
            pick: function (box) {
                pickMenu(ctx, box, Object.keys(boot.priorities || {}).map(function (key) {
                    return { key: key, node: pill(ctx, key), save: 'priority', text: boot.priorities[key] };
                }), f.priority.value, function (key) { f.priority.value = key; });
            }
        }));

        var departments = [{ key: 0, node: ctx.h.el('span', '', t('no_department')), save: 'department', text: t('no_department') }];

        (boot.departments || []).forEach(function (item) {
            departments.push({ key: item.id, node: dotText(ctx, 'gray', item.name), save: 'department', text: item.name, color: item.color });
        });

        var deptValue = task.department ? dotText(ctx, 'gray', task.department.name) : t('no_department');

        if (task.department && task.department.color) {
            deptValue.style.setProperty('--pg-tone', task.department.color);
        }

        list.appendChild(valueRow(ctx, 'department', t('department'), deptValue, {
            empty: !task.department,
            pick: function (box) {
                pickMenu(ctx, box, departments.map(function (item) {
                    if (item.color) {
                        item.node.style.setProperty('--pg-tone', item.color);
                    }

                    return item;
                }), f.department.value, function (key) {
                    f.department.value = String(key);
                    f.department.dispatchEvent(new Event('change'));
                });
            }
        }));

        // A task's channel is not changed: the row is a link to it.
        var channel = t('tr_none');

        if (task.channel) {
            channel = h.el('a', 'ws-tr-link', '#' + task.channel.name);
            channel.href = task.channel.url;
        }

        list.appendChild(valueRow(ctx, 'channel', t('tr_channel'), channel, { empty: !task.channel }));

        // The repeat in the words of its box; an earlier copy of a series
        // shows it and is changed on the newest one.
        if (f.repeat) {
            var choice = f.repeat.node.querySelector('select');
            var repeatText = (choice && choice.value !== 'none' && choice.selectedIndex >= 0) ? choice.options[choice.selectedIndex].text : t('tr_no_repeat');

            list.appendChild(valueRow(ctx, 'repeat', t('tr_repeat'), repeatText, {
                empty: !choice || choice.value === 'none',
                wide: true,
                pick: (f.repeat.value() === null) ? null : function (box, open) { pickBox(ctx, box, open, 'repeat', f.repeat.node); }
            }));
        }

        if (f.remind) {
            list.appendChild(valueRow(ctx, 'remind', t('tr_remind'), task.remind_label || t('tr_none'), {
                empty: !task.remind_label,
                wide: true,
                pick: function (box, open) { pickBox(ctx, box, open, 'remind', f.remind.node); }
            }));
        }

        list.appendChild(valueRow(ctx, 'estimate', t('tr_estimate'), task.estimate_label || t('tr_none'), {
            empty: !task.estimate_label,
            pick: function (box) { pickEstimate(ctx, box); }
        }));

        return list;
    }

    // ── The description and its checklist ────────────────────────────────

    function description(ctx) {
        var h = ctx.h;
        var task = ctx.task;
        var part = h.el('section', 'ws-tr-desc');
        var label = h.el('div', 'ws-tr-label');
        var text = h.el('div', 'ws-tr-desc-text ws-msg-body');
        var list = task.checklist || {};

        label.appendChild(h.el('span', 'ws-grow', h.t('description')));
        part.appendChild(label);

        if (ctx.editable) {
            var edit = h.button('pg-button pg-button-ghost pg-button-sm', h.t('edit'));

            edit.setAttribute('data-ws-tr-key', 'description');
            edit.addEventListener('click', function () { editDescription(ctx, part, edit); });
            label.appendChild(edit);
        }

        if (task.description_html) {
            h.setHtml(text, task.description_html);
            h.chipMenus(text);
        } else {
            text.appendChild(h.el('span', 'ws-tr-quiet', h.t('tr_none')));
        }

        part.appendChild(text);

        // A list a channel message carries: today's box, ticks and all.
        if (list.message && ctx.drawer.workBox) {
            Array.prototype.forEach.call(text.querySelectorAll('ul.ws-checklist'), function (node) { node.remove(); });
            part.appendChild(ctx.drawer.workBox);
            why(ctx, part, 'description');
            return part;
        }

        // The task's own list, ticked in place: its lists take the place of
        // the description's, which are drawn without who ticked them.
        if (list.own_html) {
            var own = h.el('div');
            var shown = text.querySelectorAll('ul.ws-checklist');

            h.setHtml(own, list.own_html);
            Array.prototype.slice.call(own.querySelectorAll('ul.ws-checklist')).forEach(function (node, index) {
                if (shown[index]) {
                    shown[index].replaceWith(node);
                } else {
                    text.appendChild(node);
                }
            });
            h.chipMenus(text);
            ticks(ctx, text);

            if (task.can_edit) {
                part.appendChild(itemAdder(ctx));
            }
        }

        if (list.progress) {
            var progress = h.el('div', 'ws-tr-progress');

            progress.appendChild(h.progressBar(list.progress, true));
            progress.appendChild(h.el('span', '', h.t('progress_text', list.progress.done, list.progress.total, list.progress.percent)));
            part.appendChild(progress);
        }

        why(ctx, part, 'description');

        return part;
    }

    // A tick is not a change of the task: who may tick today may tick here.
    // It waits while it is sent; refused, it goes back and says why.
    function ticks(ctx, container) {
        Array.prototype.forEach.call(container.querySelectorAll('input[data-ws-check]'), function (input) {
            var item = parseInt(input.getAttribute('data-ws-check'), 10);

            input.setAttribute('data-ws-tr-key', 'tick-' + item);
            input.addEventListener('change', function () {
                input.disabled = true;
                input.closest('li').classList.add('is-busy');

                ctx.h.api('ws_task_tick', { task_id: ctx.task.id, item: item, checked: input.checked ? 1 : 0 }).then(function (data) {
                    redraw(ctx, data.task, { focus: 'tick-' + item });
                    ctx.drawer.changed();
                }).catch(function (error) {
                    input.checked = !input.checked;
                    input.disabled = false;
                    input.closest('li').classList.remove('is-busy');
                    ctx.h.fail(error);
                });
            });
        });
    }

    function itemAdder(ctx) {
        var h = ctx.h;
        var row = h.el('label', 'ws-tr-add');
        var input = h.el('input', 'ws-tr-add-input');

        input.type = 'text';
        input.maxLength = 500;
        input.placeholder = h.t('add_item_placeholder');
        input.setAttribute('aria-label', h.t('add_item'));
        input.setAttribute('data-ws-tr-key', 'add-item');
        input.addEventListener('keydown', function (event) {
            var words = input.value.trim();

            if ((event.key !== 'Enter') || event.isComposing || (words === '')) {
                return;
            }

            event.preventDefault();
            input.disabled = true;

            h.api('ws_task_item_add', { task_id: ctx.task.id, text: words }).then(function (data) {
                redraw(ctx, data.task, { focus: 'add-item' });
                ctx.drawer.changed();
            }).catch(function (error) {
                input.disabled = false;
                h.fail(error);
            });
        });
        row.appendChild(h.icon('bi-plus-lg'));
        row.appendChild(input);

        return row;
    }

    // The form's writing box takes the text's place; "Save" and "Cancel"
    // end its row of tools, Ctrl+Enter saves, Esc gives up (asked first
    // when something was written).
    function editDescription(ctx, part, edit) {
        var h = ctx.h;
        var field = ctx.drawer.fields.description;
        var box = field.input.closest('.ws-desc-field');
        var tools = (box.lastChild !== field.input) ? box.lastChild : box.appendChild(h.el('div', 'ws-note-actions'));
        var cancel = h.button('pg-button pg-button-ghost pg-button-sm', h.t('cancel'));
        var save = h.button('pg-button pg-button-fill pg-button-sm', h.t('save'), 'bi-check2');

        function giveUp() {
            redraw(ctx, ctx.task, { focus: 'description' });
        }

        cancel.addEventListener('click', giveUp);
        save.addEventListener('click', function () { saveValue(ctx, 'description'); });
        tools.appendChild(h.el('span', 'ws-grow'));
        tools.appendChild(cancel);
        tools.appendChild(save);

        box.addEventListener('keydown', function (event) {
            if ((event.key === 'Enter') && (event.ctrlKey || event.metaKey) && !event.isComposing) {
                event.preventDefault();
                saveValue(ctx, 'description');
            } else if ((event.key === 'Escape') && !event.defaultPrevented) {
                event.preventDefault();
                event.stopPropagation();

                if (!field.dirty()) {
                    giveUp();
                    return;
                }

                h.ask(h.t('tr_discard'), h.t('tr_discard_yes'), true).then(function (yes) {
                    if (yes) {
                        giveUp();
                    }
                });
            }
        });

        edit.remove();
        Array.prototype.slice.call(part.children, 1).forEach(function (node) { node.remove(); });
        part.appendChild(box);
        part.classList.add('is-writing');
        field.input.focus();
    }

    // ── The parts, folded ────────────────────────────────────────────────

    function fold(ctx, key, iconName, name, summary, content) {
        var h = ctx.h;
        var wrap = h.el('div', 'ws-tr-fold');
        var toggle = h.el('button', 'ws-tr-fold-head');
        var inner = h.el('div', 'ws-tr-fold-body');
        var isOpen = !!opened.keys[key];

        toggle.type = 'button';
        inner.id = h.nextId('ws-tr-fold-');
        toggle.setAttribute('aria-controls', inner.id);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggle.setAttribute('data-ws-tr-key', 'fold-' + key);
        toggle.appendChild(h.icon(iconName, 'ws-tr-fold-icon'));
        toggle.appendChild(h.el('span', 'ws-tr-fold-name', name));
        toggle.appendChild(h.el('span', 'ws-tr-fold-state', summary || ''));
        toggle.appendChild(h.icon('bi-chevron-down', 'ws-tr-fold-chev'));
        inner.hidden = !isOpen;
        inner.appendChild(content);
        wrap.classList.toggle('is-open', isOpen);
        toggle.addEventListener('click', function () {
            var now = inner.hidden;

            inner.hidden = !now;
            opened.keys[key] = now;
            wrap.classList.toggle('is-open', now);
            toggle.setAttribute('aria-expanded', now ? 'true' : 'false');
        });

        wrap.appendChild(toggle);
        wrap.appendChild(inner);

        return wrap;
    }

    function folds(ctx, form) {
        var h = ctx.h;
        var t = h.t;
        var task = ctx.task;
        var drawer = ctx.drawer;
        var box = h.el('div', 'ws-tr-folds');
        var refs = drawer.fields.refs ? drawer.fields.refs.parentNode : null;
        var time = form.querySelector('.ws-task-time');
        var links = form.querySelector('.ws-task-links:not(.ws-task-feedback):not(.ws-task-approvals)');
        var approvals = form.querySelector('.ws-task-approvals');
        var feedback = form.querySelector('.ws-task-feedback');

        if (refs) {
            box.appendChild(fold(ctx, 'refs', 'bi-tag', t('about_records'), String(drawer.refs.length), refs));

            // A record tagged or taken off is saved at once.
            if (ctx.editable) {
                drawer.refsChanged = function () { saveValue(ctx, 'refs'); };
            }
        }

        if (drawer.delegatePart) {
            box.appendChild(fold(ctx, 'assistant', 'bi-stars', t('tr_assistant'), task.delegate_name || t('tr_not_handed'), drawer.delegatePart.node));
        }

        if (time) {
            box.appendChild(fold(ctx, 'time', 'bi-stopwatch', t('tt_title'), (task.time && task.time.compare) || task.time_label || '', time));
        }

        if (links) {
            var waits = (task.blocked_by || []).map(function (item) { return item.number; });

            box.appendChild(fold(ctx, 'links', 'bi-diagram-3', t('tl_title'), waits.length ? t('tr_waiting_for', waits.join(', ')) : t('tr_none'), links));
        }

        if (approvals) {
            box.appendChild(fold(ctx, 'approval', 'bi-patch-check', t('tr_approval'), '', approvals));
        }

        if (feedback) {
            box.appendChild(fold(ctx, 'feedback', 'bi-chat-square-heart', task.feedback.title, task.feedback.score, feedback));
        }

        if (drawer.notesBox) {
            box.appendChild(fold(ctx, 'notes', 'bi-journal-text', t('notes'), String(task.notes.length), drawer.notesBox));
        }

        return box;
    }

    // ── The page ─────────────────────────────────────────────────────────

    function page(drawer, task, h) {
        var node = drawer.node;
        var body = node.querySelector('.drawer-body');
        var form = h.el('div', 'ws-tr-form d-none');
        var state = next || {};
        var ctx = { drawer: drawer, task: task, h: h, body: body, editable: !!task.can_edit, state: state, reopen: '' };

        next = null;
        closePicker(true);
        reset(node);

        if (opened.task !== task.id) {
            opened = { task: task.id, keys: {} };
        }

        // The form stays, hidden: a row writes its field and saves the whole
        // task, as the form's "Save" did.
        while (body.firstChild) {
            form.appendChild(body.firstChild);
        }

        node.classList.add('ws-tr');
        node.querySelector('.drawer-footer').classList.add('d-none');
        head(ctx);
        body.appendChild(titlePart(ctx));
        body.appendChild(rows(ctx));
        body.appendChild(description(ctx));
        body.appendChild(folds(ctx, form));

        var made = h.t('created_by', task.creator, task.created_label);

        if (task.completed_label) {
            made += ' · ' + h.t('completed_on', task.completed_label);
        }

        if (task.made_by_label) {
            made += ' · ' + task.made_by_label;
        }

        body.appendChild(h.el('div', 'ws-tr-made', made));
        body.appendChild(form);

        window.requestAnimationFrame(function () {
            if (ctx.fit) {
                ctx.fit();
            }

            body.scrollTop = state.scroll || 0;

            var target = state.focus ? body.parentNode.querySelector('[data-ws-tr-key="' + state.focus + '"]') : null;

            if (state.reopen && target && (target.tagName === 'BUTTON')) {
                target.click();

                var search = picker && picker.box.querySelector('.ws-tr-search');

                if (search) {
                    search.value = state.search || '';
                    search.dispatchEvent(new Event('input'));
                    search.focus();
                }
            } else if (target && !target.disabled) {
                target.focus({ preventScroll: true });
            }
        });
    }

    // ── New task ─────────────────────────────────────────────────────────

    // Five fields, the rest folded under "More"; opened from a place that
    // already set one of those, "More" starts open.
    function newForm(drawer, body, h) {
        var f = drawer.fields;
        var defaults = drawer.defaults || {};
        var holder = function (control) { return control ? control.closest('.ws-plan-field') : null; };
        var title = holder(f.title);
        var desc = holder(f.description.input);
        var people = holder(f.people);
        var due = holder(f.due);
        var priority = holder(f.priority);

        reset(drawer.node);

        if (!title || !desc || !people || !due || !priority) {
            return;
        }

        var pair = h.el('div', 'row g-3');

        [due, priority].forEach(function (row) {
            var cell = row.parentNode;
            var col = h.el('div', 'col-6');

            col.appendChild(row);
            pair.appendChild(col);

            if (cell && cell.classList.contains('col-6') && !cell.firstChild) {
                cell.remove();
            }
        });

        var more = h.el('div', 'ws-tr-more');
        var toggle = h.el('button', 'ws-tr-more-head');
        var words = h.el('span', 'ws-tr-more-text');
        var inner = h.el('div', 'ws-tr-more-body');
        var open = !!(defaults.start_date || defaults.department_id || defaults.channel_id || defaults.estimate_hours || (defaults.refs && defaults.refs.length));

        toggle.type = 'button';
        inner.id = h.nextId('ws-tr-more-');
        toggle.setAttribute('aria-controls', inner.id);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.appendChild(h.el('span', 'pg-icon-tile pg-tone-gray', '')).appendChild(h.icon('bi-sliders'));
        words.appendChild(h.el('span', 'ws-tr-more-name', h.t('more')));
        words.appendChild(h.el('span', 'ws-tr-more-line', h.t('tr_more_line')));
        toggle.appendChild(words);
        toggle.appendChild(h.icon('bi-chevron-down', 'ws-tr-fold-chev'));
        more.classList.toggle('is-open', open);
        inner.hidden = !open;
        toggle.addEventListener('click', function () {
            var now = inner.hidden;

            inner.hidden = !now;
            more.classList.toggle('is-open', now);
            toggle.setAttribute('aria-expanded', now ? 'true' : 'false');
        });

        // Everything but the five fields, the note of a linked list and the
        // warnings goes under "More", in the order it was built.
        Array.prototype.slice.call(body.children).forEach(function (child) {
            if ((child === title) || (child === desc) || (child === people) || (child === f.warnings) || child.classList.contains('ws-task-link-note')) {
                return;
            }

            inner.appendChild(child);
        });

        more.appendChild(toggle);
        more.appendChild(inner);
        [title, desc, people, pair, more, f.warnings].forEach(function (node) { body.appendChild(node); });
    }

    window.PGWsTaskRead = { page: page, newForm: newForm };
})();
