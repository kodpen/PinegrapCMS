---
name: workspace-flows-record-get-box
description: The Read a record box of flows (record_get): kinds and fields, the owner's reach as the change box checks it, found 0 for a missing or unreachable record, readers, canvas
---

## What is it?
- Step kind `record_get` ("Read a record") in `ws_step_kinds()` (steps.php), type of its own, outcomes done and failed, retries as every step box. Functions in `includes/workspace/flow_record_get.php`. Tests `tests/workspace_flow_record_get_test.php`.
- Settings (`ws_step_config_normalize('record_get')`): `record`, one of the kinds of `ws_flow_record_get_fields()` (order, product, contact, booking, task, form = a submitted form, cart_quote; anything else ''), and `id`, a literal id or one value alone (`ws_step_row_names_row()`, as a row box's row). `id` is its only text, filled with target `json`; quotes a JSON text keeps are trimmed before the id is read.
- As Zapier's Find and Make's Search steps: a later condition on `found` decides what happens.

## Whose rights?
- The owner's, at publish, each run and box try (`ws_step_record_get_check()`): the kind through `ws_change_allowed($owner, $kind)` (its right column: ecommerce, contacts, forms; a task any member), a booking by the agenda's rule (`pg_booking_agenda_user_scope()` not false, `pg_booking_ready()`), a cart quote (no change kind) by the ecommerce right of its screens where `ECOMMERCE` runs and `pg_cart_quotes_ready()`.
- Each record as it is read (`ws_step_record_get_read()`): `ws_change_allowed()` with the id (a role-3 contact in their groups, a form in a folder they edit, a task they may change), then `ws_change_record()`; a booking with `pg_booking_agenda_scope_allows()` on its staff, never a `held` one; a cart quote by named columns (never `token_hash`/`token_hint`) with its contact's name and line count, its `status` the state `pg_cart_quote_state()` gives now (open past `valid_until` = expired) and `ordered` 1 or 0 (`ws_step_record_get_cart_quote_row()`, pure). Out of reach reads as not there: done, `found` 0, so a run tells nothing of a record it may not read.
- No starter check (the change box's `ws_step_starter_refusal()`): it writes nothing, and its values are held from non-editors. Role 3 builds it (`ws_flow_member_kinds()`).

## What does it give?
- `record_id`, `found` (1 or 0), then the kind's fields (`ws_flow_record_get_outputs()`): money, numbers and yes/no typed number, the rest text.
- `ws_step_record_get_data()` (pure): money as units with two decimals (`ws_flow_record_get_money()`, "12.50"), a unix time as `Y-m-d H:i`, a DATE's zero date as '', yes as '1', a form's text answers one "name: answer" a line. `ws_step_result_data()` keeps every field as text (decimals survive), `record_id`/`found` as numbers.
- Never a field: card digits, cardholder, a booking's manage token, an IP address (asserted by the test).

## Who reads what?
- `ws_step_outputs_held()` holds all its values from readers without edit; `ws_step_result_shown()` gives them "A record was read." / "No record was read."; `ws_flow_graph_present()` empties `id` (the kind stays).

## Canvas, API, AI
- workspace_flow.js: `KINDS.record_get` (group system), palette after `row_get`, offered while `FL.steps.reads` (`ws_flow_record_get_kinds($viewer)`: kind, label, outputs) is not empty; `recordGetFields()` (kind, id), `recordGetMeta()`, `boxValues()` adds the kind's outputs. Icon in workspace.js `FLOW_ICONS`.
- API: named in the `nodes` and `edges` descriptions of POST /workspace/flows; GET /workspace/flow-kinds and Pinegrap AI's catalogue read it from the kinds, with a hint in `ws_flow_ai_catalogue_hints()`.

## Why a cart quote?
- To read a quote after a wait (T13), PR #PRNUM. The AI hint (flow_draft.php) and POST /workspace/flows' `nodes` text (api.php) still name six kinds.

## What is unverified?
- No run on a dev database and no browser look at the form; the pure parts and the wiring are under tests.
