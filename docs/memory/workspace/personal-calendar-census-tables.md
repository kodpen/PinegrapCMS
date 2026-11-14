---
name: personal-calendar-census-tables
description: PC1 proposal (2026-11-14), shaped by the architect and approved by the owner (decisions-made-schema-18): the tables and columns a person's private plans need, each with its reason and step
---

## Why new tables at all?
- A plan cannot be a `ws_events` row (`personal-calendar-census-readers.md`), a task (a task is the team's) or a `ws_holiday_feeds` payload (sealed, read-only, one blob per calendar). Bookings kept their time in their own table the same way (`workspace-bookings.md`).

## Which tables are proposed?
- `ws_personal_plans`, one row a plan: `id`; `user_id` (owner; the gate's first test); `title` VARCHAR(255); `note` TEXT; `starts_at`, `ends_at` INT UNSIGNED (end exclusive, as `ws_calendar_plan_place()`); `all_day` TINYINT; `remind_at`, `reminded_at` INT UNSIGNED (claimed as task reminders are).
- More columns of `ws_personal_plans`: `visibility` VARCHAR(16) `me`/`people`/`team` (PC4; `me` default); `contact_id` INT UNSIGNED (PC6); `mail_conversation_id` INT UNSIGNED (PC7, `ws_mail_conversations`); `created_at`, `updated_at` INT UNSIGNED. Keys: (`user_id`, `starts_at`), (`reminded_at`, `remind_at`), `contact_id`, `mail_conversation_id`. InnoDB, utf8mb4.
- `ws_personal_plan_steps`, one row a roadmap step (PC5): `id`, `plan_id`, `position` SMALLINT, `title` VARCHAR(255), `day` DATE (`'0000-00-00'` none), `done_at` INT UNSIGNED, `task_id` INT UNSIGNED (PC5's "Make a task": the step follows the task's state). Keys `plan_id`, `task_id`.
- `ws_personal_plan_people`, the chosen team members of PC4: `plan_id`, `user_id`; primary key both, key `user_id` (My calendar reads "shared with me" by it).
- `ws_inbox.plan_id` INT UNSIGNED DEFAULT 0 after `event_id`: the reminder line names its plan, as `event_id` (meeting_soon) and `note_id` do. Reusing `event_id` would send notify.php's `ws_events` read to the wrong table.

## Which columns were taken out, and why?
- Architect (2026-11-14): no `copied_from` (no reader names it; PC6 copies without it) and no `deleted_at` (a deleted private plan is gone, after a confirmation; no bin).

## One step with every column, or one per step?
- Architect (2026-11-14): once the owner says yes, one 2026.11.0 step with every column, `ws_personal_plans_ready()` the one probe. The columns of a later step stay 0 until it lands.

## What stays out?
- No repeat rule (a plan does not repeat; the plan file names none), no colour, no capacity, no `department_id` (team-wide sharing is `visibility`), no column on `ws_events`, `ws_tasks` or `ws_mail_items` in PC3, no bin.
- PC7's invitation needs what the mail's calendar part said after the raw mail is gone (30 days): a TEXT `ws_mail_items.invitation` filled at ingest, approved by the owner on 2026-11-14 and added by PC7's own step (`workflow/decisions-made-schema-19.md`). Not chosen: the card only while the `.eml` exists.

## Who decides the tables?
- The owner's yes or no, asked by the architect (agent channel 25, messages 2853 and 2857): the three tables and `ws_inbox.plan_id` as above. Approved on 2026-11-14 (`workflow/decisions-made-schema-18.md`) and built by PC3 (#9500).
