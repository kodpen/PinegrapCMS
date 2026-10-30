---
name: list-period-week
description: Week's first day (Monday in Turkish, Sunday otherwise) for the list screens' period navigator (#2039) and the get_calendar() grids (#3328)
module: list_period
---

## Which day does a week start on?
- `pg_list_period_week_first_day()` (`includes/fn/list_period.php`) is 1 (Monday) when the panel language is Turkish (`lang(array('info' => '')) === 'tr'`), else 0 (Sunday): the same choice as the date pickers' `firstDay` in `get_date_picker_format()`.

## Where do the ‹ Week › arrows go?
- `pg_list_period_week_steps($range)` returns `previous` and `next` (the week before and after the one the range starts in) and `current` (this week). The orders, submitted forms, contacts, order report and visitor report screens read it.
- Before (#2039, last part) each screen built Sunday-to-Saturday weeks itself with `strtotime('last sunday')`, about 40 lines per screen.
- `pg_list_period_week($timestamp, $offset, $first_day)` steps days with `mktime()`, so a week across a daylight saving change or the year end stays seven whole days.

## How does the navigator know a range is a week?
- `pg_period_unit($range, $first_day = null)` (`includes/fn/output.php`) calls seven days that start on the week's first day a week; null reads `pg_list_period_week_first_day()`, so the unit menu marks the weeks the screens step to. It used to accept only a Sunday start.

## Do the calendars use it?
- Yes. `get_calendar()` (`includes/fn/calendar.php`) reads `pg_list_period_week_first_day()` for the monthly grid (head order from `pg_calendar_day_order()`, padding cells from `pg_calendar_month_padding()`, row breaks compared with `date('w')`, never the English `date('l')` name) and for the weekly range and its ‹ › arrows (`pg_list_period_week()`). Before (fixes #3328) the Turkish panel's Calendars screen, the dashboard calendar card and the site calendar view pages started on Sunday.
- The designer `calendar_view` widget keeps its own `week_start` setting.

Test: `tests/calendar_week_start_test.php` (column order, padding for both first days, no Sunday week left in the module), `tests/list_period_test.php` (Monday and Sunday weeks, a Sunday start on a Monday-first site, a clock change, the year end, the navigator recognising its own weeks, no screen keeps its own Sunday week) and `tests/output_test.php` (`pg_period_unit()` with both first days).
