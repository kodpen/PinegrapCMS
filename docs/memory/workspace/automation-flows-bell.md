---
name: automation-flows-bell
description: The panel's bell with the Workspace off (#9288): it shows the inbox lines flows write (a run of one's flow ended or failed, Let people know) and no other Workspace line; Let people know runs again
---

## Which Workspace lines does the bell show with the Workspace off?
- Only the lines a flow writes, `pg_notification_flow_kinds()` (`includes/notifications.php`): `flow_finished` (a run of one's flow ended or failed, for its owner) and `scheduled_notice` (Let people know). Every other Workspace row stays hidden, as before.
- The ladder `pg_notification_visible()` asks `pg_notification_flow_line($target, $reference_id)`: one read per person per request of their `ws_inbox` ids of those kinds, and only while `pg_flows_enabled()`. With the Workspace on nothing changed.
- `pg_notification_ladder_columns()` carries `reference_id` beside `target_user_id`, so marking one read and Delete All Notifications see the same rows the list draws.

## How is such a row drawn and read?
- `pg_notification_display()` describes a Workspace row while the Workspace is on or flows run (`ws_bell_describe()`): a run's line opens it on the Flows screen (`workspace_flows.php?flow=…&run=…`, open with the Workspace off), and a failed run keeps Try again (`ws_flow_step_mend` is a Flows action).
- A Let people know line opens nothing with the Workspace off (`ws_inbox_describe()` gives no url, `workspace.php` is shut): the endpoint gives it no button, the title is plain text and Mark as read reads it.
- Read in the bell is read in the inbox too (`pg_notification_mark_read()`), with the Workspace on or flows running, so GET /workspace/inbox and the bell agree.

## What is not covered?
- Device banners (`pg_push_queue_still_relevant()`, `ws_push_pending()`) still ask the Workspace switch: with it off a flow's line rings the bell but wakes no phone.

## Why?
- Architect (2026-11-13, `automation-flows-decisions.md`): a box that acts where nobody can look is not offered; Let people know is the one a shop without the Workspace needs (an order came), so it came back with the bell. `notify` left `ws_flow_kinds_workspace()` (`automation-flows-workspace-boxes.md`).

## Which test guards it?
- `tests/automation_flows_bell_test.php`: the kinds, the ladder without flow tables, the wiring, notify off the list; `tests/automation_flows_gate_test.php` lists notify among the kinds that run.
