---
name: walk-in-queue-report
description: Walk-in queue Report tab (WQ7, queue_report.php): waits and handling, left and not here by desk, staff and service, arrivals by hour, Desks needed (Erlang C), Excel
module: walk_in_queue_report
---

## Where is the report?
- `queue_report.php?place=<id>`, a place's Report tab, roles 0-2 (`validate_area_access($user, 'manager')`). The tabs Set-up, Desks, Today, Report are drawn by one helper, `pg_waiting_place_tabs()` (`includes/fn/walk_in_queue_places.php`), on all three screens.
- Code: `includes/fn/walk_in_queue_report.php`, loaded only by the screen; tests `tests/walk_in_queue_report_test.php`. Step WQ7 of the walk-in-queue plan, feature W11.

## What does it count?
- The numbers whose `day` falls in the last 7, 30 (default) or 90 days ending today, in the site's zone (`pg_waiting_report_range()`). Rows are read one at a time and folded (`pg_waiting_report_add()`), never loaded whole.
- Wait: from `joined_at`, or the booked time for a booking that came early or within the place's grace minutes (`pg_waiting_report_wait_start()`, the order rule without a move back), to `called_at`; a moved-back number's wait runs to its last call. Counted for called, served and not-here numbers.
- Handling: `done_at - called_at` of served numbers. Each time shows its median and its slowest tenth (90th percentile by nearest rank, `pg_waiting_report_slowest_tenth()`).
- Counts: Numbers taken, Numbers served, Left the queue (`left`), Not here (`no_show`). By desk and staff member from `desk_id` / `staff_user_id` of the call; a removed desk reads `#<id>`.
- Arrivals by weekday and hour: `joined_at` in the site's zone, all the period's days together; the grid runs from the first to the last hour seen.

## How is "Desks needed" worked out?
- For each weekday and hour: arrivals an hour on an average such day (arrivals over how often that weekday falls in the period) times the handling time gives the traffic; the fewest desks whose Erlang C mean wait stays at or under the target (`pg_waiting_report_desks_needed()`, 99 at most). Target: "Wait target in minutes" in the tools row, 10 by default, 1-120.
- Handling time used: the period's median from 20 served, else the mean of the services' starting minutes (`pg_waiting_report_handling()`); with neither the grid is not drawn.
- Each cell reads needed / desks that called (distinct desks calling in that hour, averaged over such days); bold when fewer desks called than needed.

## What does Excel give?
- `?export=xlsx` through `pg_xlsx_write_book()`: Summary, one sheet each for Desk, Staff member and Service (times in minutes, one decimal), Arrivals, Desks needed. Typed cells by `pg_waiting_report_sheet()`, text never a formula.

## How was it checked?
- Unit tests for every pure part (fold, percentile, Erlang C, staffing, sheets, tabs). The screen drawn on a sandbox at 1440 and 390 px (#PR).

Related: docs/memory/commerce/walk-in-queue-desk.md, docs/memory/commerce/walk-in-queue-store.md, docs/memory/commerce/walk-in-queue-rules.md
