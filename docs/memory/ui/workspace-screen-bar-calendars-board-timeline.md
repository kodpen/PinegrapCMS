---
name: workspace-screen-bar-calendars-board-timeline
description: The one-line bar and empty screens of Work Calendar, My calendar, Planning Board and Decision Timeline: barSteps(), pressed segments, Clashes count, inline filters
---

## What does the Work Calendar's bar hold?
- `startCalendar()` in `assets/js/workspace.js`, the rule of `workspace-screen-bar.md`: the month's arrows round "This month" (`barSteps()`), the month's name (`pg-title-section`); Tasks, Conversations and Plan items as a `pg-seg` whose segments are pressed each on its own; the grow slot; the agents toggle; "Filter".
- "Filter" holds person, department, priority and status. Person and department go to `ws_calendar`; priority and status only narrow what is drawn. `?month=`, `?person=`, `?agents=1` are as before.
- On a phone the segments and the agents toggle move into the "Filter" box.

## What does My calendar's bar hold?
- `workspace_my_calendar.js` builds it from `h.bar` (`featureHelpers()`: `steps`, `views`, `label`, `button`): New plan (filled, shown once `data.plans`), Work Calendar (quiet, its word in `.ws-bar-label`), previous / Today / next, the range's name, the views Month, Week, List (`barViews()`, a select when narrow). The saved view (`pg.ws.myCalendar.view`) and `?view=`, `?date=` are kept.

## What does the Planning Board's bar hold?
- `startBoard()`: Add to the plan (filled), New task (quiet), the week's arrows round "Today", the range, 1 week or 2 weeks (`pg-seg`, `aria-pressed`); the grow slot; "Only me" (a switch), the agents toggle, Clashes with its count, "Filter" (department, priority).
- The count is `ws_conflicts` over the same two weeks its drawer lists, asked again after each load; no count, no badge.
- Nobody on the board: `emptyScreen()` (violet `bi-people`, "Nobody on the board", the old sentence); with a department chosen, `barEmptyFound()` and Clear.

## What does the Decision Timeline's bar hold?
- `startTimeline()`: channel, person and the date range stand in the bar (`barFilter({ inline: true })`, leading the line); with departments in the team they are four fields, so all go behind "Filter". Then "Notes too" (a switch), the agents toggle, the search (`barSearch()`).
- Every address parameter (`channel`, `department`, `person`, `from`, `to`, `q`, `notes`, `agents`) is kept. "Clear filters" in the summary and Clear on the empty screen reset the fields and the search.
- No decision: `emptyScreen()` (blue `bi-clock-history`, "No decisions yet", the old sentence); filtered to nothing: `barEmptyFound()`.

## What pins it?
- `tests/workspace_task_filters_test.php` (`test_workspace_task_filters_calendar_board_timeline_bars`). Built in WI7 of `plans/workspace-interface-steps-2.md`.
