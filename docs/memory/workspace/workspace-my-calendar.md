---
name: workspace-my-calendar
description: My calendar (workspace_my_calendar.php, PC2): the Work Calendar opened on the reader alone by month, week and list; what it reads, its gate, its ways in from the E-mails toolbar and the Workspace menu
---

## What does My calendar show?
- `workspace_my_calendar.php`: the reader's own tasks (those they are assigned to, with coming copies of their repeating tasks), the plan items that apply to them (`ws_event_applies()`: company, their departments, items naming them), their confirmed bookings and their own iCal layer (`workspace-personal-ical.md`). No conversations, no month figures, nobody else's work.
- Views: Month (whole weeks, Monday first, like the Work Calendar), Week (Monday to Sunday, tall cells, nothing cut), List (`WS_MY_CALENDAR_LIST_DAYS` = 30 days, only days that hold something). Choosing List opens it on today, under a day heading with a "Today" pill; earlier days are one step back with the arrow. A line whose kind repeats its title (a leave day) is written once. The view is kept per browser (localStorage `pg.ws.myCalendar.view`); the address carries `?view=&date=`.
- A task opens the task drawer, a plan item its form, a booking the agenda; an own-iCal event is read-only. A day's right-click (long press) offers New task and Add plan item for the reader. A click on a day (or its "+N more") opens the list from that day; in the list a task over several days is listed once, on its first day in the range.

## Where is it built?
- `includes/workspace/my_calendar.php`: `ws_my_calendar($viewer, $view, $anchor)` (action `ws_my_calendar`, listed in `includes/panel/actions.php`); pure parts `ws_my_calendar_range()`, `ws_my_calendar_task_days()` (same laying as the Work Calendar: an open late task runs on to today, a closed one ends on its closing day), `ws_my_calendar_task_item()`, `ws_my_calendar_sort_events()` (whole-day first, then by time), `ws_my_calendar_label()`.
- Day placement reuses `ws_calendar_plan_place()`, `ws_booking_calendar_place()`, `ws_personal_ical_place()`.
- Drawn by `assets/js/workspace_my_calendar.js` (`window.PGWsMyCalendar`, in the workspace.min.js bundle, mode `my_calendar`) with the Work Calendar's `.ws-cal-*` cells and look A's toolbar parts (`.pg-seg`, `.pg-icon-button`, `.pg-button-quiet`) and list rows (`.pg-list-row`). Multi-day task lanes come from `calendarLanes()` in workspace.js, shared with the Work Calendar. `featureHelpers()` gives it `calendarLanes`, `taskDrawer`, `planItem`, `statusName`, `dmy`, `pressKeys`, `onContext`.

## Who may open it?
- Team members only: `ws_screen_gate()`; a channel guest is no member. The action never takes a person from the request: it is always the reader's own, so no administrator reads another person's calendar here.
- The Work Calendar, the planning board and capacity are unchanged (`plans/personal-calendar-plans.md`).

## Where are its ways in?
- The E-mails toolbar, on Communication › Incoming e-mail (`messages_mail.php`): `ws_mail_list_my_calendar_html()` in the `secondary` slot of `ws_mail_list_filters_html()` (`mail_list.php`), after Mark all as read. "Check e-mail" (IB2, `plans/mail-inbox-features.md`) goes beside it in the same slot.
- The Workspace menu: a Tools tile `my_calendar` right after Work Calendar (`ws_nav_groups()`), marked by `ws_side_screen()`. The screen links back to the Work Calendar.

Test: `tests/workspace_my_calendar_test.php`.
