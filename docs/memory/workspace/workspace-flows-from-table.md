---
name: workspace-flows-from-table
description: Run from a workflow table (F6c-a, #3145, decision #3122): rows compiled once into a draft flow (wtable_flow.php, ws_flow_from_wtable()), the column window on the table screen, rights; F6c-b left
---

## What does it do?
- On a table (`workspace_wtables.php?id=`) the toolbar's "Run from table" opens a dialog `#ws_wt_flow` naming four columns: step (text, required), person (person), days (number), after (number = the row number shown, `position`). Pre-filled by type, first match wins, a column used once (`ws_wtable_flow_prefill()`): of two number columns the first is days, the second after. Posted as `wt_action=flow_from_table`, `flow_map[role]`; `ws_wtable_flow_map()` keeps a column only of the role's type and not taken. Nothing is stored on the table.
- `ws_wtable_flow_create()` reads all rows, compiles, saves a new draft through `ws_flow_save()` named as the table, owned by the person, never published. Clean → straight to `workspace_flows.php?flow=N`; otherwise a notice on the table with `ws_wtable_flow_notes()` and a link to the draft (the canvas shows no server notices).

## How are rows compiled? (pure `ws_flow_from_wtable($rows, $map, $members)`)
- Box `start` (manual), then a `task` box `row_<row id>` per row with a step (whitespace folded to one line), in row order; empty steps are skipped (`empty`).
- Arrow in: from the row its after cell names when that row is above and has a box, else from the box before (`after_ignored` lists a later row, itself, an empty or missing one): no loop possible. From start `on: next`, from a task `on: done`; two rows after the same row run side by side.
- Person → `assignees` only when in `$members` (`ws_rights_for_id()['member']`), else no assignee and the row in `unknown`. Days → `due_in`, `round()`, 0-3650; none → ''.
- At most `WS_FLOW_MAX_NODES - 1` tasks (`left_out` counts the rest); laid out by `ws_flow_auto_layout()`; result passes `ws_flow_graph_check()`.

## Who may?
- `ws_wtable_flow_refusal()`: reads the table (view is enough) and `ws_can_schedule()`; the button shows only then. Assignees and role-3 reach are checked at publish (`ws_flow_publish_check()`) as for any flow. No schema.
- Tests: `tests/workspace_wtable_flow_test.php`.

## What is left (F6c-b)?
- Run asks an optional customer (contacts the starter may read): run `subject_type = 'contact'`, `subject_id`; task titles carry the customer's name; a second Run for the same customer while one is open answers that run (start key per flow + contact). Runs without a customer as today. Needs the Run dialog in `workspace_flow.js` after the Flows look steps (plans/workspace-flows-look.md).
