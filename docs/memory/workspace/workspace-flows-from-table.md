---
name: workspace-flows-from-table
description: Run from a workflow table (F6c-a #3145, F6c-b, decision #3122): rows compiled into a draft flow (ws_flow_from_wtable()), rights; Run's optional customer, one open run per flow and contact
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

## Run for a customer (F6c-b)?
- Run's question (`startRun()` in `workspace_flow.js`) holds a contact search (`customerPicker()`, `ws_ref_search` type contact) for whoever has the contacts right (`FL.run_customer`); empty = a run about nobody, as before. Sent as `contact_id` (`ws_action_flow_run_fields()`).
- `ws_flow_run_start_check()`: after a token's run, `ws_flow_run_contact_check()` (exists, contacts right, `ws_viewer_contacts_open()`; else 422 `contact_id`, existence not told). The flow's open run about the contact is answered (200) if the starter reads it, else 409 (`ws_flow_run_subject_answer()`), before the live and limit refusals.
- A new run gets `subject_type = 'contact'`, `subject_id`, `start_key = ws_flow_contact_start_key(contact, last ended run id)`: the unique (flow_id, start_key) keeps two racing starts to one run; once it ends the next key differs. The token is not the key then; a retry meets the open run.
- Context keeps `subject_name` at start; each task title gets ` – <name>` (`ws_flow_task_title_customer()`, within 200 characters, the name kept). No schema: the 11.0.80 columns.
- External API: `POST /workspace/flows/{id}/runs` takes the same optional `contact_id` (body, int ≥ 1) and hands it to `ws_flow_run_start_check()`, so its dry run meets the same answers; the open run's 409 is code `run_open` (`ws_api_flow_refusal_code()`), the dry run names `contact_id`, a run read and the run webhooks' `run` carry `contact_id` (null for none). Still no MCP tool: run writes are `'mcp' => false` (`workspace-flows-runner.md`). Done in #3351.
- Tests: `tests/workspace_flow_runs_api_test.php` (the route); `tests/workspace_flow_runs_test.php` (`…contact_start_key`, `…contact_open_run_answered`, `…task_title_with_customer`, `…contact_read_right`).
