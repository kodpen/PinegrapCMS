---
name: workspace-flows-not-converted
description: The "Not converted · N" group at the end of the Flows list (V5 #3222, PR #8573): the scheduled actions the conversion refused, their cards, ?view=scheduled opens the list, Show on canvas
---

## Where are they?
- `ws_flows_scheduled($viewer)` (staff with the flow tables; `CFG.flows.scheduled`): no section of their own since V5 (#3222, decided in #3173). `ws_flow_list` answers `scheduled` (`ws_flows_scheduled_rows()`, null for others and for `runnable_in`): `refused`, a count. `drawScheduled()` (workspace_flow.js) draws the group under the flow table, outside its search and chips; with no such rows it is not shown.
- Grouping: pure `ws_flows_scheduled_groups()` (tests/workspace_flows_list_test.php), only `refused`: `flow_id = 0`, anchor `''`, not cancelled. A cancelled row never converted is not listed in Flows (its channel or note card stays), nor a hidden anchor or a scheduled message.
- A converted action is a flow of the table, for every staff member (`workspace-flows-one-list.md`). Its group "Converted from scheduled actions" with Open flow, Run now and History went in PR #8573; Run now is on the flow's canvas (`workspace-flows-run-now.md`), and the history from before the conversion is not moved (owner, `workflow/decisions-made-product-41.md`).
- `?view=scheduled` (old links) opens the list: the screen no longer reads it, and `showNone()` clears the address. A failed action's notice without a card links `workspace_flows.php` (notify.php). The Workspace list has Scheduled messages for these staff (nav.php, unchanged).

## What does a not converted row show?
- "Not converted · N" and a help line, then `K.scheduledList()` (workspace.js's list, cards by `scheduledCard()` with their buttons, Run now and History among them; Edit retries the conversion, #2753). Where Flows lists them, the `ws_scheduled_list` action keeps only rows with no `flow` and not cancelled (actions.php), and CSS hides the list's state chips in this group. `scheduledCard()` is not in `PGWsKit`; exporting it would let the group draw cards without the list's wrapper.
- "Show on canvas" on such a card: `ws_scheduled_get` answers `graph` (pure `ws_scheduled_graph()`); `showAction()` draws it read-only in place of the list (only while the list is shown); Back returns to the list with the group's heading focused; Edit converts it first.

## Which CSS draws the group?
- backend.src.css: `.wsf-sched-group`, `.wsf-sched-title` and its `:focus`, and `.wsf-sched-refused .ws-sa-filters`, which hides the list's state chips. The rules of the converted rows (`.wsf-sched-row`, `-head`, `-mark`, `-tools`) were removed in PR #PRNUM: since #8573 a converted action is a row of the flow table.
