---
name: workspace-flows-converted-anchor
description: A scheduled action converted into a flow (anchor converted, slice 5a): starts by hand and chain, boxes write as the action, the run's end written back with its news
---

## What is it?
- A `ws_scheduled_actions` row with `flow_id` and `anchor = 'converted'` (column 11.0.88, decided in #2396; hidden anchors: `workspace-flows-anchors.md`). It keeps its id, card, history, state and news (plan answers 2b and 5, plans/workspace-flows-scheduled-merge.md). Rows are converted by the 2026.11.0 upgrade (slice 5b).

## How does it run?
- `ws_flow_anchor_start()` also takes a `manual` start box: card Run now or a chain; publishing it keeps the row active with rule trigger (not cancelled). Start box says "Started by “X”.", "Started by hand." or the kind.
- Context adds `anchor_id`, `source`, `source_result`; `ws_step_action_row()` (steps.php) makes the boxes write as the action: id, card, channel, name, {{run}}, chain source run `sched_run`, "since the last run" from the run before.
- `ws_scheduled_execute()` does not report a converted start; the run's end (`ws_flow_run_refresh()`, heal, cancel, `ws_flow_runs_cancel_ids()`) calls `ws_flow_anchor_run_ended()`: `ws_scheduled_runs` status (cancelled = skipped), a "flow run N …" line, a one-off failed, then `ws_scheduled_report()` with `ws_scheduled_run_news()`.

## Why written back at the run's end?
- The flow run ends after `ws_scheduled_execute()` returns, so the action's run first reads done ("flow … started, run N"); the end overwrites status, detail and finished_at of that same `ws_scheduled_runs` row (`sched_run` in the run's context), so the card's history and the next run's news compare against the flow's real outcome.
