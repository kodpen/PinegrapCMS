---
name: workspace-personal-plans
description: A person's private plans on My calendar (PC3): step 11.0.201, the gate ws_personal_plan_can_see() and its guard test, the drawer, Today, the plan_reminder line
---

## Where are plans kept?
- Step 11.0.201 of 2026.11.0 (`upgrade_2026_11_0_ws_personal_plans()`): `ws_personal_plans` (owner `user_id`, title, note, `starts_at`/`ends_at` end exclusive, `all_day`, `remind_at`/`reminded_at`, `visibility`, `contact_id`, `mail_conversation_id`), `ws_personal_plan_steps`, `ws_personal_plan_people`, and `ws_inbox.plan_id`. One probe: `ws_personal_plans_ready()`. Shape and reasons: `personal-calendar-census-tables.md`.
- `visibility`, `contact_id`, `mail_conversation_id`, the steps and the people stay unused until PC4-PC7. `contact_id` is listed in `pg_contact_holders()` only by PC6; until then `tests/contact_holders_test.php` names it as an exception.
- Never a `ws_events` row: its readers do not ask whose row it is (`personal-calendar-census-readers.md`).

## Who may see a plan?
- `ws_personal_plan_can_see($user_id, $plan)` in `includes/workspace/personal_plans.php`, the one gate: its owner, and those it is opened to (PC4), while reader and owner are on the team. An administrator is no exception; a guest and a leaver read nothing; a leaver's plans stay but nobody reads them and they do not remind.
- Only `personal_plans.php` names the plan tables (besides the step and `get_tables()`); `tests/workspace_personal_plans_test.php` fails when another file does. Every read in the module goes through the gate.
- No Site Log, audit line, webhook or flow event names a plan; nothing is exported.

## How is a plan shared? (PC4)
- "Who can see" on the drawer: Only me (`visibility` `me`, the default), Chosen team members (`people`, rows in `ws_personal_plan_people`; the owner and anyone off the team are dropped on save) or The whole team (`team`). The note above the title says who sees it.
- The others read it on their My calendar and Today as "Plan of <owner>" ("admin kişisinin planı"), in a read-only drawer ("<owner> shared this plan with you. Only they can change it."), without the owner's reminder or the list of the others. Save and delete refuse them (only the owner may change it: `ws_personal_plan_can_edit()`).
- Only the owner is reminded, so taking a share away leaves no inbox line to remove.

## How is a plan made and drawn?
- My calendar: "New plan" in the toolbar and on a day's right-click menu opens the drawer (title, first and last day, All day, start and end time, reminder, note; "Only you see this plan."). A plan is drawn as kind `personal` (violet, a lock before its title; on a phone the bar takes the violet at 80%) and opens in the same drawer, with Delete (after a confirmation, no bin). `?plan=N` opens a plan.
- Actions `ws_personal_plan_get`, `_save`, `_delete` (`includes/panel/actions.php`); input checked by `ws_personal_plan_clean()` (pure, tested). No end time: one hour. An all-day plan ends at midnight after its last day.
- Today's "Calendar · today" card lists the reader's plans of the day as "My plan".

## How does a plan remind?
- Through the task reminders' run: `ws_task_reminders_run()` calls `ws_personal_plan_reminders_run()`, and `ws_task_reminders_waiting()` asks `ws_personal_plan_reminders_waiting()` (job.php loads the module). Claimed by `reminded_at`; a changed reminder moment re-arms it.
- Lead times: at the start, 15 min, 1 h, 1 day; an all-day plan reminds from 09:00 of its first day.
- The line is inbox kind `plan_reminder` with `plan_id` ("Reminder: <title>", the day and time, opens My calendar on the plan); `ws_inbox_row_visible()` reads it back through the gate.
- An application never reads the plan's words: GET /workspace/inbox and GET /notifications say only "A reminder of your own plan" for such a line.

## What happens when an account is deleted?
- `pg_user_delete()` calls `ws_personal_plans_user_deleted()`: the plans, their steps, people and inbox lines go, and the account is taken off plans shared with it.

Test: `tests/workspace_personal_plans_test.php`.
