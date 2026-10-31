---
name: workspace-flows-scheduled-section
description: Staff's scheduled actions at the end of the Flows list (V5, #3222): converted rows with Run now and History, the Not converted cards, ?view=scheduled opens the list, Show on canvas
---

## Where are they?
- `ws_flows_scheduled($viewer)` (staff with the flow tables; `CFG.flows.scheduled`): no section of their own since V5 (#3222, decided in #3173). `ws_flow_list` answers `scheduled` (`ws_flows_scheduled_rows()`, null for others and for `runnable_in`): `converted` (`ws_scheduled_present()` items) and `refused` (a count). `drawScheduled()` (workspace_flow.js) draws them under the flow table, outside its search and chips; a group with no rows is not shown.
- Grouping: pure `ws_flows_scheduled_groups()` (tests/workspace_flows_list_test.php). Converted: `flow_id > 0`, anchor `converted`, any state. Refused: `flow_id = 0`, anchor `''`, not cancelled. A cancelled row never converted is not listed in Flows (its channel or note card stays), nor a hidden anchor or a scheduled message.
- `?view=scheduled` (old links, workspace.js's "Your scheduled actions are in Flows.") opens the list: the screen no longer reads it, and `showNone()` clears the address. A failed action's notice without a card links `workspace_flows.php` (notify.php). The Workspace list has Scheduled messages for these staff (nav.php, unchanged).

## What does a converted row show?
- "Converted from scheduled actions · N": name, state, the converted mark, its when (and next time), its step ("runs the flow …"), last run; Open flow (`openFromList()`), Run now (`ws_scheduled_run_now`, while active, paused or failed: the only hand start of a time- or event-started flow) and History (`ws_scheduled_get`: the anchor's `ws_scheduled_runs` lines, before and after the conversion). No Run on the canvas for a non-manual flow (#3173 2b, not built).

## What does a not converted row show?
- "Not converted · N" and a help line, then `K.scheduledList()` (workspace.js's list, cards by `scheduledCard()` with their buttons; Edit retries the conversion, #2753). Where Flows lists them, the `ws_scheduled_list` action keeps only rows with no `flow` and not cancelled (actions.php), and CSS hides the list's state chips in this group. `scheduledCard()` is not in `PGWsKit`; exporting it would let the group draw cards without the list's wrapper.
- "Show on canvas" on such a card: `ws_scheduled_get` answers `graph` (pure `ws_scheduled_graph()`); `showAction()` draws it read-only in place of the list (only while the list is shown); Back returns to the list with the group's heading focused; Edit converts it first.
