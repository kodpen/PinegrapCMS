---
name: mail-remind-later
description: Remind me later (IB10): the pane's menu and times, ws_mail_reminders, what leaves the person's list and comes back unread, the wake, inbox kind mail_remind, step 11.0.212
---

## What does the person see?
- "Remind me later" (Sonra hatırlat) among the reading pane's tools of the E-mails screen, for any team member who may read the conversation (not only who may answer): "In an hour", "Tomorrow morning" (09:00), "Next week" (Monday 09:00), "Choose a time" (a drawer with the panel's date-and-time control and "Set reminder").
- Times are in the person's zone (`USER_TIMEZONE`, else the site's); a chosen time follows Send later's limits (5 minutes to 60 days, a skipped hour refused, `ws_mail_later_parse()`).
- Once set, the row leaves the list, the pane closes and the E-mails count drops. While one is set the button reads "Reminds you at …" and the menu adds "Cancel reminder". A search still finds the conversation, so it can be opened and cancelled.
- Nobody else's list or count changes; the conversation's status, holder and card stay as they were.

## How does it come back?
- At its time `ws_mail_reminders_run()` (hooked into `ws_task_reminders_waiting()` / `_run()` in reminders.php, loaded by job.php, run before the Workspace switch is asked, so it works with the Workspace off) wakes it; no new job.
- At once when a new mail arrives in it: `ws_mail_remind_new_mail()` in `ws_mail_ingest()`, for an ongoing conversation only, before the holder's notice.
- A wake is claimed (`woken_at` set where 0), then inbox kind `mail_remind` ("Reminder: {subject}", the customer as body, opens `messages_mail.php?conversation=`). A person who can no longer read the channel is told nothing and the row goes.
- A woken conversation reads unread (`ws_mail_unread_sql()` ORs `ws_mail_remind_woken_sql()`; list rows and the API's unread map ask `ws_mail_remind_woken_ids()`) until it is read: `ws_mail_read_mark()` for a conversation deletes its woken row, Mark all as read deletes them all.

## How is it kept?
- Table `ws_mail_reminders` (owner 2026-11-15, the schema decisions' part 21, brought by PR #9566): one row per conversation, person and kind (UNIQUE `uniq_person`); kind `later` here, `no_answer` for Remind me if no answer comes (`mail-no-answer.md`). A second choice moves `due_at` and sets `woken_at` back to 0.
- Step `upgrade_2026_11_0_ws_mail_reminders()` (11.0.212), listed in `get_tables()`; readiness `ws_mail_remind_ready()` (a plain `pg_schema_has()`, callable before the Workspace loads).
- Panel action `ws_mail_remind` (choice hour | morning | week | at | cancel), also with the Workspace off. No API route or MCP tool; the API's inbox lists the kind.

## Tests?
- `tests/workspace_mail_remind_test.php`: the times per zone and weekday, the refusals, the two conditions, the pane's offer, the step's shape, the wiring. The step ran twice on MariaDB 10.11 and the hidden and unread conditions were checked against real rows.
