/**
 * Pinegrap - Enterprise Website Platform
 *
 * The rule editor of a contact group filled by rules (edit_contact_group.php,
 * pg_contact_group_rules_card()). It draws the stored rule set from the
 * catalogue the server gives (pg_audience_editor_data()), keeps the hidden
 * "rules" field in the JSON shape pg_audience_rules_clean() reads, asks the
 * server how many contacts the rules select while they change
 * (api.php audience_count) and runs Refresh now (api.php audience_refresh)
 * until the pass is done. The server cleans whatever is posted: the editor
 * only helps to write a valid set.
 *
 * Pickers (product, product group, contact group, form, form field) are
 * native selects hooked as pgCombobox fields that search
 * edit_contact_group.php?request=pick.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var root, store, config, text, catalogue, byKey, state, countTimer, countRun = 0, uid = 0;

    var PICKERS = { product: 1, product_group: 1, contact_group: 1, form: 1 };
    var NUMBERS = { number: 1, money: 1, days: 1 };

    function el(tag, attrs, children) {
        var node = document.createElement(tag);

        Object.keys(attrs || {}).forEach(function (name) {
            if (name === 'text') {
                node.textContent = attrs[name];
            } else if (attrs[name] !== null && attrs[name] !== false) {
                node.setAttribute(name, attrs[name] === true ? '' : attrs[name]);
            }
        });

        (children || []).forEach(function (child) {
            if (child) {
                node.appendChild(child);
            }
        });

        return node;
    }

    function nextId(prefix) {
        uid += 1;
        return 'pg_audience_' + prefix + '_' + uid;
    }

    function api(body) {
        body.token = (typeof software_token !== 'undefined') ? software_token : '';

        return fetch('api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (response) {
            return response.json();
        });
    }

    // A labelled control: the label is visually hidden, the row reads as a
    // sentence.
    function labelled(label, control) {
        control.id = control.id || nextId('f');

        return el('div', { 'class': 'pg-audience-part' }, [
            el('label', { 'for': control.id, 'class': 'visually-hidden', text: label }),
            control
        ]);
    }

    function select(options, value) {
        var node = el('select', { 'class': 'form-control form-control-sm' });

        options.forEach(function (option) {
            var item = el('option', { value: option.id, text: option.text });

            if (String(option.id) === String(value)) {
                item.selected = true;
            }

            node.appendChild(item);
        });

        return node;
    }

    function picker(kind, value) {
        var node = el('select', {
            'class': 'form-control form-control-sm',
            'data-pg-combobox': true,
            'data-pg-source': config.source + encodeURIComponent(kind),
            'data-pg-min-length': '2',
            'data-placeholder': text.search
        }, [el('option', { value: '' })]);
        var labels = config.labels[kind] || {};

        if (value) {
            node.appendChild(el('option', { value: value, selected: true, text: labels[value] || ('#' + value) }));
        }

        return node;
    }

    function number(value, min, step) {
        return el('input', {
            type: 'number',
            'class': 'form-control form-control-sm',
            min: String(min),
            step: step || '1',
            inputmode: step ? 'decimal' : 'numeric',
            value: (value === undefined || value === null) ? '' : String(value)
        });
    }

    // The value parts of one condition row, for its key and operator.
    function valueParts(condition, entry) {
        var parts = [];
        var op = condition.op;

        if (entry.key === 'field') {
            var contactField = labelled(text.field, select(catalogue.fields, condition.field));
            contactField.lastChild.setAttribute('data-part', 'field');
            parts.push(contactField);
        }

        if (entry.key === 'membership') {
            if (op === 'expiring') {
                parts.push(labelled(text.days, number(condition.value, 1)));
            }
        } else if (entry.value === 'text') {
            if (op !== 'empty' && op !== 'not_empty') {
                parts.push(labelled(text.value, el('input', { type: 'text', 'class': 'form-control form-control-sm', maxlength: '100', value: condition.value || '' })));
            }
        } else if (NUMBERS[entry.value]) {
            // Money is entered in whole units with cents and kept in minor
            // units, as orders.total.
            var shown = (entry.value === 'money' && condition.value !== undefined) ? condition.value / 100 : condition.value;
            parts.push(labelled(entry.value === 'days' ? text.days : text.value, number(shown, entry.value === 'days' ? 1 : 0, entry.value === 'money' ? '0.01' : null)));
        } else if (PICKERS[entry.value]) {
            parts.push(labelled(entry.label, picker(entry.value, condition.value)));
        } else if (entry.value === 'form_field') {
            var field = labelled(text.field, picker('form_field', condition.field));
            field.lastChild.setAttribute('data-part', 'field');
            parts.push(field);
            parts.push(labelled(text.value, el('input', { type: 'text', 'class': 'form-control form-control-sm', maxlength: '100', value: condition.value || '' })));
        } else if (entry.value === 'consent') {
            parts.push(labelled(text.value, select(catalogue.consents, condition.value)));
        } else if (entry.value === 'status') {
            parts.push(labelled(text.value, select(catalogue.statuses, condition.value)));
        }

        parts.forEach(function (part) {
            var control = part.lastChild;

            if (!control.hasAttribute('data-part')) {
                control.setAttribute('data-part', 'value');
            }
        });

        if (entry.window) {
            var windowPart = labelled(text.window, number(condition.days, 1));
            windowPart.lastChild.setAttribute('data-part', 'days');
            parts.push(el('span', { 'class': 'form-text', 'aria-hidden': 'true', text: text.window }));
            parts.push(windowPart);
        }

        return parts;
    }

    function conditionSelect(key) {
        var node = el('select', { 'class': 'form-control form-control-sm', 'data-part': 'key' });
        var groups = {};

        catalogue.conditions.forEach(function (entry) {
            if (!groups[entry.family]) {
                groups[entry.family] = el('optgroup', { label: catalogue.families[entry.family] || entry.family });
                node.appendChild(groups[entry.family]);
            }

            var option = el('option', { value: entry.key, text: entry.label });

            if (entry.key === key) {
                option.selected = true;
            }

            groups[entry.family].appendChild(option);
        });

        return node;
    }

    function conditionRow(condition) {
        var entry = byKey[condition.key] || catalogue.conditions[0];
        var ops = select(entry.ops.map(function (o) { return { id: o.op, text: o.label }; }), condition.op);
        ops.setAttribute('data-part', 'op');

        var remove = el('button', { type: 'button', 'class': 'btn btn-sm btn-outline theme-danger', 'data-action': 'remove', title: text.remove },
            [el('span', { 'class': 'material-icons', 'aria-hidden': 'true', text: 'close' }), el('span', { 'class': 'visually-hidden', text: text.remove })]);

        var row = el('li', { 'class': 'pg-audience-row d-flex flex-wrap align-items-center gap-2 my-2', 'data-kind': 'condition' },
            [labelled(text.condition, conditionSelect(entry.key)), labelled(text.operator, ops)]
                .concat(valueParts(condition, entry))
                .concat([remove]));

        return row;
    }

    function levelBox(level, depth) {
        var match = select([{ id: 'all', text: text.all }, { id: 'any', text: text.any }], level.match);
        match.setAttribute('data-part', 'match');

        var list = el('ul', { 'class': 'list-unstyled m-0', 'data-list': '1' });

        level.conditions.forEach(function (condition) {
            list.appendChild(condition.conditions ? levelBox(condition, 1) : conditionRow(condition));
        });

        var buttons = [el('button', { type: 'button', 'class': 'btn btn-sm btn-outline theme-primary', 'data-action': 'add' },
            [el('span', { 'class': 'material-icons me-3', 'aria-hidden': 'true', text: 'add' }), el('span', { 'class': 'pg-btn-label', text: text.add })])];

        if (depth === 0) {
            buttons.push(el('button', { type: 'button', 'class': 'btn btn-sm btn-outline theme-primary', 'data-action': 'add_nested' },
                [el('span', { 'class': 'material-icons me-3', 'aria-hidden': 'true', text: 'playlist_add' }), el('span', { 'class': 'pg-btn-label', text: text.add_nested })]));
        } else {
            buttons.push(el('button', { type: 'button', 'class': 'btn btn-sm btn-outline theme-danger', 'data-action': 'remove' },
                [el('span', { 'class': 'material-icons me-3', 'aria-hidden': 'true', text: 'close' }), el('span', { 'class': 'pg-btn-label', text: text.remove })]));
        }

        var head = el('div', { 'class': 'd-flex flex-wrap align-items-center gap-2' }, [
            el('span', { text: depth ? text.nested + ': ' + text.match : text.match }),
            labelled(text.match, match),
            el('span', { text: text.of })
        ]);

        return el(depth ? 'li' : 'div', {
            'class': depth ? 'pg-audience-level border rounded p-3 my-2' : 'pg-audience-level',
            'data-kind': 'level',
            role: 'group',
            'aria-label': depth ? text.nested : text.match
        }, [head, list, el('div', { 'class': 'd-flex flex-wrap gap-2 my-2' }, buttons)]);
    }

    function partValue(row, name) {
        var node = row.querySelector(':scope > .pg-audience-part > [data-part="' + name + '"]');
        return node ? node.value : undefined;
    }

    function readCondition(row) {
        var key = partValue(row, 'key');
        var entry = byKey[key];
        var condition = { key: key, op: partValue(row, 'op') };
        var value = partValue(row, 'value');
        var field = partValue(row, 'field');
        var days = partValue(row, 'days');

        if (field !== undefined) {
            condition.field = (entry.value === 'form_field') ? parseInt(field, 10) || 0 : field;
        }

        if (value !== undefined) {
            if (entry.value === 'money') {
                condition.value = Math.round(parseFloat(String(value).replace(',', '.')) * 100);
            } else if (NUMBERS[entry.value] || PICKERS[entry.value] || entry.key === 'membership') {
                condition.value = parseInt(value, 10);
            } else {
                condition.value = value;
            }

            if (typeof condition.value === 'number' && !isFinite(condition.value)) {
                delete condition.value;
            }
        }

        if (days !== undefined) {
            condition.days = parseInt(days, 10);
        }

        return condition;
    }

    function readLevel(box) {
        var level = { match: box.querySelector(':scope > div [data-part="match"]').value, conditions: [] };
        var list = box.querySelector(':scope > [data-list]');

        Array.prototype.forEach.call(list.children, function (child) {
            level.conditions.push(child.getAttribute('data-kind') === 'level' ? readLevel(child) : readCondition(child));
        });

        return level;
    }

    function sync() {
        state = readLevel(root.firstChild);
        store.value = JSON.stringify(state);
        root.querySelectorAll('[data-action="add"]').forEach(function (button) {
            var list = button.closest('[data-kind="level"]').querySelector(':scope > [data-list]');
            button.disabled = list.children.length >= catalogue.max;
        });
        scheduleCount();
    }

    function initFields(scope) {
        if (window.pgFields && typeof window.pgFields.init === 'function') {
            window.pgFields.init(scope);
        }
    }

    function draw() {
        root.textContent = '';
        root.appendChild(levelBox(state, 0));
        initFields(root);
        sync();
    }

    function freshCondition(key) {
        var entry = byKey[key] || catalogue.conditions[0];
        return { key: entry.key, op: entry.ops[0].op };
    }

    // A changed condition or operator redraws its own row only, so focus
    // stays where the user is.
    function redrawRow(row, condition) {
        var fresh = conditionRow(condition);
        row.parentNode.replaceChild(fresh, row);
        initFields(fresh);
        var focus = fresh.querySelector('[data-part="' + (condition.op === (byKey[condition.key] || {}).ops[0].op ? 'key' : 'op') + '"]');

        if (focus) {
            focus.focus();
        }
    }

    function onChange(event) {
        var target = event.target;
        var part = target.getAttribute && target.getAttribute('data-part');
        var row = target.closest('[data-kind="condition"]');

        if (row && part === 'key') {
            redrawRow(row, freshCondition(target.value));
        } else if (row && part === 'op') {
            var condition = readCondition(row);
            redrawRow(row, condition);
        }

        sync();
    }

    function onClick(event) {
        var button = event.target.closest('[data-action]');

        if (!button || !root.contains(button)) {
            return;
        }

        var action = button.getAttribute('data-action');
        var level = button.closest('[data-kind="level"]');
        var list = level.querySelector(':scope > [data-list]');
        var node = null;

        if (action === 'add') {
            node = conditionRow(freshCondition(''));
            list.appendChild(node);
        } else if (action === 'add_nested') {
            node = levelBox({ match: 'any', conditions: [freshCondition('')] }, 1);
            list.appendChild(node);
        } else if (action === 'remove') {
            var condition = button.closest('[data-kind="condition"]');
            var item = condition || level;
            // Focus goes to the Add button of the level that kept the item.
            var keeper = condition ? level : level.parentNode.closest('[data-kind="level"]');
            item.parentNode.removeChild(item);
            var add = keeper ? keeper.querySelector(':scope > div > [data-action="add"]') : null;

            if (add) {
                add.focus();
            }
        }

        if (node) {
            initFields(node);
            var first = node.querySelector('[data-part="key"]');

            if (first) {
                first.focus();
            }
        }

        sync();
    }

    function scheduleCount() {
        var line = document.getElementById('pg_audience_count');

        if (!line) {
            return;
        }

        clearTimeout(countTimer);
        countTimer = setTimeout(function () {
            var run = ++countRun;
            line.textContent = text.counting;

            api({ action: 'audience_count', rules: state }).then(function (answer) {
                if (run === countRun) {
                    line.textContent = (answer && answer.status === 'success' && answer.text) ? answer.text : text.count_failed;
                }
            }).catch(function () {
                if (run === countRun) {
                    line.textContent = text.count_failed;
                }
            });
        }, 600);
    }

    function fill(template, answer) {
        return template.replace('{added}', answer.added).replace('{removed}', answer.removed).replace('{members}', answer.members);
    }

    function refresh(button) {
        var status = document.getElementById('pg_audience_refresh_status');
        button.disabled = true;

        var step = function () {
            api({ action: 'audience_refresh', contact_group_id: config.group }).then(function (answer) {
                if (!answer || answer.status !== 'success') {
                    status.textContent = (answer && answer.message) || text.refresh_failed;
                    button.disabled = false;
                    return;
                }

                if (answer.done) {
                    status.textContent = fill(text.refreshed, answer);
                    button.disabled = false;
                    return;
                }

                status.textContent = fill(text.refreshing, answer);
                step();
            }).catch(function () {
                status.textContent = text.refresh_failed;
                button.disabled = false;
            });
        };

        step();
    }

    function start() {
        root = document.getElementById('pg_audience_editor');
        store = document.getElementById('rules');

        if (!root || !store) {
            return;
        }

        config = JSON.parse(root.getAttribute('data-pg-audience'));
        text = config.text;
        catalogue = config.catalogue;
        byKey = {};
        catalogue.conditions.forEach(function (entry) {
            byKey[entry.key] = entry;
        });
        state = (config.rules && config.rules.conditions) ? config.rules : { match: 'all', conditions: [] };

        // A Combobox announces a pick through jQuery where select2 still
        // runs (pg-field-wrappers), so listen through jQuery when it is here.
        if (window.jQuery) {
            window.jQuery(root).on('change', onChange).on('input', 'input', function () { sync(); });
        } else {
            root.addEventListener('change', onChange);
            root.addEventListener('input', function (event) {
                if (event.target.tagName === 'INPUT') {
                    sync();
                }
            });
        }

        root.addEventListener('click', onClick);

        var button = document.getElementById('pg_audience_refresh');

        if (button) {
            button.addEventListener('click', function () {
                refresh(button);
            });
        }

        draw();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
