---
name: workspace-channel-board-automations
description: The Automations part of a channel's Board tab (BM5): the toolbar button and its count, a column's rule line, the drawer of Switch rows, a rule asking its answer, the last runs
---

## What does the board draw?
- "Automations" in the toolbar before "New task", hidden until the board's answer says `automations`; a violet bolt and the count of rules on in a violet pill. At phone width its icon and count (`PGWsKit.bar.label`). The rules are read once a mount for the count and the lines.
- A status column with a rule that is on shows one violet line per rule under its head (`ws_board_rule_lines()`: a ready rule in its own words, another flow as "A task that comes here: “name” runs"). Only grouped by status; the overdue rule has none; never on a workflow table's board.
- The drawer (`PGWsKit.drawer`, offcanvas `ws-cb-auto-drawer`): violet tile, "Board automations", one lead line; "Ready rules" and "Flows of this channel" as Switch rows (`pg-set-fold` of `pg-set-switch-row`), a rule on with the primary line; "Your own rule" a dashed row opening `flowLayer(0, {home})`; "Last runs".
- A row: quiet when, bold then, "Ran n times this week" (since Monday, `ws_board_rule_week_start()`), a button opening its flow in Flows (`flowLayer(id)`), the switch. Someone else's flow and a draft never published have the switch off and a line saying why.
- A ready rule that asks (who approves: one person of the channel; the next task's title) opens a line in its row when switched on: Cancel and the filled "Turn on".
- After Flows closes, focusing "Automations" again reads the rules again.

## Where do the last runs come from?
- `ws_board_rule_last_runs()`: the newest runs of the rules' flows (`ws_flow_runs`) the person reaches (`ws_flow_run_access()`), at most `WS_BOARD_RULE_RUNS` (5); the line is time · task · result (`ws_flow_runs_results()`, else the run's status). The task's title is shown only for a task of this channel (a task event's run has subject_type `task`).

## Will the drawer come to other screens?
- Not yet. Owner (2026-11-24): automations on the board first; the same drawer is meant later for other screens (orders, forms, contacts) as "this screen's automations", a plan of its own when he opens it (`workflow/decisions-made-workspace-51.md`).
