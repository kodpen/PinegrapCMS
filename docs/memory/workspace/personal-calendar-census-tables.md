---
name: personal-calendar-census-tables
description: PC1 proposal (2026-11-14, asked of the owner through the architect, not yet answered): the tables and columns a person's private plans need, each with its reason and the step that uses it
---

## Why new tables at all?
- A plan cannot be a `ws_events` row (`personal-calendar-census-readers.md`), a task (a task is the team's) or a `ws_holiday_feeds` payload (sealed, read-only, one blob per calendar). Bookings kept their time in their own table the same way (`workspace-bookings.md`).

## Which tables are proposed?
- `ws_personal_plans`, one row a plan: `id`; `user_id` (owner; the gate's first test); `title` VARCHAR(255); `note` TEXT; `starts_at`, `ends_at` INT UNSIGNED (end exclusive, as `ws_calendar_plan_place()`); `all_day` TINYINT; `remind_at`, `reminded_at` INT UNSIGNED (claimed as task reminders are).
- More columns of `ws_personal_plans`: `visibility` VARCHAR(16) `me`/`people`/`team` (PC4; `me` default); `contact_id` INT UNSIGNED (PC6); `mail_conversation_id` INT UNSIGNED (PC7, `ws_mail_conversations`); `copied_from` INT UNSIGNED (PC6's "from the person's own plans"); `created_at`, `updated_at`, `deleted_at` INT UNSIGNED. Keys: (`user_id`, `starts_at`), (`reminded_at`, `remind_at`), `contact_id`, `mail_conversation_id`. InnoDB, utf8mb4.
- `ws_personal_plan_steps`, one row a roadmap step (PC5): `id`, `plan_id`, `position` SMALLINT, `title` VARCHAR(255), `day` DATE (`'0000-00-00'` none), `done_at` INT UNSIGNED, `task_id` INT UNSIGNED (PC5's "Make a task": the step follows the task's state). Keys `plan_id`, `task_id`.
- `ws_personal_plan_people`, the chosen team members of PC4: `plan_id`, `user_id`; primary key both, key `user_id` (My calendar reads "shared with me" by it).
- `ws_inbox.plan_id` INT UNSIGNED DEFAULT 0 after `event_id`: the reminder line names its plan, as `event_id` (meeting_soon) and `note_id` do. Reusing `event_id` would send notify.php's `ws_events` read to the wrong table.

## Why one step with every column, not one per step?
- PC4 to PC7 each add a column; one 2026.11.0 step with all of them is one migration to run twice on a dev DB and one readiness probe (`ws_personal_plans_ready()`). The columns of a later step stay 0 until it lands. The alternative is a step per PC, each asked again.

## What stays out?
- No repeat rule (a plan does not repeat; the plan file names none), no colour, no capacity, no `department_id` (team-wide sharing is `visibility`), no column on `ws_events`, `ws_tasks` or `ws_mail_items` in PC3.
- PC7's invitation needs what the mail's calendar part said after the raw mail is gone (30 days): either a TEXT `ws_mail_items.invitation` (JSON: uid, method, summary, start, end, all_day, location, organizer) filled at ingest, or the card only while the `.eml` exists. Asked with PC7, not now (`personal-calendar-census-mail.md`).

## What is asked?
- Under "Decision question" in the PC1 PR, naming the architect: yes or no to the three tables and `ws_inbox.plan_id`, and to one step with every column.
