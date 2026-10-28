---
name: report-screen-controls
description: Report and panel controls on Bootstrap 6: period changers, named arrows, labelled layout and date selects (#2716), calendar controls, comment rating, accessibility tables (#2900)
---

## How is a report's period changer built?
- The order and visitor reports print one `.btn-group.btn-group-sm` per unit (Year, Month, Week, Day), `role="group"` named by the unit, of `btn btn-outline theme-secondary` links: previous (`bi-chevron-left`, `aria-label` Previous), the current period, next (`bi-chevron-right`, Next). Bootstrap 6's group, join and size rules only select `[class*=btn-]` children, so bare `.btn` links (the v5 markup with `border-*` utilities) kept their full corners and never joined.
- They cannot call `pg_period_nav()`: their links carry the report's `&id=`. The visitor report's next-month link dropped the id before (#2716). The shipping report's pair uses `start_date` / `end_date` and is a `role="group"` named Period with `btn-text` chevrons around the range.

## How are the layout selects named?
- The Summarize by / order by rows (order and visitor reports, `edit_form_list_view.php`) are plain `.row`s of `<label for>` + select; liveform prints a select's id only when `output_field()` gets `'id'`. They sat in an `.input-group-text row` with the texts in divs: six unnamed comboboxes per screen.

## How is the Grand Total percentage printed?
- `pg_format_number(100, 2) . '%'` like the other cells, never a literal `100.00%` (a comma-decimal site showed it beside `45,23%`).

## How are the date range filters of list screens named?
- Each date (orders, submitted forms, contacts, commissions) is a `<fieldset>` whose `<legend class="form-label">` is Start / End, and its month, day and year selects carry `aria-label` Month / Day / Year. A `<label>` without `for` named nothing: the runtime binder in `backend.src.js` only binds a label to a next field that has an id.
- The performance log's Search, Time Range and Area labels point at `perf_log_search`, `perf_log_range`, `perf_log_area`.

## Other report formats?
- The visitor detail pager links are First page / Previous page / Next page / Last page in a `<nav>` named Pagination.
- The SEO drawer's group weight prints `lang('{var:1}%')` (TR `%40`, EN `40%`); the Search Console chart days are `m/d` on a `month_day` site, else `d.m`.
- The cash flow toolbar form wraps (`flex-wrap row-gap-2`), each date label in one `d-inline-flex` span with its field: panel CSS keeps a toolbar form on one line, so at 390 px Apply sat off-screen.

## Panel calendar, comment rating, accessibility report?
- `get_calendar()`'s `$user` branches (`calendars.php`, dashboard widget): the `<` / `>` links carry `aria-label` Previous month / Next month (monthly) and Previous week / Next week (weekly); the Calendar, View and Status selects carry `aria-label`. Front-end branches are site output and stay as they are.
- Edit Comment's star radios are `input.btn-check` with no inline `display:none` (that took them out of the tab order; the panel rule already hides them with `clip`), each named Rated {var:1} out of 5, in a `role="radiogroup"` labelled by `#comment_rating_label`; the screen's own `@layer custom` block draws the focus outline.
- `view_accessibility.php` keeps both tables in `.table-responsive`: the `text-nowrap` criterion column pushed a 390 px phone sideways.

Tests: `tests/comment_calendar_names_test.php`, `tests/report_screens_bs6_test.php`, `tests/report_formats_test.php`, `tests/list_period_test.php`, `tests/panel_accessible_names_test.php`.
