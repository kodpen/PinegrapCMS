/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - channel templates (includes/workspace/templates.php) on the
 * screens: the "From a template" cards on top of the new channel form, the
 * "Apply a template" window of a channel's menu, "Make a template of this
 * channel", and the task and note rows of the template screen
 * (workspace_template.php).
 *
 * assets/js/workspace.js asks for the first three through
 * window.PGWsTemplates and hands over its own helpers (api, toast, fail);
 * the template screen is drawn by this file alone when it finds
 * #ws-template-form. Every text comes from the #ws-config block.
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

    var S = CFG.strings || {};
    var TPL = CFG.templates || {};

    // ── Small helpers ──────────────────────────────────────────────────

    function t(key) {
        var text = Object.prototype.hasOwnProperty.call(S, key) ? String(S[key]) : key;
        var values = Array.prototype.slice.call(arguments, 1);

        values.forEach(function (value, index) {
            text = text.split('{' + (index + 1) + '}').join(String(value));
        });

        return text;
    }

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if ((text !== undefined) && (text !== null) && (text !== '')) {
            node.textContent = String(text);
        }

        return node;
    }

    function icon(name, extra) {
        var node = el('i', 'bi ' + name + (extra ? ' ' + extra : ''));
        node.setAttribute('aria-hidden', 'true');
        return node;
    }

    function button(className, label, iconName, title) {
        var node = el('button', className);
        node.type = 'button';

        if (iconName) {
            node.appendChild(icon(iconName, label ? 'me-1' : ''));
        }

        if (label) {
            node.appendChild(document.createTextNode(label));
        }

        if (title) {
            node.title = title;
            node.setAttribute('aria-label', title);
        }

        return node;
    }

    function clear(node) {
        while (node && node.firstChild) {
            node.removeChild(node.firstChild);
        }

        return node;
    }

    // An icon class as the server keeps it: bi-something, or the default.
    function iconClass(name) {
        return /^bi-[a-z0-9-]+$/.test(String(name || '')) ? name : 'bi-layout-text-window';
    }

    // ── The cards a template is chosen from ────────────────────────────

    // One template as a card: its icon in its colour, its name, its words
    // and how much it brings.
    function card(template) {
        var node = el('button', 'ws-tpl-card');
        node.type = 'button';
        node.setAttribute('aria-pressed', 'false');

        var badge = el('span', 'ws-tpl-icon');
        badge.appendChild(icon(template ? iconClass(template.icon) : 'bi-square'));

        if (template && template.hex) {
            badge.style.background = template.hex;
        }

        node.appendChild(badge);

        var text = el('span', 'ws-tpl-card-text');
        var name = el('span', 'ws-tpl-card-name', template ? template.name : t('tpl_none'));

        if (template && template.builtin) {
            name.appendChild(el('span', 'badge text-bg-light ms-1', t('tpl_builtin')));
        }

        text.appendChild(name);
        text.appendChild(el('span', 'ws-tpl-card-desc', template ? template.description : t('tpl_none_help')));

        if (template) {
            text.appendChild(el('span', 'ws-tpl-card-count', t('tpl_counts', template.tasks, template.notes)));
        }

        node.appendChild(text);

        return node;
    }

    // A list of cards, one of them chosen; the first is "No template" when
    // asked for. onPick is called with the template, or null.
    function cardList(helpers, withNone, onPick) {
        var box = el('div', 'ws-tpl-cards');
        var chosen = null;
        var cards = [];

        box.appendChild(el('div', 'small text-body-secondary', t('loading')));

        function mark(node) {
            cards.forEach(function (item) {
                item.classList.toggle('active', item === node);
                item.setAttribute('aria-pressed', item === node ? 'true' : 'false');
            });
        }

        function add(template) {
            var node = card(template);

            node.addEventListener('click', function () {
                chosen = template;
                mark(node);

                if (onPick) {
                    onPick(template);
                }
            });

            cards.push(node);
            box.appendChild(node);

            return node;
        }

        helpers.api('ws_templates', {}).then(function (data) {
            clear(box);

            var templates = data.templates || [];

            if (withNone) {
                mark(add(null));
            }

            templates.forEach(add);

            if (!templates.length) {
                box.appendChild(el('div', 'small text-body-secondary', t('tpl_empty')));
            }
        }).catch(function (error) {
            clear(box);
            box.appendChild(el('div', 'small text-danger', error.message));
        });

        return {
            node: box,
            value: function () {
                return chosen;
            }
        };
    }

    // The section on top of the new channel form. value() is the id of the
    // template chosen, '' for none.
    function picker(helpers, onPick) {
        var box = el('div', 'mb-3 ws-tpl-picker');
        var label = el('div', 'form-label', t('tpl_from'));
        var help = el('div', 'form-text d-none', t('tpl_suggest_help'));
        var list = cardList(helpers, true, function (template) {
            help.classList.toggle('d-none', !template);

            if (onPick) {
                onPick(template);
            }
        });

        box.appendChild(label);
        box.appendChild(list.node);
        box.appendChild(help);

        return {
            node: box,
            value: function () {
                var template = list.value();

                return template ? String(template.id) : '';
            }
        };
    }

    // What a template suggests for the channel, written into the form:
    // the name (unless one was typed), who can see it, the department and
    // the colour. Every one of them can still be changed.
    function suggest(template, fields) {
        if (!template) {
            return;
        }

        if (fields.name && (!fields.name.value || fields.name.getAttribute('data-ws-suggested') === fields.name.value)) {
            fields.name.value = template.name;
            fields.name.setAttribute('data-ws-suggested', template.name);
        }

        if (fields.kind) {
            Array.prototype.forEach.call(fields.kind.querySelectorAll('input[type="radio"]'), function (radio) {
                radio.checked = (radio.value === template.kind);
            });
        }

        if (fields.department && template.department_id && fields.department.querySelector('option[value="' + template.department_id + '"]')) {
            fields.department.value = String(template.department_id);
        }

        if (fields.colors && fields.colors.set) {
            fields.colors.set(template.color || 0);
        }
    }

    // One window, reused: a channel's menu applies a template to it.
    var applyModal = null;

    function applyDialog(channel, helpers) {
        if (!applyModal) {
            applyModal = el('div', 'modal fade ws-tpl-modal');
            applyModal.tabIndex = -1;
            applyModal.innerHTML = '<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg"><div class="modal-content">'
                + '<div class="modal-header"><h2 class="modal-title h6" data-ws-tpl-title></h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>'
                + '<div class="modal-body"><p class="small text-body-secondary" data-ws-tpl-help></p><div data-ws-tpl-list></div></div>'
                + '<div class="modal-footer"><a class="btn btn-sm btn-ghost me-auto d-none" data-ws-tpl-manage></a><button type="button" class="btn btn-sm btn-ghost" data-bs-dismiss="modal" data-ws-tpl-cancel></button>'
                + '<button type="button" class="btn btn-sm btn-primary rounded-pill px-3" data-ws-tpl-ok></button></div></div></div>';
            document.body.appendChild(applyModal);
        }

        applyModal.querySelector('[data-ws-tpl-title]').textContent = t('tpl_apply');
        applyModal.querySelector('[data-ws-tpl-help]').textContent = t('tpl_apply_help');
        applyModal.querySelector('.btn-close').setAttribute('aria-label', t('close'));
        applyModal.querySelector('[data-ws-tpl-cancel]').textContent = t('cancel');

        var manage = applyModal.querySelector('[data-ws-tpl-manage]');
        manage.textContent = t('tpl_manage');
        manage.href = TPL.settings_url || '#';
        manage.classList.toggle('d-none', !TPL.manage);

        var list = cardList(helpers, false, null);
        clear(applyModal.querySelector('[data-ws-tpl-list]')).appendChild(list.node);

        var ok = applyModal.querySelector('[data-ws-tpl-ok]');
        var modal = window.bootstrap.Modal.getOrCreateInstance(applyModal);

        ok.textContent = t('tpl_apply_button');
        ok.disabled = false;
        ok.onclick = function () {
            var template = list.value();

            if (!template) {
                helpers.toast(t('tpl_pick_first'), 'warning');
                return;
            }

            ok.disabled = true;

            helpers.api('ws_template_apply', { channel_id: channel.id, template_id: template.id }).then(function (data) {
                modal.hide();
                helpers.toast(data.message, data.warning ? 'warning' : 'success');

                if (helpers.done) {
                    helpers.done(data);
                }
            }).catch(function (error) {
                ok.disabled = false;
                helpers.fail(error);
            });
        };

        modal.show();
    }

    // "Make a template of this channel": the draft opens on the template
    // screen, where it is read through before anything is kept.
    function fromChannel(channel, helpers) {
        helpers.api('ws_template_from_channel', { channel_id: channel.id }).then(function (data) {
            window.location.href = data.url;
        }).catch(helpers.fail);
    }

    // ── The template screen (workspace_template.php) ───────────────────

    function editorScreen(form) {
        var dataNode = document.getElementById('ws-template-data');
        var bodyField = document.getElementById('ws-template-body');
        var taskBox = document.getElementById('ws-template-tasks');
        var noteBox = document.getElementById('ws-template-notes');
        var DATA = {};
        var body = { tasks: [], notes: [] };

        try {
            DATA = JSON.parse((dataNode && dataNode.textContent) || '{}');
        } catch (error) {
            DATA = {};
        }

        try {
            body = JSON.parse(bodyField.value || '{}') || {};
        } catch (error) {
            body = {};
        }

        var keySeed = 0;
        var tasks = (body.tasks || []).map(function (task) {
            keySeed += 1;
            return { key: keySeed, data: task };
        });

        // Dependencies are kept as keys while the rows move, and written
        // back as positions when the form is sent.
        tasks.forEach(function (row) {
            row.after = (row.data.depends_on || []).map(function (index) {
                return tasks[index] ? tasks[index].key : 0;
            }).filter(Boolean);
        });

        var notes = (body.notes || []).map(function (note) {
            return { data: note };
        });

        // The writing box of the channels for the three texts, where it is
        // loaded; the plain field otherwise.
        var rich = [];

        if (window.PGWsEditor) {
            Array.prototype.forEach.call(form.querySelectorAll('textarea[data-ws-rich]'), function (area) {
                var editor = window.PGWsEditor.create({ placeholder: '' });

                editor.node.classList.add('ws-rich-field', 'ws-tpl-rich');
                editor.setMarkup(area.value, DATA.labels || {});
                window.getSelection().removeAllRanges();

                var tools = el('div', 'ws-note-actions');
                var list = button('btn btn-sm btn-ghost', '', 'bi-ui-checks', t('insert_checklist'));
                var table = button('btn btn-sm btn-ghost', '', 'bi-table', t('insert_table'));

                list.addEventListener('click', function () {
                    window.PGWsEditor.openChecklist('', function (markup) {
                        if (markup !== '') {
                            editor.insertBlock('checklist', markup);
                        }
                    });
                });

                table.addEventListener('click', function () {
                    window.PGWsEditor.openTable({ columns: 3, rows: 3 }, function (markup) {
                        if (markup !== '') {
                            editor.insertBlock('table', markup);
                        }
                    });
                });

                tools.appendChild(list);
                tools.appendChild(table);

                area.classList.add('d-none');
                area.parentNode.insertBefore(editor.node, area);
                area.parentNode.insertBefore(tools, area);
                rich.push({ area: area, editor: editor });
            });
        }

        // The icon beside its field follows what is typed.
        var iconField = document.getElementById('ws_tpl_icon');
        var iconPreview = document.getElementById('ws_tpl_icon_preview');

        if (iconField && iconPreview) {
            iconField.addEventListener('input', function () {
                iconPreview.className = 'bi ' + iconClass(iconField.value.trim().replace(/^bi\s+/, ''));
            });
        }

        function select(options, value) {
            var node = el('select', 'form-select form-select-sm');

            options.forEach(function (option) {
                if (option.group) {
                    var group = el('optgroup');
                    group.label = option.group;
                    option.items.forEach(function (item) {
                        var choice = el('option', '', item[1]);
                        choice.value = item[0];
                        choice.selected = (String(item[0]) === String(value));
                        group.appendChild(choice);
                    });
                    node.appendChild(group);
                    return;
                }

                var item = el('option', '', option[1]);
                item.value = option[0];
                item.selected = (String(option[0]) === String(value));
                node.appendChild(item);
            });

            return node;
        }

        function numberField(value, max) {
            var node = el('input', 'form-control form-control-sm');
            node.type = 'number';
            node.min = '0';
            node.max = String(max);
            node.value = (value === null || value === undefined) ? '' : String(value);

            return node;
        }

        function labelled(label, control, className) {
            var box = el('div', className || 'col-6 col-md-3');
            var caption = el('label', 'form-label small mb-1', label);
            control.id = 'ws-tpl-f-' + (++keySeed);
            caption.htmlFor = control.id;
            box.appendChild(caption);
            box.appendChild(control);

            return box;
        }

        function assignOptions() {
            var options = [
                ['creator', t('tpl_assign_creator')],
                ['department_lead', t('tpl_assign_lead')],
                ['none', t('tpl_assign_none')]
            ];

            if ((DATA.departments || []).length) {
                options.push({ group: t('tpl_departments'), items: DATA.departments.map(function (item) { return ['department:' + item.id, t('tpl_assign_dept', item.name)]; }) });
            }

            if ((DATA.people || []).length) {
                options.push({ group: t('tpl_people'), items: DATA.people.map(function (item) { return ['user:' + item.id, item.name]; }) });
            }

            return options;
        }

        // Rows move by dragging their handle.
        var dragged = null;

        function draggable(row, list, items, item, redraw) {
            var handle = button('btn btn-sm btn-ghost ws-tpl-handle', '', 'bi-grip-vertical', t('tpl_drag'));

            handle.addEventListener('mousedown', function () { row.draggable = true; });
            handle.addEventListener('touchstart', function () { row.draggable = true; }, { passive: true });

            row.addEventListener('dragstart', function (event) {
                dragged = item;
                row.classList.add('ws-tpl-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', '');
            });

            row.addEventListener('dragend', function () {
                row.draggable = false;
                row.classList.remove('ws-tpl-dragging');
                dragged = null;
            });

            row.addEventListener('dragover', function (event) {
                if (dragged && (items.indexOf(dragged) !== -1) && dragged !== item) {
                    event.preventDefault();
                }
            });

            row.addEventListener('drop', function (event) {
                if (!dragged || (items.indexOf(dragged) === -1) || dragged === item) {
                    return;
                }

                event.preventDefault();
                collect();

                var from = items.indexOf(dragged);
                var to = items.indexOf(item);

                items.splice(from, 1);
                items.splice(to, 0, dragged);
                redraw();
            });

            return handle;
        }

        // ── Tasks ──

        function taskRow(row, position) {
            var task = row.data;
            var node = el('div', 'ws-tpl-item');
            var head = el('div', 'ws-tpl-item-head');

            head.appendChild(draggable(node, taskBox, tasks, row, drawTasks));
            head.appendChild(el('span', 'ws-tpl-item-n', String(position + 1) + '.'));

            var title = el('input', 'form-control form-control-sm ws-grow');
            title.type = 'text';
            title.maxLength = 255;
            title.value = task.title || '';
            title.placeholder = t('title');
            title.setAttribute('aria-label', t('title'));
            head.appendChild(title);

            var remove = button('btn btn-sm btn-ghost', '', 'bi-trash', t('tpl_remove_task'));
            remove.addEventListener('click', function () {
                collect();
                tasks.splice(tasks.indexOf(row), 1);
                tasks.forEach(function (other) {
                    other.after = other.after.filter(function (key) { return key !== row.key; });
                });
                drawTasks();
            });
            head.appendChild(remove);
            node.appendChild(head);

            var grid = el('div', 'row g-2 mt-1');
            var priorities = Object.keys(DATA.priorities || {}).map(function (key) { return [key, DATA.priorities[key]]; });
            var priority = select(priorities, task.priority || 'normal');
            var start = numberField(task.start_in_days, 3650);
            var due = numberField(task.due_in_days, 3650);
            var estimate = numberField(task.estimate_minutes || '', 100000);
            var assign = select(assignOptions(), task.assign || 'none');

            grid.appendChild(labelled(t('priority'), priority));
            grid.appendChild(labelled(t('tpl_start_in'), start));
            grid.appendChild(labelled(t('tpl_due_in'), due));
            grid.appendChild(labelled(t('tpl_estimate'), estimate));
            grid.appendChild(labelled(t('tpl_assign'), assign, 'col-12 col-md-6'));

            var description = el('textarea', 'form-control form-control-sm');
            description.rows = 2;
            description.maxLength = 2000;
            description.value = task.description || '';
            grid.appendChild(labelled(t('description'), description, 'col-12 col-md-6'));
            node.appendChild(grid);

            // The checklist: one field an item.
            var listBox = el('div', 'mt-2');
            var items = el('div', 'ws-tpl-checklist');

            listBox.appendChild(el('div', 'form-label small mb-1', t('insert_checklist')));

            function itemField(value) {
                var line = el('div', 'input-group input-group-sm mb-1');
                var text = el('input', 'form-control');
                text.type = 'text';
                text.maxLength = 180;
                text.value = value || '';
                text.placeholder = t('tpl_item_placeholder');
                text.setAttribute('aria-label', t('tpl_item_placeholder'));
                line.appendChild(text);

                var drop = button('btn btn-outline-secondary', '', 'bi-x-lg', t('remove'));
                drop.addEventListener('click', function () { line.remove(); });
                line.appendChild(drop);
                items.appendChild(line);

                return text;
            }

            (task.checklist || []).forEach(function (value) { itemField(value); });
            listBox.appendChild(items);

            var addItem = button('btn btn-sm btn-link px-0', t('add_item'), 'bi-plus-lg');
            addItem.addEventListener('click', function () { itemField('').focus(); });
            listBox.appendChild(addItem);
            node.appendChild(listBox);

            // "After these tasks": the earlier ones, as toggles.
            var earlier = tasks.slice(0, position);

            if (earlier.length) {
                var afterBox = el('div', 'mt-2');
                afterBox.appendChild(el('div', 'form-label small mb-1', t('tpl_after')));

                var chips = el('div', 'ws-tpl-after');

                earlier.forEach(function (other, index) {
                    var on = row.after.indexOf(other.key) !== -1;
                    var chip = el('button', 'btn btn-sm rounded-pill ' + (on ? 'btn-primary' : 'btn-outline-secondary'), String(index + 1) + '. ' + (other.data.title || t('tpl_task_n', index + 1)));
                    chip.type = 'button';
                    chip.setAttribute('aria-pressed', on ? 'true' : 'false');
                    chip.addEventListener('click', function () {
                        var at = row.after.indexOf(other.key);

                        if (at === -1) {
                            row.after.push(other.key);
                        } else {
                            row.after.splice(at, 1);
                        }

                        collect();
                        drawTasks();
                    });
                    chips.appendChild(chip);
                });

                afterBox.appendChild(chips);
                afterBox.appendChild(el('div', 'form-text', t('tpl_after_help')));
                node.appendChild(afterBox);
            }

            // What the row holds now, read back before every redraw and on
            // sending.
            row.read = function () {
                var checklist = [];

                Array.prototype.forEach.call(items.querySelectorAll('input'), function (input) {
                    if (input.value.trim() !== '') {
                        checklist.push(input.value.trim());
                    }
                });

                row.data = {
                    title: title.value.trim(),
                    description: description.value,
                    priority: priority.value,
                    start_in_days: (start.value === '') ? null : parseInt(start.value, 10),
                    due_in_days: (due.value === '') ? null : parseInt(due.value, 10),
                    estimate_minutes: parseInt(estimate.value, 10) || 0,
                    assign: assign.value,
                    checklist: checklist
                };
            };

            return node;
        }

        function drawTasks() {
            clear(taskBox);

            // A task waits only for an earlier one: what moved below it is
            // let go.
            tasks.forEach(function (row, position) {
                var earlier = tasks.slice(0, position).map(function (other) { return other.key; });
                row.after = row.after.filter(function (key) { return earlier.indexOf(key) !== -1; });
            });

            if (!tasks.length) {
                taskBox.appendChild(el('div', 'small text-body-secondary mb-2', t('tpl_no_tasks')));
            }

            tasks.forEach(function (row, position) {
                taskBox.appendChild(taskRow(row, position));
            });

            var add = button('btn btn-sm btn-outline-secondary rounded-pill px-3', t('tpl_add_task'), 'bi-plus-lg');
            add.addEventListener('click', function () {
                collect();
                keySeed += 1;
                tasks.push({ key: keySeed, data: { priority: 'normal', assign: 'creator', checklist: [] }, after: [] });
                drawTasks();

                var fields = taskBox.querySelectorAll('.ws-tpl-item-head input');

                if (fields.length) {
                    fields[fields.length - 1].focus();
                }
            });
            taskBox.appendChild(add);
        }

        // ── Notes ──

        function noteRow(row) {
            var node = el('div', 'ws-tpl-item');
            var head = el('div', 'ws-tpl-item-head');

            head.appendChild(draggable(node, noteBox, notes, row, drawNotes));

            var title = el('input', 'form-control form-control-sm ws-grow');
            title.type = 'text';
            title.maxLength = 200;
            title.value = row.data.title || '';
            title.placeholder = t('title');
            title.setAttribute('aria-label', t('title'));
            head.appendChild(title);

            var remove = button('btn btn-sm btn-ghost', '', 'bi-trash', t('tpl_remove_note'));
            remove.addEventListener('click', function () {
                collect();
                notes.splice(notes.indexOf(row), 1);
                drawNotes();
            });
            head.appendChild(remove);
            node.appendChild(head);

            var text = el('textarea', 'form-control form-control-sm mt-2 ws-tpl-note-body');
            text.rows = 6;
            text.maxLength = 20000;
            text.value = row.data.body || '';
            text.placeholder = t('tpl_note_body');
            text.setAttribute('aria-label', t('tpl_note_body'));
            node.appendChild(text);

            row.read = function () {
                row.data = { title: title.value.trim(), body: text.value };
            };

            return node;
        }

        function drawNotes() {
            clear(noteBox);

            if (!notes.length) {
                noteBox.appendChild(el('div', 'small text-body-secondary mb-2', t('tpl_no_notes')));
            }

            notes.forEach(function (row) {
                noteBox.appendChild(noteRow(row));
            });

            var add = button('btn btn-sm btn-outline-secondary rounded-pill px-3', t('add_note'), 'bi-plus-lg');
            add.addEventListener('click', function () {
                collect();
                notes.push({ data: { title: '', body: '' } });
                drawNotes();
            });
            noteBox.appendChild(add);
        }

        function collect() {
            tasks.forEach(function (row) {
                if (row.read) {
                    row.read();
                }
            });

            notes.forEach(function (row) {
                if (row.read) {
                    row.read();
                }
            });
        }

        drawTasks();
        drawNotes();

        form.addEventListener('submit', function () {
            collect();

            var keys = tasks.map(function (row) { return row.key; });

            bodyField.value = JSON.stringify({
                tasks: tasks.map(function (row) {
                    var task = {};

                    Object.keys(row.data).forEach(function (field) { task[field] = row.data[field]; });
                    task.depends_on = row.after.map(function (key) { return keys.indexOf(key); }).filter(function (index) { return index >= 0; });

                    return task;
                }),
                notes: notes.map(function (row) { return row.data; }).filter(function (note) {
                    return (note.title || '').trim() !== '' || (note.body || '').trim() !== '';
                })
            });

            rich.forEach(function (item) {
                item.area.value = item.editor.markup();
            });
        });
    }

    function start() {
        var form = document.getElementById('ws-template-form');

        if (form) {
            editorScreen(form);
        }
    }

    window.PGWsTemplates = {
        picker: picker,
        suggest: suggest,
        applyDialog: applyDialog,
        fromChannel: fromChannel
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
