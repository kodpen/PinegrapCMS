---
name: workspace-flows-engine
description: Plan (2026-10-24, F1 #1503, F3a #1551, rest open): Flows engine: tables, runs and steps, event wake-ups, drivers, shared steps, idempotency, versions, limits, API
---

## What does it reuse?
- Steps `ws_scheduled_do()`, checks `ws_scheduled_action_input()`, conditions `ws_scheduled_rules_hold()`, outcomes done/failed, assignees `ws_template_assignees()`.
- Trigger: a hidden anchor row in `ws_scheduled_actions` (kind action, new `flow_id`, step `{type:'flow'}`) keeps time, event, join rules, lock, hourly cap; lists and `ws_scheduled_action()` skip it by a new filter (`ws_scheduled_actions_only()` also feeds event and join starts); `ws_scheduled_report()` stays silent.
- Legacy actions run unchanged on `ws_scheduled_execute()`; flow-only steps never reach their form.

## Which tables? (one 2026.11.0 Workspace sub-step; VARCHAR(16) statuses, not ENUM)
- `ws_flows`: owner, home channel, name, status (draft, live, paused, binned), draft, draft_rev (409 `changed_meanwhile`), version_id; anchor by `flow_id`.
- `ws_flow_versions`: flow_id, n, graph, publisher, time; immutable.
- `ws_flow_runs`: version, status (running, waiting, done, failed, cancelled), starter, actor, start_key (UNIQUE per flow), subject, context (trigger payload, set once), depth.
- `ws_flow_run_steps`: run, node_id, pass (0, no loops), status (ready, running, waiting, done, failed, skipped, interrupted), outcome, attempt, ready_at, claimed_at, wait_event, wait_key, wake_at, effect, result; UNIQUE (run, node, pass).

## How does a run move?
- A start (Run, API, the anchor's step) writes the run (start_key `manual:<token hash>`, `sched:<scheduled run id>`), its trigger step done.
- A step that ends marks itself, then INSERT IGNOREs each next box whose arrows in are all settled; untaken branches skip their boxes, so a join fires once.
- `ws_flow_advance()` claims a ready step (`UPDATE … AND status='ready'`, affected_rows 1), runs it, follows its outcome.
- Task, approval: `effect` (`task:12`), then waiting; an approval needs a channel, ends approved, rejected, expired or withdrawn. Delay: `wake_at` (F5b).
- No open step: done or failed, the owner told once (conditional UPDATE). Publish adds a version, syncs the anchor; runs keep theirs.

## How do events wake it?
- `ws_events_awaited()` also reads waiting `wait_event`s, else `ws_event()` drops them; then `ws_events_process()` calls `ws_flow_wake()`: `workspace.task.status_changed` (done, cancelled) by `task:<id>`, `workspace.approval.decided` by `approval:<id>`.
- Lost events: waiting steps recheck hourly.

## Drivers, budgets, limits?
- `ws_scheduled_run()` (ws_tick, both jobs) calls `ws_flow_advance()` before its early returns; `ws_scheduled_due()` sees due steps (job.php's gate).
- Time budget: no box starts after 10 s in ws_tick, 25 s in a job; 3 boxes a run per request.
- A run's events carry its depth (`ws_event_depth()`); `pg_event_record()` will too (today only `ws_event()`).
- `WS_FLOW_*`: 100 boxes, 200 box runs a run, 60 starts a flow an hour, 500 open runs, result 16 KB, finished runs kept 30 days.

## Shared steps and idempotency?
- Built (F4a): steps.php, workspace/workspace-flows-steps.md. Left: change, row save `effect` at once; a stale begun step fails and its owner retries or skips it (F5a). Webhook: `Idempotency-Key` from run, node, pass.

## API and MCP?
- /workspace/flows, /{id}, /publish, /resume (`ws_api_own_words_only()`, `'mcp' => false`), /pause, /runs; /workspace/flow-runs/{id}, /cancel, /steps/{node}/retry, /skip; `dry_run`; `workspace:read`, `:write`.

## Where to start?
- Built: F1 #1503, F3a #1551 (workspace-flows-runner.md).
- F4 done: a #1835, b #1857, b2 #1923, b3 #1957 (step kinds), c #1980 condition, d #2006 anchors, e #2024 box try, f #2070 starter's rights.
- F5: a) #2098 retry or skip a failed box (workspace-flows-retry-skip.md); b) #2135 delay box (workspace-flows-delay-box.md); c) #2142 approval box (workspace-flows-approval-box.md); d) #PRNUM wait with a timeout outcome on the task box (workspace-flows-wait-timeout.md).
