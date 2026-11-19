/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the roadmap of a person's own plan, inside the plan drawer of
 * My calendar (workspace_my_calendar.js): dated steps, a bar with each step
 * on its day and the progress, "Make a task" on a step. The steps are read
 * and saved by includes/workspace/personal_plan_steps.php. "Save as team
 * template" keeps the roadmap for the team (roadmap_templates.php), and
 * "Move the following steps too" shifts the later steps with a changed day.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    function fill(template, values) {
        return String(template || '').replace(/\{(\d+)\}/g, function (all, index) {
            return values[parseInt(index, 10) - 1];
        });
    }

    function dayNumber(date) {
        var parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date || '');

        return parts ? Date.UTC(+parts[1], +parts[2] - 1, +parts[3]) / 86400000 : null;
    }

    function dayOf(number) {
        return new Date(number * 86400000).toISOString().slice(0, 10);
    }

    /**
     * "Move the following steps too": the steps after the one at index whose
     * day went from before to after move by the same number of calendar
     * days; a step with no day keeps none. Pure: a new list is returned.
     *
     * @param {Object[]} steps  the steps in order, each with a day (Y-m-d or '')
     * @param {number} index    the step whose day changed
     * @param {string} before   its old day
     * @param {string} after    its new day
     * @return {Object[]}
     */
    function shiftFollowing(steps, index, before, after) {
        var from = dayNumber(before);
        var to = dayNumber(after);
        var moved = (from === null || to === null) ? 0 : to - from;

        return steps.map(function (step, at) {
            var copy = Object.assign({}, step);
            var own = dayNumber(step.day);

            if (at === index) {
                copy.day = after;
            } else if (at > index && moved && own !== null) {
                copy.day = dayOf(own + moved);
            }

            return copy;
        });
    }

    /**
     * The roadmap part of the drawer.
     *
     * @param {Object} h     the screen's helpers
     * @param {Function} s   the screen's texts
     * @param {Object|null} plan the plan opened, null for a new one
     * @param {Object} hooks range(): the drawer's first and last day;
     *                       saveAll(): saves the plan and its steps, resolving
     *                       with the roadmap; reopen(planId): draws the drawer again
     * @return {Object} node, save(planId)
     */
    function section(h, s, plan, hooks) {
        var el = h.el;
        // Every field comes from the kit's form parts, as the drawer's do.
        var part = h.form;
        var editable = !plan || plan.mine !== false;
        var steps = [];
        var root = el('section', 'ws-roadmap');
        var head = el('div', 'ws-roadmap-head');
        var count = el('span', 'pg-text-meta pg-tabular');
        var bar = el('div', 'ws-roadmap-bar');
        var fillBar = el('div', 'ws-roadmap-fill');
        var marks = el('div', 'ws-roadmap-marks');
        var list = el('ol', 'ws-roadmap-steps');
        var add = el('button', 'pg-button pg-button-quiet pg-button-sm', '');
        var foot = el('div', 'ws-roadmap-foot');
        var moveSwitch = part.switchRow(s('steps_move_following'), false);
        var moveRow = moveSwitch.row;
        var move = moveSwitch.input;
        var keep = el('button', 'pg-button pg-button-quiet pg-button-sm', '');
        var chooser = el('div', 'ws-roadmap-keep d-none');

        head.appendChild(el('h3', 'pg-group-label', s('roadmap')));
        head.appendChild(count);
        root.appendChild(head);

        bar.setAttribute('role', 'progressbar');
        bar.setAttribute('aria-valuemin', '0');
        bar.setAttribute('aria-valuemax', '100');
        // The dots repeat the steps below; a screen reader hears the count.
        marks.setAttribute('aria-hidden', 'true');
        bar.appendChild(fillBar);
        bar.appendChild(marks);
        root.appendChild(bar);
        root.appendChild(list);

        add.type = 'button';
        add.appendChild(h.icon('bi-plus-lg', 'me-1'));
        add.appendChild(document.createTextNode(s('step_add')));
        add.addEventListener('click', function () {
            steps.push({ id: 0, title: '', day: '', done: false, follows: false, task_id: 0 });
            drawSteps();
            var inputs = list.querySelectorAll('.ws-roadmap-title');

            if (inputs.length) {
                inputs[inputs.length - 1].focus();
            }
        });

        moveRow.classList.add('ws-roadmap-move');
        root.appendChild(moveRow);
        var moveHelp = el('div', 'pg-text-meta ws-roadmap-move-help', s('steps_move_help'));
        root.appendChild(moveHelp);

        // "Save as team template": the owner's saved plan only.
        keep.type = 'button';
        keep.appendChild(h.icon('bi-signpost-2', 'me-1'));
        keep.appendChild(document.createTextNode(s('template_save')));
        keep.addEventListener('click', function () {
            keep.disabled = true;
            h.api('ws_roadmap_templates', {}).then(function (result) {
                var mine = (result.templates || []).filter(function (one) { return one.mine; });

                keep.disabled = false;

                if (!mine.length) {
                    keepAs(0);
                } else {
                    choose(mine);
                }
            }).catch(function (error) {
                keep.disabled = false;
                h.fail(error);
            });
        });

        foot.appendChild(add);
        foot.appendChild(keep);
        root.appendChild(foot);
        root.appendChild(chooser);

        // ── the team template ──

        function keepAs(templateId) {
            hooks.saveAll().then(function (roadmap) {
                if (!roadmap) {
                    return;
                }

                return h.api('ws_roadmap_template_save', { plan_id: roadmap.plan_id, template_id: templateId }).then(function (result) {
                    h.toast(fill(s('template_saved'), [result.title]), 'success');
                    hooks.reopen(roadmap.plan_id);
                });
            }).catch(h.fail);
        }

        // A maker with templates keeps a new one or puts this roadmap in
        // place of one of theirs.
        function choose(mine) {
            var pick = part.select([['0', s('template_save_new')]].concat(mine.map(function (one) {
                return [String(one.id), fill(s('template_save_over'), [one.title])];
            })), '0', true);
            var go = el('button', 'pg-button pg-button-quiet pg-button-sm', s('template_keep'));

            h.clear(chooser);
            pick.setAttribute('aria-label', s('template_save'));
            var cancel = el('button', 'pg-button pg-button-ghost pg-button-sm', s('template_cancel'));

            go.type = 'button';
            go.addEventListener('click', function () {
                go.disabled = true;
                keepAs(parseInt(pick.value, 10) || 0);
            });
            // One save at a time: the first button gives way while the
            // chooser is open, and Cancel brings it back.
            cancel.type = 'button';
            cancel.addEventListener('click', function () {
                chooser.classList.add('d-none');
                h.clear(chooser);
                keep.classList.remove('d-none');
                keep.focus();
            });
            chooser.appendChild(pick);
            chooser.appendChild(go);
            chooser.appendChild(cancel);
            chooser.classList.remove('d-none');
            keep.classList.add('d-none');
            pick.focus();
        }

        function drawFoot() {
            var dated = steps.filter(function (step) { return dayNumber(step.day) !== null; }).length;

            add.classList.toggle('d-none', !editable);
            moveRow.classList.toggle('d-none', !editable || dated < 2);
            moveHelp.classList.toggle('d-none', !editable || dated < 2);
            keep.classList.toggle('d-none', !editable || !plan || !plan.id || !steps.length || !chooser.classList.contains('d-none'));
            foot.classList.toggle('d-none', !editable);
        }

        // ── the bar: the plan's days, each dated step on its day ──

        function drawBar() {
            var kept = steps.filter(function (step) { return step.title.trim() !== ''; });
            var done = kept.filter(function (step) { return step.done; }).length;
            var percent = kept.length ? Math.round(done * 100 / kept.length) : 0;
            var range = hooks.range();
            var first = dayNumber(range.start);
            var last = dayNumber(range.end);
            var width = (first !== null && last !== null && last > first) ? last - first : 0;

            // The fill and the dots share one scale, the plan's days: the fill
            // ends at the dot of the last step of the leading done run.
            var reach = 0;
            var leading = true;

            function placeOf(step) {
                var at = dayNumber(step.day);

                if (at === null || first === null) {
                    return null;
                }

                // A day outside the plan's days stands at the bar's end.
                return width ? Math.min(100, Math.max(0, (at - first) * 100 / width)) : 50;
            }

            kept.forEach(function (step) {
                leading = leading && step.done;

                if (leading && placeOf(step) !== null) {
                    reach = Math.max(reach, placeOf(step));
                }
            });

            count.textContent = kept.length ? fill(s('step_progress'), [done, kept.length]) : '';
            fillBar.style.width = reach + '%';
            bar.setAttribute('aria-valuenow', String(percent));
            bar.setAttribute('aria-valuetext', count.textContent);
            bar.setAttribute('aria-label', s('roadmap'));
            bar.classList.toggle('d-none', !kept.length);
            h.clear(marks);

            kept.forEach(function (step) {
                var place = placeOf(step);

                if (place === null) {
                    return;
                }

                var mark = el('span', 'ws-roadmap-mark' + (step.done ? ' is-done' : ''));

                mark.style.left = place + '%';
                mark.title = step.title + ' · ' + h.dmy(step.day);
                marks.appendChild(mark);
            });
        }

        // ── the steps, one row each ──

        function makeTask(index) {
            // A step with no title is not kept, so the saved list is shorter.
            var kept = steps.slice(0, index).filter(function (step) { return step.title.trim() !== ''; }).length;

            hooks.saveAll().then(function (roadmap) {
                var step = roadmap && roadmap.steps[kept];

                if (!step) {
                    return;
                }

                h.taskDrawer(0, { title: step.title, due_date: step.day || '', assignees: [h.cfg.me] }, function (taskId) {
                    if (!taskId) {
                        return;
                    }

                    h.api('ws_personal_plan_step_task', { plan_id: roadmap.plan_id, step_id: step.id, task_id: taskId }).then(function () {
                        h.toast(s('step_task_linked'), 'success');
                        hooks.reopen(roadmap.plan_id);
                    }).catch(h.fail);
                });
            }).catch(h.fail);
        }

        function stepRow(step, index) {
            var row = el('li', 'ws-roadmap-step' + (step.done ? ' is-done' : ''));
            var done = el('input', 'check');
            var title = part.input('text', step.title, true);
            var day = part.input('date', step.day || '', true);
            var tools = el('div', 'ws-roadmap-tools');
            var meta = el('div', 'ws-roadmap-meta');

            done.type = 'checkbox';
            done.checked = !!step.done;
            done.disabled = !editable || step.follows;

            // A tied step's mark is its task's: said on the greyed box.
            if (step.follows) {
                done.title = s('step_follows');
                done.setAttribute('aria-description', s('step_follows'));
            }
            done.setAttribute('aria-label', s('step_done'));
            done.addEventListener('change', function () {
                step.done = done.checked;
                row.classList.toggle('is-done', step.done);
                drawBar();
            });
            row.appendChild(done);

            title.classList.add('ws-roadmap-title');
            title.maxLength = 255;
            title.placeholder = s('step_title');
            title.disabled = !editable;
            title.setAttribute('aria-label', s('step_title'));
            title.addEventListener('input', function () {
                step.title = title.value;
                drawBar();
            });
            row.appendChild(title);

            day.classList.add('ws-roadmap-day');
            day.disabled = !editable;
            // Named on the box people see, as the title box beside it is.
            day.placeholder = s('step_day');
            day.setAttribute('aria-label', s('step_day'));
            day.addEventListener('change', function () {
                var before = step.day;

                step.day = day.value;

                if (move.checked && before && day.value && index < steps.length - 1) {
                    steps = shiftFollowing(steps, index, before, day.value);
                    drawSteps();

                    // The drawn date box, or the input itself before it is drawn.
                    var again = list.querySelectorAll('.ws-roadmap-step')[index];
                    var box = again && (again.querySelector('.pg-date-box') || again.querySelector('.ws-roadmap-day'));

                    if (box) {
                        box.focus();
                    }

                    return;
                }

                drawBar();
                drawFoot();
            });
            row.appendChild(day);

            if (step.task_id) {
                var task = el('button', 'pg-pill pg-tone-blue ws-roadmap-task');
                task.type = 'button';
                task.title = s('step_open_task');
                task.appendChild(document.createTextNode(step.task_number + ' · ' + h.statusName(step.task_status)));
                task.addEventListener('click', function () { h.openTask(step.task_id); });
                meta.appendChild(task);
            } else if (step.follows) {
                meta.appendChild(el('span', 'pg-text-meta', s('step_follows')));
            } else if (editable && plan && plan.id) {
                var make = el('button', 'pg-icon-button pg-icon-button-sm');
                make.type = 'button';
                make.title = s('step_make_task');
                make.setAttribute('aria-label', s('step_make_task'));
                make.appendChild(h.icon('bi-check2-square'));
                make.addEventListener('click', function () {
                    if (!step.title.trim()) {
                        title.focus();
                        return;
                    }

                    makeTask(index);
                });
                tools.appendChild(make);
            }

            if (editable) {
                var remove = el('button', 'pg-icon-button pg-icon-button-sm');
                remove.type = 'button';
                remove.title = s('step_remove');
                remove.setAttribute('aria-label', s('step_remove'));
                remove.appendChild(h.icon('bi-x-lg'));
                remove.addEventListener('click', function () {
                    steps.splice(index, 1);
                    drawSteps();
                });
                tools.appendChild(remove);
            }

            row.appendChild(tools);

            if (meta.firstChild) {
                row.appendChild(meta);
            }

            return row;
        }

        function drawSteps() {
            h.clear(list);
            steps.forEach(function (step, index) {
                list.appendChild(stepRow(step, index));
            });

            if (!steps.length) {
                list.appendChild(el('li', 'ws-roadmap-empty pg-text-meta', s('roadmap_hint')));
            }

            drawBar();
            drawFoot();
        }

        function take(roadmap) {
            steps = (roadmap && roadmap.steps) || [];
            editable = editable && !!(roadmap ? roadmap.can_edit : true);
            drawSteps();
        }

        if (plan && plan.id) {
            h.api('ws_personal_plan_roadmap', { plan_id: plan.id }).then(function (result) {
                take(result.roadmap);
            }).catch(h.fail);
        } else if (hooks.steps) {
            take({ can_edit: true, steps: hooks.steps.map(function (step) {
                return { id: 0, title: step.title, day: step.day, done: false, follows: false, task_id: 0 };
            }) });
        } else {
            take(null);
        }

        return {
            node: root,
            // The bar follows the drawer's days.
            redraw: drawBar,
            save: function (planId) {
                if (!editable) {
                    return Promise.resolve(null);
                }

                return h.api('ws_personal_plan_steps_save', {
                    plan_id: planId,
                    steps: steps.map(function (step) {
                        return { id: step.id, title: step.title, day: step.day, done: step.done ? 1 : 0 };
                    })
                }).then(function (result) {
                    take(result.roadmap);

                    return result.roadmap;
                });
            }
        };
    }

    /**
     * How far a plan on the calendar has come: "2/5", or ''.
     *
     * @param {Object} event a calendar entry
     * @return {string}
     */
    function progress(event) {
        return event && event.steps_total ? event.steps_done + '/' + event.steps_total : '';
    }

    /**
     * The same in words: "2 of 5 steps done".
     *
     * @param {Object} event
     * @param {Function} s the screen's texts
     * @return {string}
     */
    function label(event, s) {
        return event && event.steps_total ? fill(s('step_progress'), [event.steps_done, event.steps_total]) : '';
    }

    window.PGWsRoadmap = { section: section, progress: progress, label: label, shiftFollowing: shiftFollowing };
})();
