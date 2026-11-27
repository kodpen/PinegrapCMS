---
name: workspace-my-calendar-phone
description: My calendar below 576 px (CM2): the month's chosen day listed under the grid, the week one day at a time stepped by the arrows and a swipe, icon views, the short range name in the bar
---

## What does the month show on a phone?
- The grid keeps its coloured bars. Under it stands one day's list (`.ws-mycal-picked`): its heading with the "Today" pill, then the List view's `.pg-list-row` lines (`daySection()`, shared with List), tasks over several days included on every day they cover.
- The day is today at first (the month's first day when today is in another month). A tap or Enter on a day chooses it: the cell is ringed (`.ws-cal-chosen`, `aria-pressed`) and the list redrawn. From 576 px a day still opens the List view from that day.
- A day with nothing: its heading and "Nothing on your calendar this day."

## What does the week show on a phone?
- One day with the hour grid (`workspace_my_calendar_week.js` drawn with that day alone; the column count is `--ws-mycal-days`). Its chips are whole (`.ws-mycal-one-day`), not the month's bars. The bar's title is that day's name.
- The day is the one stepped to, else today, else Monday. The bar's arrows and a sideways swipe on the card (over 50 px and mostly sideways) step one day; past Sunday or before Monday the next or previous week is read. "Today" returns to today.

## What changed in the bar?
- The views Month, Week, List are icon segments (`.pg-seg-icons`, icons `bi-calendar3`, `bi-calendar-week`, `bi-list-ul`), never the narrow bar's select on this screen; below 576 px the chosen one keeps its word.
- On a phone the range reads short: `label_short` from `ws_my_calendar_label_short()` ("Nov 2026"; "9 – 15 Nov" or "30 Nov – 6 Dec" for a week, no year; "27 Nov" for the List, the day it runs on from), the full name as the title's tooltip. Pure, tested in `tests/workspace_my_calendar_test.php`.
- The bar's one line (`ui/workspace-screen-bar.md`) did not hold six parts at 390 px: on a phone New plan floats at the bottom right (`.ws-mycal-fab`, the Data list's placing, shown only while plans can be made) and the Work Calendar button is hidden; the Work Calendar stays in the Workspace menu's Tools.
- Width changes (a turned phone) redraw through `matchMedia('(max-width: 575.98px)')`.

Related: docs/memory/workspace/workspace-my-calendar-week.md, docs/memory/workspace/workspace-my-calendar.md
