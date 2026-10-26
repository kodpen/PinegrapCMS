---
name: workspace-followups
description: Workspace follow-ups (followups.php, #1790): ws_followup_task_open() opens a tagged, reminded task; "Remind me" on the customer card; quote expiry and overdue receivable jobs
---

## What is a follow-up?
- CRM seams slice 5 (plans/workspace-crm-seams.md): an ordinary task tagged with the record it is about (`ws_refs`), on a channel or for a person, due on a day and reminded by the task reminder (reminders.php: inbox and e-mail, told once more when overdue). No schema, no table of its own; the customer history lists it with the customer's tasks.
- Code: includes/workspace/followups.php; tests: tests/workspace_followup_test.php.

## How does a caller open one?
- `ws_followup_task_open($viewer, $input, $once = false)`, as the person it is opened by; members only. A job builds that person with `pg_load_user_row()` and `ws_viewer()`, as `ws_recurrence_actor()` does.
- `ws_followup_task_data()` (pure) lays the input out: at least one site-record tag (`ws_record_type_keys()`, as text or type/id), a day (Y-m-d or d.m.Y), an optional time, `remind_minutes` (left out: 0, at the due time; null: none; at most 60 days). A reminder on a task already due is refused; one whose moment alone has passed goes at once. No channel and nobody named: the opener's own; on a channel nobody needs to be on it (the reminder goes to the opener).
- Then `ws_task_create()`, with every check of a task made by hand (post in the channel, `ws_can_assign_to()`). A channel the person can no longer write in is dropped and the task is theirs alone.
- `$once`: an open task (todo, doing, waiting) with the first tag, in the same channel or, without one, with one of the same people on it, is handed back (`existing`) instead of a second one (`ws_followup_task_find()`). For jobs that run again.

## What does "Remind me" on the customer card do?
- A button in the card's header (assets/js/workspace_customer.js), only where the reader may post and the channel is not archived. Form: what to follow up on (optional), day (a week ahead), time (optional, else `WS_TASK_REMIND_DAY_TIME`).
- Panel action `ws_channel_customer_followup` (`ws_action_channel()` with 'post'), then `ws_customer_followup_open()`: tags the record the channel is about (`ws_channel_customer_of()`), for the person asking, on the channel, reminded at the due time.
- An empty title is "Follow up: <channel name>": whoever sees the task reads the channel already. The customer's name is never written in: a reader without the right sees the customer only by kind and number.
- The answer is the task's number and due label; the card says "Reminder set" with Open task (the drawer).

## Who else calls it?
- Sales quote expiry (#1826, `erp_quote_expiry_followups()`, off unless `ERP_QUOTE_EXPIRY_FOLLOWUP`): as the quote's `created_by`, on the account's customer channel, `$once` true (erp/erp-alerts.md).
- Overdue receivables (#PRNUM, `erp_overdue_followups()`, off unless `ERP_OVERDUE_FOLLOWUP`): as `ERP_OVERDUE_FOLLOWUP_USER` or the invoice's `created_by`, on the account's customer channel, `$once` true (erp/erp-overdue-reminders.md).

## Is there an API route?
- No new one: `POST /workspace/tasks` already takes `refs`, `channel_id`, `assignees`, `due_date`, `due_time`, `remind_minutes` (workspace-api-task-reminder.md).

## What was not checked live?
- No running site: the action, the task insert and the card's form never ran against a database or in a browser (2026-10-26).

Related: docs/memory/workspace/workspace-customer-card.md, docs/memory/workspace/workspace-customer-history.md
