---
name: workspace-calendars-modern
description: Plan (owner 2026-11-27): the calendars modernised: My calendar's week with hours, a phone month with the day's list, a drag that moves, whole chips on the Work Calendar; steps CM1 to CM5
priority: 6
---

## What is asked?
- Owner (2026-11-27): the Workspace's own screens modernised (`plans/workspace-areas-modern.md`, whose rules every step here keeps). This plan is My calendar (`workspace_my_calendar.js`, `includes/workspace/my_calendar.php`) and the Work Calendar (`startCalendar()` in `assets/js/workspace.js`).
- The rules of a person's own plans hold (`workspace/workspace-personal-calendar-rules.md`): private is the rule, a plan takes no capacity, the planning board and capacity do not change.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- CM1 (M) My calendar's week with hours. Week shows a strip for what has no hour (whole-day plans, tasks without a due time, leave) and under it an hour grid, 07:00 to 20:00 in view and the rest reached by scrolling. What has a time stands at its start with its length (a task with a due time as a 30-minute mark ending there); items that overlap share the day's width.
  - The placing is a pure function with its test. A line marks now. A click on an empty hour, or Enter on a focused hour, opens New plan with that day and hour. Choosing Week while the month shown holds today opens today's week; it opens the month's first week now.
- CM2 (M) My calendar on a phone. Under the month grid stands the chosen day's list as `.pg-list-row` lines (today at first; a tap on a day chooses it, and the day shows it is chosen); the grid keeps its coloured bars. Week on a phone is one day at a time with the hour grid, stepped by the arrows and a swipe. The bar's views are icons (`pg-seg-icons`) and the month reads short ("Nov 2026") in place of a cut name.
- CM3 (M) Items moved by a drag. On My calendar a plan of the reader's, or a task they may change, dragged to another day (Month, Week) or hour (Week) is saved through the item's own save, and the result notice offers Undo. A repeating plan asks "This one" or "This and the following" as its drawer does.
  - What the reader may not change does not lift: a booking, an own-iCal event, a plan item of others. The item's right-click menu gets "Move to…" with a date field, for the keyboard and the phone.
- CM4 (M) The Work Calendar's cells. A chip is as wide as its cell and cut only there, whole in its title; a day shows up to three and then "+N more", which opens the day's list beside the cell (a floating card of `.pg-list-row` lines, each opening what it names). The column of figures folds away behind a bar button "Figures" (kept per browser), and the grid takes the freed width; on a phone it stands under the grid, folded at first.
- CM5 (S) Close-out: `workspace/workspace-my-calendar.md` corrected (the week, the phone, the drag), the Work Calendar's notes corrected, this file deleted.

## What is left out?
- A week or day view of the Work Calendar, and a roadmap drawn as one bar a week row (`workspace/workspace-personal-calendar-rules.md`): later steps.
- A drag on the Work Calendar: its items are other people's work and plan items with their own rights; they are changed in their drawers.
