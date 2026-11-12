---
name: bell-actions
description: A bell row's in-place action (BP4): Approve, Try again, Mark as done, offered only when the reader may run it (ws_bell_act()); after success the row is read and the list redrawn
---

## Which rows act in place?
- Owner (2026-11-11, `workflow/decisions-made-panel-4.md`): "Aç + hazır işlemler": what the dashboard does in place the bell row does too; no new kind of action, no reply box.
- `ws_bell_act_kind($kind, $run_status)` (`includes/workspace/notify.php`, pure): `approval_requested` approve; `flow_finished` with a failed run retry; `assigned`, `task_reminder`, `task_overdue` done; anything else none.

## When does a row get `act`?
- `pg_panel_get_notifications()` sends `act` for a Workspace row through `ws_bell_row_act($inbox_id, $user['id'])`: the reader's own inbox row, visible to them, then `ws_bell_act()` runs the dry-run check of the action it would call and gives null when it refuses:
  - approve: `ws_approval_decide_check($viewer, $approval, 'approve', '')`: asked, not answered, open, may post in the channel; an approval that asks for an answer is refused here, so it is approved where it was asked.
  - retry: the run's latest failed box, `ws_flow_step_mend_check()`: only the flow's owner (`ws_flow_share_manager()`).
  - done: a task not done that the reader sees and may change (`ws_can_see_task()`, `ws_task_set_status_check()`).
- `act` = kind plus its ids: `approval_id`; `run_id`, `node_id`; `task_id`. A read row keeps it while the action is still open.

## How does the drawer run it?
- `pg_bell_row()` draws the one button as `.pg-bell-do` (Approve, Try again, Mark as done) in place of the opening button; the title still opens the row's place.
- The click calls the Workspace's own panel actions, as the dashboard's cards do (`pg_dash_cards_work.js`, `pg_dash_cards_team.js`): `ws_approval_decide` (approve), `ws_flow_step_mend` (`how` retry), `ws_task_status` (`status` done). They check the reader again.
- Success: an unread row is read (`edit_notification(…, 'mark_read', …)`) and the list is drawn anew keeping its place (`get_notifications(true)`). A refusal or a failed request shows the bell's toast (`pg_bell_refused()`, `bell-refusals.md`); the button keeps its label and is disabled only while the request runs.

## What did the bell plan leave as it was?
- No schema. `ws_inbox` stays the store for the API, sync and push; the API's `group` filter stays, and a module filter there was not part of the bell's work.

Tests: `tests/bell_actions_test.php`. The bell plan's last step (BP4), after #8805.
