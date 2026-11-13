/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the quick setup of a flow (includes/workspace/flow_quick.php):
 * a dialog that builds a flow by choices in three stages, Start, Steps and
 * Summary, and sends them to the panel action ws_flow_quick_setup, which
 * saves a normal draft through the flow store.
 *
 * The first stage: the site's areas in a rail (the catalogue's areas, in
 * its order, a line between its parts), the starts of the chosen area as
 * cards to choose one from, a search over every area's starts, and the
 * optional "Only when" row, whose conditions become condition boxes after
 * the start (ws_flow_quick_graph()). A later stage hangs itself on
 * window.PGWsFlowQuick.stages under its key; Next leads to it.
 *
 * window.PGWsFlowQuick.open(kit, quick, options) opens the dialog: kit is
 * window.PGWsKit, quick the catalogue ws_flow_js_config() serves as
 * flows.quick, options.operators the comparisons' labels (key => label).
 * window.PGWsFlowQuick.pure holds the functions that read the catalogue and
 * the choices without the page (tests/workspace_flow_quick_js_test.php).
 *
 * Served in assets/js/workspace_flow.min.js after workspace_flow.js
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

    // The comparisons a condition on a value offers by the value's type, as
    // the canvas's condition box (VALUE_OPS in workspace_flow.js).
    var OPS = { number: ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'empty', 'not_empty'], text: ['eq', 'neq', 'contains', 'empty', 'not_empty'] };

    // The comparisons that take no value.
    var BARE = ['empty', 'not_empty'];

    // The stages in their order, each with its label; the first is drawn
    // here.
    var STAGES = ['start', 'steps', 'summary'];
    var STAGE_LABELS = { start: 'fl_quick_stage_start', steps: 'fl_quick_stage_steps', summary: 'fl_quick_stage_summary' };

    // ── Reading the catalogue (pure) ────────────────────────────────────

    // A text as a search compares it: lower case, Turkish letters as their
    // plain twins, as the Workspace's own search (foldText()).
    function fold(value) {
        return String(value || '').toLocaleLowerCase('tr')
            .replace(/ı/g, 'i').replace(/̇/g, '').replace(/ş/g, 's').replace(/ğ/g, 'g')
            .replace(/ü/g, 'u').replace(/ö/g, 'o').replace(/ç/g, 'c')
            .replace(/â/g, 'a').replace(/î/g, 'i').replace(/û/g, 'u');
    }

    function areas(quick) {
        return (quick && Array.isArray(quick.areas)) ? quick.areas : [];
    }

    function startOf(quick, key) {
        var starts = (quick && quick.starts) || {};

        return Object.prototype.hasOwnProperty.call(starts, key) ? starts[key] : null;
    }

    // The area the dialog opens on: the first of the site's own, else the
    // first there is.
    function firstArea(quick) {
        var list = areas(quick);
        var site = list.filter(function (area) { return area.part === 'site'; })[0];

        return site ? site.key : (list[0] ? list[0].key : '');
    }

    // The area that holds a start, the site's own before Often used.
    function areaOfStart(quick, key) {
        var found = areas(quick).filter(function (area) {
            return (area.starts || []).indexOf(key) !== -1;
        });
        var own = found.filter(function (area) { return area.part !== 'top'; })[0];

        return own ? own.key : (found[0] ? found[0].key : '');
    }

    // The starts of one area, in its order.
    function startsOf(quick, areaKey) {
        var area = areas(quick).filter(function (item) { return item.key === areaKey; })[0];

        return area ? (area.starts || []).map(function (key) { return startOf(quick, key); }).filter(Boolean) : [];
    }

    // Every start whose words hold each word typed, in the areas' order and
    // once each: its sentence, its short name and its area's name count.
    function search(quick, query) {
        var words = fold(query).split(/\s+/).filter(Boolean);
        var seen = {};
        var out = [];

        if (!words.length) {
            return out;
        }

        areas(quick).forEach(function (area) {
            (area.starts || []).forEach(function (key) {
                var start = startOf(quick, key);

                if (!start || seen[key]) {
                    return;
                }

                var text = fold([start.label, start.name, area.label].join(' '));

                if (words.every(function (word) { return text.indexOf(word) !== -1; })) {
                    seen[key] = true;
                    out.push(start);
                }
            });
        });

        return out;
    }

    // The comparisons a value of the start offers.
    function opsFor(field) {
        return OPS[(field && field.type === 'number') ? 'number' : 'text'];
    }

    function fieldOf(start, key) {
        return ((start && Array.isArray(start.fields)) ? start.fields : []).filter(function (field) { return field.key === key; })[0] || null;
    }

    // The "Only when" rows as the dialog sends them: those on a value of
    // the start with a comparison of its type and, but for empty and not
    // empty, something to compare with; at most the catalogue's limit.
    function onlyWhen(quick, start, rows) {
        var limit = (quick && quick.limits && quick.limits.only_when) || 5;

        return (Array.isArray(rows) ? rows : []).filter(function (row) {
            var field = fieldOf(start, row && row.field);

            return field && (opsFor(field).indexOf(row.op) !== -1)
                && ((BARE.indexOf(row.op) !== -1) || (String(row.value === undefined ? '' : row.value).trim() !== ''));
        }).slice(0, limit).map(function (row) {
            return { field: row.field, op: row.op, value: (BARE.indexOf(row.op) !== -1) ? '' : String(row.value).trim() };
        });
    }

    // The choices of the first stage in the shape ws_flow_quick_setup takes:
    // the start, its box's settings (an event start names its event; any
    // other start box is set on the canvas or by a later stage) and the
    // conditions.
    function choices(quick, chosen) {
        var start = startOf(quick, chosen && chosen.start);

        if (!start) {
            return null;
        }

        return {
            start: start.key,
            config: (start.kind === 'event') ? { event: start.event } : ((chosen.config && typeof chosen.config === 'object') ? chosen.config : {}),
            only_when: onlyWhen(quick, start, chosen.only_when)
        };
    }

    var pure = {
        fold: fold,
        firstArea: firstArea,
        areaOfStart: areaOfStart,
        startsOf: startsOf,
        search: search,
        opsFor: opsFor,
        onlyWhen: onlyWhen,
        choices: choices
    };

    // ── The dialog ──────────────────────────────────────────────────────

    var K = null;
    var t = null;
    var el = null;
    var ui = null;

    function make() {
        var modal = el('dialog', 'dialog wsf-quick');
        var head = el('div', 'dialog-header wsf-quick-head');
        var titles = el('div', 'wsf-quick-titles');
        var title = el('h2', 'dialog-title pg-title-section', t('fl_quick_title'));
        var sub = el('p', 'pg-text-2 wsf-quick-sub', t('fl_quick_intro'));
        var cross = el('button', 'btn-close');
        var stages = el('ol', 'wsf-quick-stages');
        var body = el('div', 'dialog-body wsf-quick-body');
        var foot = el('div', 'dialog-footer wsf-quick-foot');
        var hint = el('span', 'pg-text-meta wsf-quick-hint');
        var back = K.button('pg-button pg-button-ghost', t('fl_quick_back'), 'bi-arrow-left');
        var cancel = K.button('pg-button pg-button-quiet', t('fl_quick_cancel'));
        var next = K.button('pg-button pg-button-fill', t('fl_quick_next'));

        modal.id = 'wsf-quick';
        modal.tabIndex = -1;
        modal.setAttribute('aria-labelledby', 'wsf-quick-title');
        title.id = 'wsf-quick-title';
        cross.type = 'button';
        cross.setAttribute('aria-label', t('fl_quick_close'));
        cross.setAttribute('data-bs-dismiss', 'dialog');
        cancel.setAttribute('data-bs-dismiss', 'dialog');
        stages.setAttribute('aria-label', t('fl_quick_stages'));
        hint.appendChild(K.icon('bi-diagram-3'));
        hint.appendChild(document.createTextNode(' ' + t('fl_quick_canvas_hint')));
        next.appendChild(K.icon('bi-arrow-right', 'ms-1'));

        STAGES.forEach(function (key, index) {
            var item = el('li', 'wsf-quick-stage');

            item.setAttribute('data-stage', key);
            item.appendChild(el('span', 'wsf-quick-stage-n', String(index + 1)));
            item.appendChild(el('span', '', t(STAGE_LABELS[key])));
            stages.appendChild(item);
        });

        titles.appendChild(title);
        titles.appendChild(sub);
        head.appendChild(titles);
        head.appendChild(cross);
        foot.appendChild(back);
        foot.appendChild(hint);
        foot.appendChild(cancel);
        foot.appendChild(next);
        modal.appendChild(head);
        modal.appendChild(stages);
        modal.appendChild(body);
        modal.appendChild(foot);
        document.body.appendChild(modal);

        back.addEventListener('click', function () { go(-1); });
        next.addEventListener('click', function () { go(1); });

        pgUI.dialog.on(modal, 'hidden', function () {
            var done = ui.state && ui.state.onClose;

            ui.state = null;

            if (typeof done === 'function') {
                done();
            }
        });

        return { modal: modal, body: body, stages: stages, back: back, next: next, hint: hint, state: null };
    }

    // The stage the dialog shows, and the next one when its script is
    // there: a stage a later script draws is skipped until then.
    function stageIndex() {
        return STAGES.indexOf(ui.state.stage);
    }

    function nextStage() {
        var at = stageIndex() + 1;

        return ((at < STAGES.length) && window.PGWsFlowQuick.stages[STAGES[at]]) ? STAGES[at] : '';
    }

    function go(step) {
        var state = ui.state;

        if (!state) {
            return;
        }

        if (step > 0) {
            var to = nextStage();

            if (!to || !choices(state.quick, state.chosen)) {
                return;
            }

            state.stage = to;
        } else if (stageIndex() > 0) {
            state.stage = STAGES[stageIndex() - 1];
        }

        draw();
    }

    // The whole dialog for the stage it is on.
    function draw() {
        var state = ui.state;
        var at = stageIndex();

        Array.prototype.forEach.call(ui.stages.children, function (item, index) {
            item.classList.toggle('is-done', index < at);
            item.classList.toggle('is-active', index === at);

            if (index === at) {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
        });

        ui.back.classList.toggle('d-none', at === 0);
        ui.hint.classList.toggle('d-none', at !== 0);
        ui.body.textContent = '';

        if (state.stage === 'start') {
            drawStart();
        } else {
            window.PGWsFlowQuick.stages[state.stage].draw(ui.body, state, K);
        }

        updateNext();
    }

    function updateNext() {
        var state = ui.state;

        ui.next.classList.toggle('d-none', nextStage() === '');
        ui.next.disabled = !choices(state.quick, state.chosen);
    }

    // ── The first stage: where the flow starts ──────────────────────────

    function drawStart() {
        var state = ui.state;
        var split = el('div', 'wsf-quick-split');
        var rail = el('nav', 'wsf-quick-rail');
        var pane = el('div', 'wsf-quick-pane');
        var find = el('div', 'pg-input wsf-quick-find');
        var input = el('input', '');
        var list = el('div', 'wsf-quick-list');
        var when = el('div', 'wsf-quick-when');
        var part = '';

        rail.setAttribute('aria-label', t('fl_quick_areas'));
        input.type = 'search';
        input.value = state.query;
        input.placeholder = t('fl_quick_search');
        input.setAttribute('aria-label', t('fl_quick_search_label'));
        find.appendChild(K.icon('bi-search'));
        find.appendChild(input);

        areas(state.quick).forEach(function (area) {
            if (part && (area.part !== part)) {
                rail.appendChild(el('hr', 'wsf-quick-rail-line'));
            }

            part = area.part;

            var item = K.button('wsf-quick-area', '', area.icon);
            var on = !state.query && (area.key === state.area);

            item.appendChild(el('span', 'wsf-quick-area-name', area.label));

            if (area.part === 'site') {
                item.appendChild(el('span', 'pg-text-count wsf-quick-area-count', String((area.starts || []).length)));
            }

            item.classList.toggle('is-active', on);

            if (on) {
                item.setAttribute('aria-current', 'true');
            }

            item.addEventListener('click', function () {
                state.area = area.key;
                state.query = '';
                draw();
                focusIn('.wsf-quick-area.is-active');
            });
            rail.appendChild(item);
        });

        input.addEventListener('input', K.debounce(function () {
            state.query = input.value;
            drawCards(list);
            Array.prototype.forEach.call(rail.querySelectorAll('.wsf-quick-area'), function (item) {
                item.classList.toggle('is-quiet', state.query.trim() !== '');
            });
        }, 120));

        pane.appendChild(find);
        pane.appendChild(list);
        pane.appendChild(when);
        split.appendChild(rail);
        split.appendChild(pane);
        ui.body.appendChild(split);
        drawCards(list);
        drawWhen(when);
    }

    // The starts of the area, or those the search finds, as a group of
    // radio cards.
    function drawCards(list) {
        var state = ui.state;
        var query = state.query.trim();
        var starts = query ? search(state.quick, query) : startsOf(state.quick, state.area);
        var group = el('div', 'wsf-quick-cards');

        list.textContent = '';
        group.setAttribute('role', 'radiogroup');
        group.setAttribute('aria-label', query ? t('fl_quick_found', starts.length) : t('fl_quick_pick'));

        if (query) {
            list.appendChild(el('p', 'pg-text-meta wsf-quick-found', starts.length ? t('fl_quick_found', starts.length) : t('fl_quick_none', query)));
        }

        starts.forEach(function (start) {
            var card = el('label', 'wsf-quick-card');
            var radio = el('input', 'wsf-quick-radio');
            var words = el('span', 'wsf-quick-card-words');
            var area = areas(state.quick).filter(function (item) { return item.key === areaOfStart(state.quick, start.key); })[0];
            var tile = el('span', 'pg-icon-tile');

            radio.type = 'radio';
            radio.name = 'wsf-quick-start';
            radio.value = start.key;
            radio.checked = (state.chosen.start === start.key);
            card.classList.toggle('is-active', radio.checked);
            tile.appendChild(K.icon(area ? area.icon : 'bi-lightning'));
            words.appendChild(el('span', 'wsf-quick-card-title', start.label));

            if (start.name && (fold(start.name) !== fold(start.label))) {
                words.appendChild(el('span', 'pg-text-meta wsf-quick-card-line', start.name));
            } else if (query && area) {
                words.appendChild(el('span', 'pg-text-meta wsf-quick-card-line', area.label));
            }

            radio.addEventListener('change', function () {
                if (!radio.checked) {
                    return;
                }

                // Another start reads other values: the conditions go.
                if (state.chosen.start !== start.key) {
                    state.chosen = { start: start.key, config: {}, only_when: [] };
                }

                Array.prototype.forEach.call(group.querySelectorAll('.wsf-quick-card'), function (item) {
                    item.classList.toggle('is-active', item === card);
                });
                drawWhen(ui.body.querySelector('.wsf-quick-when'));
                updateNext();
            });

            card.appendChild(radio);
            card.appendChild(tile);
            card.appendChild(words);
            group.appendChild(card);
        });

        list.appendChild(group);
    }

    // "Only when": conditions on the values of the chosen start, each a
    // value, a comparison and what it is compared with.
    function drawWhen(box) {
        var state = ui.state;
        var start = startOf(state.quick, state.chosen.start);
        var fields = (start && Array.isArray(start.fields)) ? start.fields : [];
        var limit = (state.quick.limits && state.quick.limits.only_when) || 5;

        if (!box) {
            return;
        }

        box.textContent = '';
        box.classList.toggle('d-none', !fields.length);

        if (!fields.length) {
            return;
        }

        var label = el('p', 'pg-field-label wsf-quick-when-label', t('fl_quick_only_when'));
        var rows = el('div', 'wsf-quick-when-rows');

        label.appendChild(el('span', 'pg-field-label-note', ' · ' + t('fl_quick_optional')));
        box.appendChild(label);
        box.appendChild(rows);

        state.chosen.only_when.forEach(function (row, index) {
            rows.appendChild(whenRow(start, row, index));
        });

        if (state.chosen.only_when.length < limit) {
            var add = K.button('pg-button pg-button-quiet pg-button-sm wsf-quick-when-add', t('fl_quick_add_condition'), 'bi-plus-lg');

            add.addEventListener('click', function () {
                var field = fields[0];

                state.chosen.only_when.push({ field: field.key, op: opsFor(field)[0], value: '' });
                drawWhen(box);
                updateNext();

                var selects = box.querySelectorAll('.wsf-quick-when-field');

                if (selects.length) {
                    selects[selects.length - 1].focus();
                }
            });
            box.appendChild(add);
        }
    }

    function whenRow(start, row, index) {
        var state = ui.state;
        var line = el('div', 'wsf-quick-when-row');
        var operators = (ui.state.operators && typeof ui.state.operators === 'object') ? ui.state.operators : {};
        var field = fieldOf(start, row.field) || start.fields[0];

        row.field = field.key;
        var pick = K.select(start.fields.map(function (item) { return [item.key, item.label]; }), field.key);
        var op = K.select(opsFor(field).map(function (key) { return [key, operators[key] || key]; }), row.op);
        var value = el('input', 'pg-input pg-input-sm wsf-quick-when-value');
        var remove = K.button('pg-icon-button pg-icon-button-sm', '', 'bi-x-lg', t('fl_quick_remove_condition'));

        pick.className = 'pg-input pg-input-sm wsf-quick-when-field';
        op.className = 'pg-input pg-input-sm wsf-quick-when-op';
        pick.setAttribute('aria-label', t('fl_quick_condition_value'));
        op.setAttribute('aria-label', t('fl_quick_condition_op'));
        value.setAttribute('aria-label', t('fl_quick_condition_with'));
        remove.setAttribute('aria-label', t('fl_quick_remove_condition'));
        value.type = (field.type === 'number') ? 'number' : 'text';
        value.value = row.value || '';
        value.classList.toggle('d-none', BARE.indexOf(row.op) !== -1);

        pick.addEventListener('change', function () {
            var chosen = fieldOf(start, pick.value);

            row.field = pick.value;

            if (opsFor(chosen).indexOf(row.op) === -1) {
                row.op = opsFor(chosen)[0];
            }

            drawWhen(line.closest('.wsf-quick-when'));
        });
        op.addEventListener('change', function () {
            row.op = op.value;
            value.classList.toggle('d-none', BARE.indexOf(row.op) !== -1);
        });
        value.addEventListener('input', function () {
            row.value = value.value;
        });
        remove.addEventListener('click', function () {
            var box = line.closest('.wsf-quick-when');

            state.chosen.only_when.splice(index, 1);
            drawWhen(box);
            updateNext();

            var add = box.querySelector('.wsf-quick-when-add');

            if (add) {
                add.focus();
            }
        });

        line.appendChild(pick);
        line.appendChild(op);
        line.appendChild(value);
        line.appendChild(remove);

        return line;
    }

    function focusIn(selector) {
        var target = ui.body.querySelector(selector);

        if (target) {
            target.focus();
        }
    }

    // Opens the dialog on its first stage. options: operators (key =>
    // label), start (a start's key to begin on), onClose.
    function open(kit, quick, options) {
        if (!kit || !quick || !areas(quick).length) {
            return false;
        }

        options = options || {};
        K = kit;
        t = kit.t;
        el = kit.el;
        ui = ui || make();

        var chosen = startOf(quick, options.start) ? options.start : '';

        ui.state = {
            quick: quick,
            operators: options.operators || {},
            onClose: options.onClose || null,
            stage: 'start',
            area: chosen ? areaOfStart(quick, chosen) : firstArea(quick),
            query: '',
            chosen: { start: chosen, config: {}, only_when: [] }
        };

        draw();
        pgUI.dialog.show(ui.modal);

        return true;
    }

    window.PGWsFlowQuick = {
        open: open,
        pure: pure,
        // A later stage's script: { draw(body, state, kit) }.
        stages: {},
        // The choices made so far, for a later stage.
        choices: function () {
            return ui && ui.state ? choices(ui.state.quick, ui.state.chosen) : null;
        }
    };
}());
