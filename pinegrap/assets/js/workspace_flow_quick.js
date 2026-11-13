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
 * the start (ws_flow_quick_graph()).
 *
 * The second stage: the steps chosen so far in a row, the recipes that fit
 * the start (while no step is chosen) and every step kind the person may
 * add, by its group; a chosen step shows its own setting, drawn by the
 * canvas's field code (window.PGWsFlow.boxForm()) on a graph of the start
 * and the steps before it, so it writes in their values; "One more step"
 * returns to the kinds.
 *
 * The third stage, the summary: the start and the steps in order, a
 * condition's or loop's steps indented under it, each moved, put into the
 * condition or loop above it or taken out, or opened in its setting; the
 * flow's name and "Start working when saved". It sends the choices to
 * ws_flow_quick_setup, which makes a normal draft, and hands what it made
 * to the opener once the dialog has closed.
 *
 * window.PGWsFlowQuick.open(kit, quick, options) opens the dialog: kit is
 * window.PGWsKit, quick the catalogue ws_flow_js_config() serves as
 * flows.quick, options.operators the comparisons' labels (key => label),
 * options.home the channel a new flow is made in, options.onDone called
 * with { flow, published, publish_error, open } once the flow is made (else
 * the Flows screen opens on it).
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

    // The stages in their order, each with its label.
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

    // ── The steps (pure) ────────────────────────────────────────────────

    // The recipes that fit a start: those naming it, and those for any
    // start about a record (ws_flow_quick_recipes_for()).
    function recipesFor(quick, key) {
        var start = startOf(quick, key);

        return start ? ((quick && Array.isArray(quick.recipes)) ? quick.recipes : []).filter(function (recipe) {
            var fits = Array.isArray(recipe.fits) ? recipe.fits : [];

            return (fits.indexOf(start.key) !== -1) || ((fits.indexOf('record') !== -1) && !!start.record);
        }) : [];
    }

    // Every step, held ones after their box.
    function flat(steps) {
        return (Array.isArray(steps) ? steps : []).reduce(function (out, step) {
            return out.concat([step], flat(step.steps));
        }, []);
    }

    // A free id for a new step, step1, step2 and on: never one a step
    // holds, nor the start's or its Read a record box's.
    function freeId(steps) {
        var taken = { start: true, read: true };

        flat(steps).forEach(function (step) {
            taken[step.id] = true;
        });

        for (var n = 1; ; n++) {
            if (!taken['step' + n]) {
                return 'step' + n;
            }
        }
    }

    // A recipe's steps added after those chosen, each a copy with an id,
    // given in order, a held step after its box.
    function fromRecipe(recipe, steps) {
        var out = (Array.isArray(steps) ? steps : []).slice();
        var taken = out.slice();
        var copy = function (list) {
            return (Array.isArray(list) ? list : []).map(function (item) {
                var step = { id: freeId(taken), kind: item.kind, label: '', config: JSON.parse(JSON.stringify(item.config || {})), steps: [] };

                taken.push(step);
                step.steps = copy(item.steps);

                return step;
            });
        };

        return out.concat(recipe ? copy(recipe.steps) : []);
    }

    // Where a step is: the list that holds it, its place there, the steps
    // holding it (outermost first); null for none.
    function placeOf(steps, id, parents) {
        var list = Array.isArray(steps) ? steps : [];

        parents = parents || [];

        for (var index = 0; index < list.length; index++) {
            if (list[index].id === id) {
                return { list: list, index: index, step: list[index], parents: parents };
            }

            var inner = placeOf(list[index].steps, id, parents.concat([list[index]]));

            if (inner) {
                return inner;
            }
        }

        return null;
    }

    // How deep a step's own held steps go, 1 for none.
    function depthOf(step) {
        return 1 + (step.steps || []).reduce(function (most, inner) {
            return Math.max(most, depthOf(inner));
        }, 0);
    }

    function holdsLoop(step) {
        return (step.kind === 'for_each') || (step.steps || []).some(holdsLoop);
    }

    // What a step may do in the Summary: move up or down among its
    // siblings, go into the condition or loop just above it (as
    // ws_flow_quick_steps_read() takes it: within the depth, no loop in a
    // loop), or come out of the one holding it.
    function moves(steps, id, depth) {
        var at = placeOf(steps, id);
        var into = at && (at.index > 0) ? at.list[at.index - 1] : null;
        var held = at ? at.parents.concat(into ? [into] : []) : [];

        return {
            up: !!at && (at.index > 0),
            down: !!at && (at.index < at.list.length - 1),
            into: !!into && ((into.kind === 'condition') || (into.kind === 'for_each'))
                && (at.parents.length + 1 + depthOf(at.step) <= (depth || 3))
                && !(holdsLoop(at.step) && held.some(function (step) { return step.kind === 'for_each'; })),
            out: !!at && (at.parents.length > 0)
        };
    }

    // Moves a step (up, down, into, out) when moves() allows it.
    function move(steps, id, how, depth) {
        var at = placeOf(steps, id);

        if (!at || !moves(steps, id, depth)[how]) {
            return false;
        }

        at.list.splice(at.index, 1);

        if ((how === 'up') || (how === 'down')) {
            at.list.splice(at.index + ((how === 'up') ? -1 : 1), 0, at.step);
        } else if (how === 'into') {
            var holder = at.list[at.index - 1];

            holder.steps = Array.isArray(holder.steps) ? holder.steps : [];
            holder.steps.push(at.step);
        } else {
            var parent = at.parents[at.parents.length - 1];
            var outer = placeOf(steps, parent.id);

            outer.list.splice(outer.index + 1, 0, at.step);
        }

        return true;
    }

    // Takes a step out; the steps it held take its place.
    function remove(steps, id) {
        var at = placeOf(steps, id);

        if (!at) {
            return false;
        }

        Array.prototype.splice.apply(at.list, [at.index, 1].concat(at.step.steps || []));

        return true;
    }

    // The steps in their order with their depth, held ones after their box.
    function rows(steps, depth) {
        depth = depth || 0;

        return (Array.isArray(steps) ? steps : []).reduce(function (out, step) {
            return out.concat([{ step: step, depth: depth }], rows(step.steps, depth + 1));
        }, []);
    }

    // A loop with no step inside, which the server refuses.
    function emptyLoop(steps) {
        return flat(steps).filter(function (step) {
            return (step.kind === 'for_each') && !(step.steps || []).length;
        })[0] || null;
    }

    // What the dialog sends to ws_flow_quick_setup.
    function sent(quick, chosen, options) {
        var picked = choices(quick, chosen);

        if (!picked) {
            return null;
        }

        options = options || {};
        picked.steps = sentSteps(chosen.steps);
        picked.name = String(chosen.name || '').trim().slice(0, 160);
        picked.channel_id = parseInt(options.home, 10) || 0;
        picked.publish = options.publish ? 1 : 0;

        return picked;
    }

    // How many boxes the steps place: a loop's End box is one more.
    function boxCount(steps) {
        return flat(steps).reduce(function (count, step) {
            return count + ((step.kind === 'for_each') ? 2 : 1);
        }, 0);
    }

    // The steps as the dialog sends them (ws_flow_quick_steps_read()).
    function sentSteps(steps) {
        return (Array.isArray(steps) ? steps : []).map(function (step) {
            return { id: step.id, kind: step.kind, label: step.label || '', config: step.config || {}, steps: sentSteps(step.steps) };
        });
    }

    // The graph a step's setting reads its values from, laid as
    // ws_flow_quick_graph() lays the flow: the start, its Read a record box
    // (the server places it only when a value of it is used), then the steps
    // one after the other, a condition's on yes, a loop's on each up to its
    // End box. A box's settings are the step's own, so the setting writes
    // into the step. firstOut(kind) gives the outcome a box goes on from.
    function graphOf(quick, chosen, firstOut) {
        var picked = choices(quick, chosen);

        if (!picked) {
            return null;
        }

        var start = startOf(quick, picked.start);
        var nodes = [{ id: 'start', kind: start.kind, label: '', x: 0, y: 0, config: picked.config }];
        var edges = [];
        var column = 1;
        var ends = 0;
        var into = [['start', 'next']];
        var place = function (id, kind, label, config, depth) {
            nodes.push({ id: id, kind: kind, label: label || '', x: column * 256, y: (depth - 1) * 112, config: config });
            column++;
        };
        var lead = function (from, to) {
            from.forEach(function (arrow) {
                edges.push({ from: arrow[0], to: to, on: arrow[1] });
            });
        };
        var chain = function (steps, arrows, depth) {
            (Array.isArray(steps) ? steps : []).forEach(function (step) {
                step.config = (step.config && (typeof step.config === 'object') && !Array.isArray(step.config)) ? step.config : {};
                place(step.id, step.kind, step.label, step.config, depth);
                lead(arrows, step.id);

                if (step.kind === 'condition') {
                    arrows = (step.steps && step.steps.length) ? chain(step.steps, [[step.id, 'yes']], depth + 1).concat([[step.id, 'no']]) : [[step.id, 'yes']];
                } else if (step.kind === 'for_each') {
                    var inside = chain(step.steps, [[step.id, 'each']], depth + 1);
                    var end = 'loop_end' + (++ends);

                    place(end, 'for_each_end', '', { for_each: step.id, collect: '' }, depth);
                    lead(inside, end);
                    arrows = [[end, 'done']];
                } else {
                    arrows = [[step.id, firstOut(step.kind) || 'done']];
                }
            });

            return arrows;
        };

        if (start.read) {
            place('read', 'record_get', '', JSON.parse(JSON.stringify(start.read)), 1);
            lead(into, 'read');
            into = [['read', firstOut('record_get') || 'done']];
        }

        chain(chosen.steps, into, 1);

        return { nodes: nodes, edges: edges };
    }

    var pure = {
        recipesFor: recipesFor,
        freeId: freeId,
        fromRecipe: fromRecipe,
        boxCount: boxCount,
        sentSteps: sentSteps,
        graphOf: graphOf,
        placeOf: placeOf,
        moves: moves,
        move: move,
        remove: remove,
        rows: rows,
        emptyLoop: emptyLoop,
        sent: sent,
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
        var more = K.button('pg-button pg-button-quiet d-none', t('fl_quick_more'), 'bi-plus-lg');
        var canvas = K.button('pg-button pg-button-quiet d-none', t('fl_quick_open_canvas'), 'bi-diagram-3');
        var save = K.button('pg-button pg-button-fill d-none', t('fl_quick_start_flow'), 'bi-play-fill');

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
        foot.appendChild(more);
        foot.appendChild(canvas);
        foot.appendChild(next);
        foot.appendChild(save);
        modal.appendChild(head);
        modal.appendChild(stages);
        modal.appendChild(body);
        modal.appendChild(foot);
        document.body.appendChild(modal);

        back.addEventListener('click', function () { go(-1); });
        next.addEventListener('click', function () { go(1); });
        more.addEventListener('click', function () { oneMore(); });
        canvas.addEventListener('click', function () { finish(true); });
        save.addEventListener('click', function () { finish(false); });

        pgUI.dialog.on(modal, 'hidden', function () {
            var done = ui.state && ui.state.onClose;

            closeForm();
            ui.state = null;

            if (typeof done === 'function') {
                done();
            }
        });

        return { modal: modal, body: body, stages: stages, back: back, next: next, more: more, canvas: canvas, save: save, hint: hint, form: null, state: null };
    }

    // The stage the dialog shows, and the next one.
    function stageIndex() {
        return STAGES.indexOf(ui.state.stage);
    }

    function nextStage() {
        var at = stageIndex() + 1;

        return (at < STAGES.length) ? STAGES[at] : '';
    }

    // May the dialog go on from the stage it is on: a start chosen, and on
    // the steps at least one step.
    function ready() {
        var state = ui.state;

        return !!choices(state.quick, state.chosen) && ((state.stage !== 'steps') || (state.chosen.steps.length > 0));
    }

    function go(step) {
        var state = ui.state;

        if (!state || state.busy) {
            return;
        }

        state.error = '';

        if (step > 0) {
            var to = nextStage();

            if (!to || !ready()) {
                return;
            }

            state.stage = to;
            state.at = (to === 'steps') ? (state.chosen.steps.length ? state.chosen.steps[0].id : '') : state.at;
        } else if ((state.stage === 'steps') && (state.at === '') && state.chosen.steps.length) {
            // Back from the kinds shows the last step chosen.
            state.at = flat(state.chosen.steps).slice(-1)[0].id;
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
            var key = STAGES[index];
            var number = numberOf(state.at);

            item.lastChild.textContent = t(STAGE_LABELS[key]) + (((key === 'steps') && (index === at)) ? ' · ' + t('fl_quick_step_n', number) : '');
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
        closeForm();
        ui.body.textContent = '';

        if (state.stage === 'start') {
            drawStart();
        } else if (state.stage === 'steps') {
            drawSteps();
        } else {
            drawSummary();
        }

        updateNext();
    }

    function updateNext() {
        var state = ui.state;

        ui.next.classList.toggle('d-none', nextStage() === '');
        ui.next.disabled = !ready();
        ui.more.classList.toggle('d-none', (state.stage !== 'steps') || (state.at === ''));
        ui.more.disabled = !roomFor('');
        ui.more.title = ui.more.disabled ? t('fl_quick_too_many', limitOf('boxes', 60)) : '';
        ui.canvas.classList.toggle('d-none', state.stage !== 'summary');
        ui.save.classList.toggle('d-none', state.stage !== 'summary');
        ui.canvas.disabled = state.busy;
        ui.save.disabled = state.busy || !state.chosen.steps.length;
        ui.save.lastChild.textContent = state.chosen.publish ? t('fl_quick_start_flow') : t('fl_quick_save_flow');
        ui.back.disabled = state.busy;
    }

    // Whether one more box of the kind fits: the start, the steps and it, a
    // loop with its End box, within the catalogue's limit.
    function roomFor(kind) {
        return boxCount(ui.state.chosen.steps) + 1 + ((kind === 'for_each') ? 2 : 1) <= limitOf('boxes', 60);
    }

    function limitOf(key, fallback) {
        var limits = (ui.state.quick && ui.state.quick.limits) || {};

        return limits[key] || fallback;
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
                    state.chosen = { start: start.key, config: {}, only_when: [], steps: [], name: '', publish: true };
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

    // ── The second stage: the steps ─────────────────────────────────────

    // The canvas's own words for a kind: its name and group (the colour of
    // its tile), the outcome it goes on from. The canvas script is served
    // first in the bundle.
    function kindOf(kind) {
        var flow = window.PGWsFlow;

        return (flow && flow.boxKind) ? (flow.boxKind(kind) || { kind: kind, name: kind, group: '', out: '' }) : { kind: kind, name: kind, group: '', out: '' };
    }

    // The kind's line icon on a tile in its group's colour.
    function kindTile(kind, extra) {
        var info = kindOf(kind);
        var tile = el('span', 'pg-icon-tile wsf-quick-tile' + (info.group ? ' wsf-group-' + info.group : '') + (extra ? ' ' + extra : ''));

        tile.appendChild(K.flowIcon(kind));

        return tile;
    }

    // The kinds of a group the palette offers here (a row box with a
    // table, an MCP box with a server, Pinegrap AI set up).
    function offeredKinds(group) {
        var flow = window.PGWsFlow;

        return (group.kinds || []).filter(function (kind) {
            return !flow || !flow.boxOffered || flow.boxOffered(kind);
        });
    }

    // The place of a step among all of them, 1 first; the place after the
    // last while a kind is chosen.
    function numberOf(id) {
        var all = flat(ui.state.chosen.steps);
        var at = all.map(function (step) { return step.id; }).indexOf(id);

        return (at === -1) ? all.length + 1 : at + 1;
    }

    function drawSteps() {
        var state = ui.state;
        var pane = el('div', 'wsf-quick-steps');
        var at = placeOf(state.chosen.steps, state.at);

        state.at = at ? state.at : '';

        if (state.chosen.steps.length) {
            pane.appendChild(drawTrail());
        }

        if (!at) {
            drawKinds(pane);
        } else {
            drawStep(pane, at.step);
        }

        ui.body.appendChild(pane);
    }

    // The steps chosen so far, in their order (held ones after their box):
    // each opens its setting; the last place is the step being chosen.
    function drawTrail() {
        var state = ui.state;
        var trail = el('ol', 'wsf-quick-trail');

        trail.setAttribute('aria-label', t('fl_quick_steps_chosen'));

        flat(state.chosen.steps).forEach(function (step, index) {
            var item = el('li', '');
            var go = K.button('wsf-quick-trail-step', '');
            var on = step.id === state.at;

            go.appendChild(el('span', 'wsf-quick-trail-n', String(index + 1)));
            go.appendChild(kindTile(step.kind));
            go.appendChild(el('span', 'wsf-quick-trail-name', step.label || kindOf(step.kind).name));
            go.classList.toggle('is-active', on);

            if (on) {
                go.setAttribute('aria-current', 'step');
            }

            go.addEventListener('click', function () {
                state.at = step.id;
                draw();
                focusIn('.wsf-quick-trail-step.is-active');
            });
            item.appendChild(go);
            trail.appendChild(item);
        });

        if (state.at === '') {
            var next = el('li', 'wsf-quick-trail-new');

            next.setAttribute('aria-current', 'step');
            next.appendChild(K.icon('bi-plus-lg'));
            next.appendChild(document.createTextNode(' ' + t('fl_quick_step_n', numberOf(''))));
            trail.appendChild(next);
        }

        return trail;
    }

    // What comes next: the recipes that fit the start while no step is
    // chosen, then every kind the person may add, by its group.
    function drawKinds(pane) {
        var state = ui.state;
        var recipes = state.chosen.steps.length ? [] : recipesFor(state.quick, state.chosen.start);
        var heading = el('h3', 'pg-title-status wsf-quick-lead', t('fl_quick_next_what'));

        heading.id = 'wsf-quick-next-what';
        heading.tabIndex = -1;
        pane.appendChild(heading);
        pane.appendChild(el('p', 'pg-text-2 wsf-quick-lead-sub', recipes.length ? t('fl_quick_next_help') : t('fl_quick_next_help_kinds')));

        if (recipes.length) {
            var shelf = el('div', 'wsf-quick-recipes');

            shelf.setAttribute('role', 'group');
            shelf.setAttribute('aria-label', t('fl_quick_recipes'));
            recipes.forEach(function (recipe) {
                var card = K.button('wsf-quick-recipe', '');
                var chain = el('span', 'wsf-quick-recipe-chain');

                chain.setAttribute('aria-hidden', 'true');
                card.appendChild(el('span', 'wsf-quick-card-title', recipe.title));
                (recipe.steps || []).forEach(function (step, index) {
                    if (index) {
                        chain.appendChild(K.icon('bi-chevron-right'));
                    }

                    chain.appendChild(kindTile(step.kind, 'wsf-quick-tile-sm'));
                });
                card.appendChild(chain);
                card.addEventListener('click', function () {
                    state.chosen.steps = fromRecipe(recipe, state.chosen.steps);
                    state.at = state.chosen.steps.length ? state.chosen.steps[0].id : '';
                    draw();
                    focusIn('.wsf-quick-trail-step.is-active');
                });
                shelf.appendChild(card);
            });
            pane.appendChild(shelf);
        }

        (state.quick.groups || []).forEach(function (group) {
            var kinds = offeredKinds(group);

            if (!kinds.length) {
                return;
            }

            var row = el('div', 'wsf-quick-group');
            var name = el('span', 'pg-text-meta wsf-quick-group-name', group.label);
            var acts = el('div', 'wsf-quick-acts');

            name.id = 'wsf-quick-group-' + group.key;
            acts.setAttribute('role', 'group');
            acts.setAttribute('aria-labelledby', name.id);
            kinds.forEach(function (kind) {
                var act = K.button('wsf-quick-act', '');

                act.appendChild(kindTile(kind, 'wsf-quick-tile-sm'));
                act.appendChild(el('span', '', kindOf(kind).name));
                act.addEventListener('click', function () { addStep(kind); });
                acts.appendChild(act);
            });
            row.appendChild(name);
            row.appendChild(acts);
            pane.appendChild(row);
        });
    }

    // A step of the kind after those chosen, its settings as the palette
    // gives a new box; its setting is shown.
    function addStep(kind) {
        var state = ui.state;
        var flow = window.PGWsFlow;
        var step = { id: freeId(state.chosen.steps), kind: kind, label: '', config: {}, steps: [] };

        if (!roomFor(kind)) {
            K.toast(t('fl_quick_too_many', limitOf('boxes', 60)), 'warning');
            return;
        }

        state.chosen.steps.push(step);
        step.config = (flow && flow.boxConfig) ? flow.boxConfig(kind, graphOf(state.quick, state.chosen, firstOut)) : {};
        state.at = step.id;
        draw();
        focusIn('.wsf-quick-step-head h3');
    }

    function firstOut(kind) {
        return kindOf(kind).out;
    }

    // A step's own setting: the canvas's fields for its box, on a graph of
    // the start and the steps, so it writes in the values before it.
    function drawStep(pane, step) {
        var state = ui.state;
        var head = el('div', 'wsf-quick-step-head');
        var title = el('h3', 'pg-title-status', kindOf(step.kind).name);
        var drop = K.button('pg-button pg-button-quiet pg-button-sm', t('fl_quick_remove_step'), 'bi-trash');
        var host = el('div', 'wsf-quick-form');
        var flow = window.PGWsFlow;

        title.tabIndex = -1;
        head.appendChild(kindTile(step.kind));
        head.appendChild(title);
        head.appendChild(drop);
        pane.appendChild(head);
        pane.appendChild(host);

        drop.addEventListener('click', function () {
            var all = flat(state.chosen.steps);
            var place = all.indexOf(step);

            remove(state.chosen.steps, step.id);
            all = flat(state.chosen.steps);
            state.at = all.length ? all[Math.min(place, all.length - 1)].id : '';
            draw();
            focusIn(state.at === '' ? '.wsf-quick-act' : '.wsf-quick-trail-step.is-active');
        });

        ui.form = (flow && flow.boxForm) ? flow.boxForm(host, {
            graph: graphOf(state.quick, state.chosen, firstOut),
            node: step.id,
            onChange: function (node) {
                step.label = node.label || '';
                step.config = node.config;

                var name = ui.body.querySelector('.wsf-quick-trail-step.is-active .wsf-quick-trail-name');

                if (name) {
                    name.textContent = step.label || kindOf(step.kind).name;
                }
            }
        }) : null;
    }

    // ── The third stage: the summary ────────────────────────────────────

    // The flow as it will be made: the start with its "Only when" lines,
    // the steps in order (a condition's or a loop's indented under it), each
    // moved, put into the condition or loop above it, taken out, or changed
    // with its own setting; the flow's name and whether it starts working.
    function drawSummary() {
        var state = ui.state;
        var start = startOf(state.quick, state.chosen.start);
        var pane = el('div', 'wsf-quick-steps');
        var heading = el('h3', 'pg-title-status wsf-quick-lead', t('fl_quick_ready'));
        var line = el('ol', 'wsf-quick-line');
        var depth = limitOf('depth', 3);

        heading.tabIndex = -1;
        pane.appendChild(heading);
        pane.appendChild(el('p', 'pg-text-2 wsf-quick-lead-sub', t('fl_quick_ready_help')));
        line.setAttribute('aria-label', t('fl_quick_stage_steps'));

        var area = areas(state.quick).filter(function (item) { return item.key === areaOfStart(state.quick, start.key); })[0];
        var startTile = el('span', 'pg-icon-tile pg-icon-tile-lg');

        startTile.appendChild(K.icon(area ? area.icon : 'bi-lightning'));
        line.appendChild(summaryLine(startTile, start.label, whenLines(start), 0, [
            tool('bi-pencil', t('fl_quick_change_start'), 'start', function () {
                state.stage = 'start';
                draw();
            })
        ]));

        rows(state.chosen.steps).forEach(function (row) {
            var step = row.step;
            var can = moves(state.chosen.steps, step.id, depth);
            var name = step.label || kindOf(step.kind).name;
            var shift = function (how) {
                return function () {
                    move(state.chosen.steps, step.id, how, depth);
                    drawAgain(step.id, how);
                };
            };
            var tools = [];

            if (can.up) {
                tools.push(tool('bi-arrow-up', t('fl_quick_move_up', name), 'up', shift('up')));
            }

            if (can.down) {
                tools.push(tool('bi-arrow-down', t('fl_quick_move_down', name), 'down', shift('down')));
            }

            if (can.into) {
                tools.push(tool('bi-arrow-return-right', t('fl_quick_move_into', name), 'into', shift('into')));
            }

            if (can.out) {
                tools.push(tool('bi-arrow-return-left', t('fl_quick_move_out', name), 'out', shift('out')));
            }

            tools.push(tool('bi-pencil', t('fl_quick_change_step', name), 'edit', function () {
                state.stage = 'steps';
                state.at = step.id;
                draw();
                focusIn('.wsf-quick-step-head h3');
            }));

            var item = summaryLine(kindTile(step.kind, 'pg-icon-tile-lg'), name, [boxMeta(step)].filter(Boolean), row.depth, tools);

            item.setAttribute('data-step', step.id);
            line.appendChild(item);
        });

        pane.appendChild(line);

        var add = K.button('pg-button pg-button-ghost pg-button-sm wsf-quick-add', t('fl_quick_add_step'), 'bi-plus-lg');

        add.disabled = !roomFor('');
        add.addEventListener('click', function () {
            state.stage = 'steps';
            state.at = '';
            draw();
            focusIn('.wsf-quick-lead');
        });
        pane.appendChild(add);
        pane.appendChild(nameRow(start));

        if (state.error) {
            var error = el('div', 'alert theme-danger wsf-quick-error', state.error);

            error.setAttribute('role', 'alert');
            pane.appendChild(error);
        }

        ui.body.appendChild(pane);
    }

    // One line of the summary: its tile, title, the lines under it, its
    // depth and its buttons.
    function summaryLine(tile, title, lines, depth, tools) {
        var item = el('li', 'wsf-quick-event');
        var words = el('div', 'wsf-quick-event-words');
        var bar = el('div', 'wsf-quick-event-tools');

        item.style.setProperty('--wsf-quick-depth', String(depth));
        words.appendChild(el('span', 'wsf-quick-card-title', title));
        lines.forEach(function (text) {
            words.appendChild(el('span', 'pg-text-meta wsf-quick-card-line', text));
        });
        tools.forEach(function (button) {
            bar.appendChild(button);
        });
        item.appendChild(tile);
        item.appendChild(words);
        item.appendChild(bar);

        return item;
    }

    function tool(iconName, label, how, onClick) {
        var button = K.button('pg-icon-button pg-icon-button-sm', '', iconName, label);

        button.setAttribute('data-how', how);
        button.addEventListener('click', onClick);

        return button;
    }

    // Drawn again after a move, the focus on the same button of the step
    // moved, or its pencil when that button is gone.
    function drawAgain(id, how) {
        draw();

        var item = ui.body.querySelector('[data-step="' + id + '"]');
        var target = item ? (item.querySelector('[data-how="' + how + '"]') || item.querySelector('[data-how="edit"]')) : null;

        if (target) {
            target.focus();
        }
    }

    // The "Only when" conditions in words.
    function whenLines(start) {
        var state = ui.state;
        var operators = state.operators || {};

        return onlyWhen(state.quick, start, state.chosen.only_when).map(function (row) {
            var field = fieldOf(start, row.field);

            return t('fl_quick_only_when_line', [field ? field.label : row.field, operators[row.op] || row.op, row.value].join(' ').trim());
        });
    }

    // What a step does in a few words, as the canvas's box says it.
    function boxMeta(step) {
        var flow = window.PGWsFlow;

        return (flow && flow.boxMeta) ? flow.boxMeta({ id: step.id, kind: step.kind, label: step.label, config: step.config }) : '';
    }

    // The flow's name and whether it starts working once made.
    function nameRow(start) {
        var state = ui.state;
        var row = el('div', 'wsf-quick-name');
        var named = el('div', '');
        var label = el('label', 'pg-field-label', t('fl_quick_name'));
        var input = el('input', 'pg-input');
        var tick = el('label', 'wsf-quick-publish');
        var knob = el('span', 'switch');
        var box = el('input', '');

        input.id = 'wsf-quick-name';
        input.type = 'text';
        input.maxLength = 160;
        input.value = state.chosen.name;
        input.placeholder = start.label;
        label.htmlFor = input.id;
        input.addEventListener('input', function () {
            state.chosen.name = input.value;
        });
        box.type = 'checkbox';
        box.setAttribute('role', 'switch');
        box.checked = state.chosen.publish;
        box.addEventListener('change', function () {
            state.chosen.publish = box.checked;
            updateNext();
        });
        knob.appendChild(box);
        tick.appendChild(knob);
        tick.appendChild(document.createTextNode(' ' + t('fl_quick_publish')));
        named.appendChild(label);
        named.appendChild(input);
        row.appendChild(named);
        row.appendChild(tick);

        return row;
    }

    // Makes the flow (ws_flow_quick_setup): to open it on the canvas as a
    // draft, or to save it, started when chosen. The dialog closes first;
    // the opener then gets { flow, published, publish_error, open }.
    function finish(open) {
        var state = ui.state;

        if (!state || state.busy || (state.stage !== 'summary')) {
            return;
        }

        var loop = emptyLoop(state.chosen.steps);

        if (loop) {
            state.error = t('fl_quick_loop_empty', loop.label || kindOf(loop.kind).name);
            drawSummary.again();
            return;
        }

        var data = sent(state.quick, state.chosen, { home: state.home, publish: !open && state.chosen.publish });

        state.busy = true;
        state.error = '';
        updateNext();

        K.api('ws_flow_quick_setup', data).then(function (answer) {
            var done = state.onDone;
            var result = { flow: answer.flow, published: !!answer.published, publish_error: answer.publish_error || '', open: open };

            state.busy = false;

            return Promise.resolve(pgUI.dialog.hide(ui.modal)).then(function () {
                if (done) {
                    done(result);
                } else if (result.flow && result.flow.id) {
                    window.location.href = 'workspace_flows.php?flow=' + encodeURIComponent(result.flow.id);
                }
            });
        }).catch(function (error) {
            if (ui.state !== state) {
                return;
            }

            state.busy = false;
            state.error = (error && error.message) || t('fl_quick_failed');
            drawSummary.again();
        });
    }

    // The summary drawn again with its error, the focus on it.
    drawSummary.again = function () {
        if (!ui.state || (ui.state.stage !== 'summary')) {
            return;
        }

        draw();

        var error = ui.body.querySelector('.wsf-quick-error');

        if (error) {
            error.tabIndex = -1;
            error.focus();
        }
    };

    function closeForm() {
        if (ui && ui.form) {
            ui.form.destroy();
            ui.form = null;
        }
    }

    // "One more step": back to the kinds, for the step after the last.
    function oneMore() {
        var state = ui.state;

        if (!state || (state.stage !== 'steps') || ui.more.disabled) {
            return;
        }

        state.at = '';
        draw();
        focusIn('.wsf-quick-lead');
    }

    function focusIn(selector) {
        var target = ui.body.querySelector(selector);

        if (target) {
            target.focus();
        }
    }

    // Opens the dialog on its first stage. options: operators (key =>
    // label), start (a start's key to begin on), home, onDone, onClose.
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
            // The id of the step whose setting is shown, '' while a kind is
            // chosen.
            at: '',
            home: parseInt(options.home, 10) || 0,
            onDone: (typeof options.onDone === 'function') ? options.onDone : null,
            busy: false,
            error: '',
            chosen: { start: chosen, config: {}, only_when: [], steps: [], name: '', publish: true }
        };

        draw();
        pgUI.dialog.show(ui.modal);

        return true;
    }

    window.PGWsFlowQuick = {
        open: open,
        pure: pure,
        // The choices made so far, as the dialog would send them.
        choices: function () {
            return ui && ui.state ? sent(ui.state.quick, ui.state.chosen, { home: ui.state.home, publish: ui.state.chosen.publish }) : null;
        }
    };
}());
