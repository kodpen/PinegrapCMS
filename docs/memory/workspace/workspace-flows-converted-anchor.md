---
name: workspace-flows-converted-anchor
description: A scheduled action converted into a flow (anchor converted, 5a #2558, 5b): how rows convert after the upgrade, starts, boxes write as the action, write-back, edit by any staff
---

## What is it?
- A `ws_scheduled_actions` row with `flow_id` and `anchor = 'converted'` (column 11.0.88, decided in #2396; hidden anchors: `workspace-flows-anchors.md`). It keeps its id, card, history, state and news (answers 2b and 5, `workspace-flows-scheduled-merge.md`). Rows are converted right after the 2026.11.0 upgrade (below).

## How are rows converted?
- `includes/workspace/scheduled_convert.php`: `ws_scheduled_run()` first calls `ws_scheduled_convert_due()` (10 a request, 5 s): `kind = 'action'`, `flow_id = 0`, `anchor = ''`, not cancelled. The upgrade cannot load the Workspace code, so the driver does it in the first ticks; actions the form saves later convert the same way.
- `ws_scheduled_convert()`: run lock taken, `ws_flow_graph_from_scheduled()` with its own id (self-retry), a graph `ws_flow_problems()` refuses is left on `ws_scheduled_execute()`. Flow owned by the creator, channel 0 (no home readers gain Run), live, version 1. Row: `anchor = 'converted'`, rules = the "when" only (`ws_scheduled_convert_rules()`; conditions are boxes), action `{type: flow}`, follow `[]`; undone if the row got a flow meanwhile. Site Log line with what the drawing left (`left`).

## How does it run?
- `ws_flow_anchor_start()` also takes a `manual` start box: card Run now or a chain; publishing it keeps the row active with rule trigger (not cancelled). Start box says "Started by “X”.", "Started by hand." or the kind.
- Context adds `anchor_id`, `source`, `source_result`; `ws_step_action_row()` (steps.php) makes the boxes write as the action: id, card, channel, name, {{run}}, chain source run `sched_run`, "since the last run" from the run before.
- `ws_scheduled_execute()` does not report a converted start; the run's end (`ws_flow_run_refresh()`, heal, cancel, `ws_flow_runs_cancel_ids()`) calls `ws_flow_anchor_run_ended()`: `ws_scheduled_runs` status (cancelled = skipped), a "flow run N …" line, a one-off failed, then `ws_scheduled_report()` with `ws_scheduled_run_news()`. A condition box that left on no makes it skipped with its sentence (`ws_flow_anchor_skip_reason()`), as its rules did.

## Who changes it?
- Any staff: `ws_flow_access_level()` fact `converted` (`ws_flow_converted()`, one read a request) gives edit; `ws_flow_publish_check()` lets them publish. Shares and delete stay the owner's. The form refuses it ("change it on the flow canvas"); the card has `flow`: Edit opens `workspace_flows.php?flow=`, no read-only drawing (an unconverted row's Edit converts it first); Duplicate copies its flow (`workspace-flows-scheduled-ways-in.md`).

## Why written back at the run's end?
- The flow run ends after `ws_scheduled_execute()` returns, so the action's run first reads done ("flow … started, run N"); the end overwrites status, detail and finished_at of that same `ws_scheduled_runs` row (`sched_run` in the run's context), so the card's history and the next run's news compare against the flow's real outcome.
