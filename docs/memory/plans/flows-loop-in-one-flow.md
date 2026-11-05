---
name: flows-loop-in-one-flow
description: Plan (owner 2026-11-04): a loop in one flow: a Read a row box, Repeat N times with pure boxes in the loop, date words in a derived value; claim as [plan:flows-loop-in-one-flow] FL<n>
---

## What is this plan?
- Three small Flows steps so a counter line (read a row, raise it, write it, a round a minute) is one flow instead of two restarting each other (decided in `workflow/decisions-made-product-30.md`). A coordinator hands them out as `feature-plans-2026-11.md` says. Read first: `workspace/README.md` key rules, `workspace-flows-for-each.md`, `workspace-flows-table-boxes.md`, `workspace-flows-row-box.md`, `workspace-flows-derived-box.md`, `workspace-flows-outputs.md`.

## Which steps are there?
- FL1 (S) done (#6874). "Read a row" box: kind `row_get` in `ws_step_kinds()` (steps.php, outcomes done and failed), settings `table_id` and `row` (a literal row id or one value, as `row_set`); checked at publish, run and try with the owner's rights, `ws_wtable_flow_check(…, 'view')`, the `paused` gate waits as the table boxes do; outputs `row_id`, `found` (1 or 0) and `cell_<id>` per column (`ws_flow_row_outputs()`, cells as `ws_flow_row_cell_text()` gives them); a missing row is done with `found` 0 and '' cells. Non-editors read "A row of a table was read." and no cells (`ws_step_outputs_held()`, `ws_flow_row_box_masked()`); role 3 in `ws_flow_member_kinds()` with view reach. Canvas: palette entry by the table boxes, `rowGetFields()` (table, row), the value picker offers the cells. API kinds and the draft tool schema name it. Tests `tests/workspace_flow_row_read_test.php`, parity lines. Memory `workspace/workspace-flows-row-get-box.md`.

- FL2 (M) after FL1. Repeat N times: For each `mode` list or count (`count` 1 to 50, a number or one value; `ws_flow_loop_items()` gives the items 1…N, `index` = `item`, no `fields`); `forEachFields()` shows the count field; a count out of range or not a number is `bad_config`. The loop reference check (`ws_flow_loop_problems()`) lets a pure box (`value`, `constants`) read a body box when every box reading that pure box is in the same body (a pure box takes the loop of its readers; read inside and outside, `bad_reference` as today); a derived box in a body is worked out at the reader's pass (`ws_step_run_values()`). Tests: count mode, the pure-box rule, a Wait box in the body woken at pass > 0 (`ws_flow_steps_recheck()`), and the line of the decision as one flow (`tests/workspace_flow_loops_test.php`, `workspace_flow_values_test.php`). Memory `workspace-flows-for-each.md`, `-for-each-canvas.md`.

- FL3 (S) done (#6873). Date words in a derived value: `ws_flow_pure_values()` fills `{{date}}` (`Y-m-d`), `{{time}}` (`H:i`) and `{{weekday}}` (`ws_recurrence_weekday_names()`) in `a` and `b` on the typed text before `ws_flow_derived_fill()`, the moment given as an argument (`time()` by default) so the test is fixed; the box try shows them; `valuesHint()` (#6844) names the three words for the value box (a new text by `tr_add.php`); the API box description says so. Tests `tests/workspace_flow_values_test.php`. Memory `workspace-flows-derived-box.md`.

## Which decisions bind it?
- `workflow/decisions-made-product-30.md`: no flow variables, no 1-minute schedule, no back arrows, no fill in table cells; no schema. D9's loop limits (`workflow/decisions-made-workspace-3.md`) and the data-passing rules (`workspace-flows-decisions.md`) hold: a filled value is never read again as a placeholder.
- Texts through `tr_add.php`; `.src.js` and `.min.js` together (`php tools/build_js.php`); each step under 1,000 product lines.

## Where does it start?
- FL1 and FL3 at once: different functions of `workspace_flow.js` (a new form; `valuesHint()`) and different PHP files. FL2 after FL1 lands, so its last test builds the decision's line as one flow.
