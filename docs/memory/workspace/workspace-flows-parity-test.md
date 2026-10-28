---
name: workspace-flows-parity-test
description: tests/workspace_flows_parity_test.php maps every scheduled-action key to the Flows box that keeps it; the changes far from scheduled.php that fail it, how call sites are keyed, what to do
---

## What does the test guard?
- Scheduled actions move into Flows with nothing lost. Every key the code has (form and request, rules, events, steps and their options, change kinds and fields, caps, limits, call sites, placeholders, states, card, routes, links, inbox kinds) needs a line in `workspace_flows_parity_table()`: the slice (1 one screen ... 5 convert) and the box that keeps it, or `'form'` and why.
- A key the code gains, or a line whose key the code lost, fails `test_workspace_flows_parity_every_key_is_mapped`.

## Which changes outside scheduled.php make it fail?
- The change catalogue `ws_change_types()`: a new kind, update or delete, or update field.
- `ws_events_catalog()`, `ws_task_statuses()`, `ws_events_order_statuses()`, `ws_events_approval_outcomes()`.
- Any call in the product of `ws_event()`, `ws_scheduled_run()`, `ws_scheduled_due()`, `ws_scheduled_execute()`, `ws_events_process()`, `ws_events_start()` or `ws_scheduled_joined()`, a new one or one moved to another function or `case`.
- A `ws_scheduled_*` case of includes/workspace/actions.php, its `pg_panel_actions()` row, an `api('ws_scheduled_...')` call in workspace.js.
- `scheduledCard()`, `showScheduled()` in workspace.js; `ws_nav_groups()`, `ws_tour_steps()`, `ws_inbox_describe()`, the inbox route's kinds in api.php; Pinegrap AI's `propose_scheduled_action`. The `api_routes` group went with the API's scheduled-action routes (#2107); `templates` and `entry_points`, and the form's keys of `request`, with the form's code (R3a).

## How is a call site keyed?
- `name(first argument) in file function case '...'`, a repeat in the same place numbered `#2`.
- A first argument written as the parameter's default counts as left out: `ws_scheduled_run(WS_SCHEDULED_BATCH)` is `ws_scheduled_run()`. Further arguments are not keyed.

## What do the readers read, and when do they throw?
- Caps: `min(N, ...)`, a cut to the first N, and a length or count compared with a number (`place length > N`, kept as written). Limits: every `WS_SCHEDULED_*` define or const, the value as PHP works it out.
- They throw on a shape they do not model: an entry or write written another way, a key worked out by code, a new condition, a define() whose name is code, a step's action used whole other than as a `ws_` function's argument or parameter (a copy, `extract()`, `array_key_exists()`, a cast, a loop, a closure).

## What does a PR do when it fails?
- Adds or moves the line in the same PR, with the slice and box, or `'form'` and the reason; never drops a key to pass. A reader that throws is taught the new shape, not skipped.
- The readers have their own tests at the end of the file ("The readers themselves"): a reader taught a shape gets a case there.
- Workdays cases of `ws_scheduled_next_run()` (tests/workspace_scheduled_test.php) run in their own process with `ws_day_off()` stubbed.
