---
name: workspace-my-calendar-week
description: My calendar's Week with hours (CM1): the strip of what has no hour, the hour grid 07:00-20:00 in view, what stands where, overlaps sharing the width, the line of now, New plan from an empty hour
---

## How is the week drawn?
- The days' heads (a click or Enter opens the list from that day), a strip of what has no hour, then an hour grid of 24 hours with 07:00 to 20:00 in view; the rest is reached by scrolling.
- In the strip: whole-day events (leave, all-day plans), tasks without a due time, coming copies of a repeating task, tasks over several days (the month's lanes), and an event filling the whole day (the middle of a long one).
- On the grid: an event from its time to its end on that day; a task with a due time on its due day is a 30-minute mark ending there (`WS_MY_CALENDAR_HOUR_SHORTEST`, also the shortest an item is drawn). Items that overlap share the day's width.
- A red line marks now on today's column, moved each minute.
- On a phone the week is one day at a time with whole chips (`workspace-my-calendar-phone.md`).

## Who places what?
- The server: each week day carries `hours` (type, index in its events or tasks, start, end, column, columns) from `ws_my_calendar_day_hours()`, laid by `ws_my_calendar_hours_place()` (pure, `tests/workspace_my_calendar_hours_test.php`); the answer's `now` is the minute of the day.
- Every day event carries `until` ("H:i", "24:00" past the day, '' all day) from `ws_calendar_until()`, set by all four placers (plan items, bookings, own iCal, plans).
- Drawn by `assets/js/workspace_my_calendar_week.js` (`window.PGWsMyCalendarWeek.draw()`, in the workspace bundle before `workspace_my_calendar.js`) with the screen's own chips; classes `.ws-mycal-hours*`, `.ws-mycal-block`, `.ws-mycal-now` in `backend.src.css`.

## What do the pointer and the keyboard do?
- A click on an empty hour, or Enter on a focused one, opens New plan on that day from that hour to the next; only while the plan tables are there. A right-click on a day's column or strip gives the month's day menu.
- One hour is in the tab order (today's 09:00, else Monday's); the arrows move between hours and days.
- Choosing Week from a month that holds today opens today's week; another month opens its first week.

Related: docs/memory/workspace/workspace-my-calendar.md
