---
name: workspace-task-reminders
description: Task reminders, overdue and meeting notices (reminders.php): who is told, the per-recipient mail and its channel line, claims against double sending, time zone and DST
---

## Who is told about a task's reminder or its going overdue?
- `ws_task_notice_user_ids()`: the people on the task who are still on the team, else its creator. Both always see the task (`ws_can_see_task()`), so no further access check is needed.
- The inbox line (`task_reminder`, `task_overdue`) is filtered again when it is read or pushed (`ws_inbox_row_visible()`).

## What does the reminder e-mail name?
- The task, its number, the due day (and time when it has one), a high/urgent priority and the task link.
- The channel line `Channel: #name` only for a recipient who may read the channel: the mail is built per person (`ws_task_reminder_mail($task, $channel_name)`). A person on a task in a private channel they are not in sees the task but not the channel, like the task drawer (`ws_task_detail()` hides it too).
- Symptom fixed: before, one mail for all named the private channel to every person on the task. Test: `tests/workspace_task_reminder_mail_test.php`.

## Can two job runs send the same reminder twice?
- No. Tasks and meetings are claimed with `UPDATE … SET reminded_at = now WHERE … AND reminded_at = 0` and only the run with one affected row sends. Overdue notices claim their stretch by moving the `cron_runs` mark with a guarded update.

## In which time zone are the moments?
- The site's (`TIMEZONE`, set in `init.php`); there is no per-person zone. Due moments are `strtotime()` of local date and time, so a DST change moves nothing.
- The overdue run's lower date bound `date('Y-m-d', $from - 86400)` stays right across DST: a local day is 23 to 25 hours, so stepping back 24 hours never skips the day before, and the exact window `(from, now]` is checked per task.

## Is there a snooze?
- No. A changed date, time or lead time re-arms the reminder (`ws_task_reminder_sync()`); a repeating task's copy takes the newest copy's time, and its reminder when the series says so (`remind_each`).
