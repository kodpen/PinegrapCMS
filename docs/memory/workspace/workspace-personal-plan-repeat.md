---
name: workspace-personal-plan-repeat
description: Repeating plans on My calendar (#9565, #9611): the Repeat field, step 11.0.209, times worked out on read, This one / This and the following, a reminder each time
---

## Where does a repeat live?
- Step 11.0.209 of 2026.11.0 (`upgrade_2026_11_0_ws_personal_plan_repeat()`): on `ws_personal_plans` `repeat_frequency`, `repeat_interval`, `repeat_until` (INT, the moment after the last time, 0 = no end), `repeat_count`, `remind_minutes`, `repeat_of`; table `ws_personal_plan_skips` (`plan_id`, `day`). Probe `ws_personal_plan_repeat_ready()`.
- Owner (2026-11-14): the columns and the table approved (channel 3434-3438, recorded with #9553); the architect set `repeat_until` to a moment (3432).
- Code: `includes/workspace/personal_plan_repeat.php`, required by `personal_plans.php`; allowed by the plan-table guard.

## How are the times drawn?
- Never stored: `ws_personal_plan_times()` (pure) moves the row's start and end by whole calendar days, so a time of day survives a clock change. A month without the day takes its last day, as task series do (`ws_personal_plan_repeat_day()`). No working-day shift.
- `ws_personal_plans_between()` reads a running series for any span and lays it out as its times (`ws_personal_plans_as_times()`, `time_day` on each), only from rows the gate `ws_personal_plan_can_see()` let through. So My calendar, the shared view and Today draw each time; a chip opens the drawer on its time (`plan_day`, `?plan=N&day=Y-m-d`).
- Each time is marked before it is opened (`repeat` on the event): the repeat icon on the month's chip and the week's block, the rule in the list row's meta line ("My plan · Every week"; architect, channel 3590).
- Card Roadmaps and history list a series once; the API too, with its rule and times (`workspace-personal-plans-api.md`).

## What does the drawer ask?
- "Repeat": Does not repeat, Every day, week, month, year; "Repeat every" N (1-99); "Repeat ends": Never, On a day (Last day of the repeat), After a number of times (1-999). `ws_personal_plan_repeat_clean()` turns a day or a count into `repeat_until` on save, so no reader counts. Two rows of two under Reminder: Repeat with Repeat every (unit beside the number), then Repeat ends with Number of times or Last day of the repeat (architect, channel 3590).
- A plan longer than the step between its times is refused (times never overlap).
- A roadmap does not repeat: the Roadmap section hides while a repeat is set; the save refuses a rule on a plan with steps, and `ws_personal_plan_steps_save()` refuses steps on a repeating plan. A plan made from a mail may repeat (architect, 3432).
- Save or Delete on a repeating plan asks "This one" / "This and the following" (the drawer's own `askTimes()` in workspace_my_calendar.js).

## What do the two answers do?
- This one, change: the day goes into `ws_personal_plan_skips` and a plain plan is made from the drawer with `repeat_of` = the series and its customer. Delete: the day is skipped.
- This and the following on a later time: the series ends at that time's start (`ws_personal_plan_series_cut()`); a change makes a new series from the drawer and hands it the later changed times and skipped days; a delete removes them. On the first time it is the whole series.
- Deleting a whole series deletes the times changed out of it (`repeat_of`). A write without `repeat` (the API) keeps the rule.

## How does each time remind?
- `remind_minutes` keeps the lead; `remind_at` is the next time's reminder (`ws_personal_plan_next_remind()`). The run tells the owner, then arms the next time, skipped or ended times passed over; with none left it stays claimed.
- The line names the time it was written for (`&day=`): the latest reminder moment not after its `created_at` (`ws_personal_plan_reminded_time()`), so "1 day before" on a daily plan names tomorrow (#9665).

## What did not change?
- Work Calendar, planning board, capacity, task series (recurrence.php), the iCal feed.

Tests: `workspace_personal_plan_repeat_test.php`, `workspace_personal_plan_reminder_test.php`.
