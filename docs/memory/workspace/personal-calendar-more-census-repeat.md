---
name: personal-calendar-more-census-repeat
description: PC10 census (2026-11-14) for PC11 repeating plans: how a plan's days are stored and read, what This one / This and the following and a reminder each time need, the columns proposed
---

## How are a plan's days stored and read today?
- One row of `ws_personal_plans` a plan: `starts_at`, `ends_at` (end exclusive), `all_day`, one `remind_at` moment and `reminded_at` claim (`workspace-personal-plans.md`). The lead is not kept: the drawer works it out as start (09:00 for all day) less `remind_at`.
- Readers by days: `ws_personal_plans_between()` (SQL `starts_at < to AND ends_at > from`, then the gate) for My calendar and the shared view (`my_calendar.php`), Today's card (`home.php`) and GET /workspace/personal-plans (`api_personal_plans.php`). By contact: `ws_personal_plans_of_contact()` (card Roadmaps, history). Reminders: `ws_personal_plan_reminders_run()` by `remind_at`.

## What does a repeat need that is missing?
- The rule: every day, week, month or year with a step count, until a day or N times. A row now matches only its first time in the SQL above, and one `remind_at` rings once.
- The counting exists for tasks: `ws_recurrence_nth()` (recurrence.php) counts from the anchor, a short month takes its last day. It may be reused; the working-day shift of task copies (workdays.php) is the task's and must not move a plan.
- Times are drawn, never stored: the row's start and length moved by calendar days (as `ws_personal_plan_copy_shift()`), shown through `ws_personal_plan_can_see()` on the row.

## Which columns were proposed on `ws_personal_plans`?
- `repeat_frequency` VARCHAR(8) NOT NULL DEFAULT '' ('' none, daily, weekly, monthly, yearly).
- `repeat_interval` SMALLINT UNSIGNED NOT NULL DEFAULT 1 (every N units).
- `repeat_until` INT UNSIGNED NOT NULL DEFAULT 0: the moment after the last time (exclusive, as `ends_at`), 0 = no end (architect, channel 3432). The save writes it from a count too, so readers filter by SQL with no counting: `starts_at < to AND (ends_at > from OR (repeat_frequency <> '' AND (repeat_until = 0 OR repeat_until > from)))`.
- `repeat_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 ("for N times" as the person chose it; 0 = by day or no end).
- `remind_minutes` INT UNSIGNED NOT NULL DEFAULT 0, the lead: after a time rings, the run sets `remind_at` to the next time less the lead and `reminded_at` 0. Without it the lead cannot be found again (a daily plan reminded a day before is ambiguous).

## What do "This one" and "This and the following" need?
- Delete "This one": a skipped day. Proposed table `ws_personal_plan_skips`: `plan_id` INT UNSIGNED, `day` DATE, primary key both. Change "This one": that day is skipped and a plain plan row is made for it, copying owner, visibility, people and customer.
- Proposed `ws_personal_plans.repeat_of` INT UNSIGNED NOT NULL DEFAULT 0 (key): the series a changed time came from, so "This and the following" and a delete of the series take its later changed times too.
- "This and the following": the series ends at that time's start (`repeat_until`) and a new row starts there with the change; no column more.
- Delete paths (`ws_personal_plans_remove()`, account deletion) also clear the skips; the plan-table guard (`tests/workspace_personal_plans_test.php`) gains the new table.

## What else must the step keep?
- A roadmap does not repeat: a plan with steps refuses a rule (the check, no column). A plan made from a mail may repeat (architect, 3432).
- Card Roadmaps and history list a repeating plan once.
- GET /workspace/personal-plans answers the row with its rule and, for a range, the times in it; an API change takes the same "This one" / "This and the following" (architect, 3432).
- Never changed: `ws_events` and the Work Calendar's team view, the planning board and capacity (`ws_plan_grid()`), task series (`ws_task_recurrences`, recurrence.php), the outgoing iCal subscription.

Related: `personal-calendar-more-census-template.md`, `personal-calendar-more-census-write.md`, `plans/personal-calendar-more.md`.
