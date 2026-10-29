---
name: list-period-navigator-and-clock
description: List screens: the month arrows step from the last shown month, the range heading follows the date order (list_period.php), pickers and times follow the 24-hour setting (#2039, #2040, #2058)
module: list_period
---

## Where do the ‹ Month › arrows go?
- `pg_list_period_month_steps($range)` gives the whole month before and after the LAST month of the range on screen; the orders, contacts and visitor report screens read `previous` / `next` from it. Before (#2039) each screen computed start month ± 1 on its own: from the default "26 September – 27 October" Previous showed August and the contacts' all-time range (from 2020) stepped to April 2020. A missing or impossible stop month counts as the current month.
- The submitted forms list and the order report read it too since #2039's second part; the order report's Next month link also keeps the report's `id` now (it dropped it and opened the default report). The week arrows: `list-period-week.md`.

## How is the range heading written?
- `pg_list_period_heading($range)` prints both dates in the site's date order with the month name in the panel language: "26 Eylül 2026 – 27 Ekim 2026" on a day/month site, "September 26, 2026 – October 27, 2026" otherwise; a one-day range once. The orders, contacts, visitor report and submitted forms headings use it (they printed "Eylül 26, 2026"). The order report's range badge comes from `get_absolute_time()` and is `fg-body` (it was `fg-black`, unreadable in the dark theme, #2058).

## Which clock do the pickers and time displays use?
- `pg_time_format_code()` (`includes/fn/core.php`) is the `date()` code of the site's clock (`TIME_FORMAT`: `H:i` for `twenty_four_hours`, else `g:i A`) and `pg_picker_time_format()` the jQuery UI timepicker's `HH:mm` / `h:mm TT`. The three picker-format scripts (`get_date_picker_format()`, `get_date_time_picker_format()`, `get_time_picker_format()`), the calendar event form's default times, the calendar's event time ranges and the campaign profiles' schedule time read them. Before (#2040) every one of them hard-coded the 12-hour clock whatever the setting said.
- Form values (`prepare_form_data_for_output()` for `time` / `date and time`), the time help texts (`pg_time_format_help()`) and the commerce settings' and shipping method form's "current site time" follow it as well (`form-time-24-hour.md`); the order view's withdrawal date is in its own screen's PR. Inputs (native `<input type="time">`, `datetime-local`) and the designer and Workspace JavaScript displays are not covered by this.

## What changed on the users list?
- The folder/calendar/form/group access menus (`pg_user_list_names_cell()`, `includes/fn/user_list.php`) print their names in `fg-body` instead of `fg-black` (unreadable in the dark theme) and open with `data-bs-strategy="fixed"`, so the menu is not clipped by the table's scroll box (#2058; the other screens were done in #2481).

Test: `tests/list_period_test.php` (month steps around a month, from the last shown month, across the year, without a range; the heading in both orders; the clock codes; the picker scripts carry the format).

## Which words label a date range filter?
- "Start" / "End" ("Başlangıç" / "Bitiş"), not "From" / "To": those keys also name e-mail senders and recipients, so one Turkish value could not fit both (#2052). Orders, submitted forms, contacts, commissions, site log, audit trail, profit, expenses, accountant package, ERP export and the Workspace rule hours use them; the list filters' month/day/year selects are named (`report-screen-controls.md`, #2716).
