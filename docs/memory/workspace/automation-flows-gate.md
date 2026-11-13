---
name: automation-flows-gate
description: The one gate of automation flows, pg_flows_enabled() (AF1): flows run with the Workspace off; what the general job, the site events and the scheduled run do then, and what waits for the Workspace
module: automation_flows
---

## What is the gate?
- `pg_flows_enabled()` in `includes/fn/automation_flows.php`, loaded everywhere: true once the flow tables are there (`ws_flow_run_steps`, `ws_scheduled_actions.flow_id`, `ws_events_in`), read from the schema cache. No switch of its own: Owner (2026-11-12, #8961, M1a) put flows on at every site behind the right only (`workflow/decisions-made-workspace-20.md`).
- `ws_enabled()` stays the Workspace's gate for everything else. Inside the module the code asks `ws_enabled()` to tell the two cases apart; outside it asks `pg_flows_enabled()` before loading the module.
- `pg_flows_in_use()`: one read, a live flow or a run still running or waiting. The general job loads the Workspace's 150 files on a Workspace-off site only when it says yes.

## Who asks it?
- `job.php`'s scheduled block: loaded when the Workspace is on or `pg_flows_in_use()`; `ws_scheduled_run()` when `ws_enabled() || pg_flows_enabled()` and `ws_scheduled_due()`. Agent wakes and the ws_refs backfill keep `ws_enabled()`.
- `workspace_recurring_job.php`: `ws_scheduled_run()` and `ws_flow_runs_sweep()` (30-day cleanup) on the same condition; everything else in it keeps `ws_enabled()`.
- `pg_event_record()` (site events, `includes/fn/events.php`): records when the Workspace is on or the gate holds.
- `flow_start.php` (`ws_flow_web_receive()`): 503 `not_ready` without the gate. It never asked the Workspace switch.
- Still behind the Workspace: the Flows screens and panel actions (AF2), the menu row (AF4), the API routes and run events and the dashboard's flow entries (no step yet; proposals in `plans/automation-flows-menu-census.md`).

## Which site events are kept with the Workspace off?
- Only those an event rule of an active flow anchor names (`flow_id > 0`): every event is treated as an on-demand one (`pg_event_workspace_kept()`, pure; `pg_event_workspace_waiting_rules($event, true)`, one read per event per request). With the Workspace on nothing changed: all but `inventory.changed` are kept, for the watching channels.

## What does the scheduled run do with the Workspace off?
- Its flows' parts: the events (`ws_events_process()`, which wakes waiting boxes and queues anchor starts), `ws_flow_advance()`, the timed anchors and the queue's starts of anchors. `ws_scheduled_running_where()` / `ws_scheduled_queue_running_where()` (pure) add `flow_id > 0`, and `AND 1 = 0` without the flow tables.
- Waiting for the Workspace: converting scheduled actions, the lines in watching channels (`ws_watch_write()`), approval deadlines, read receipts, scheduled actions that are no flow, scheduled messages. `ws_scheduled_due()` asks only about what will run.

## Which boxes stop?
- A box that needs the Workspace fails at run time with "This box needs the Workspace, which is switched off on this site, so it did not run." (`ws_flow_workspace_box_refusal()`, asked in `ws_flow_step_run()` after the member's refusal). The list: `automation-flows-workspace-boxes.md`.

## Where is it tested?
- `tests/automation_flows_gate_test.php`: no tables no gate, the events kept, the run's rows, the kinds list, the per-box rule, the refusal, both jobs.
