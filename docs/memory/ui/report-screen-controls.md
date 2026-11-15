---
name: report-screen-controls
description: Report and panel controls on Bootstrap 6: period changers, named arrows, labelled layout and date selects (#2716), calendar controls, comment rating, accessibility tables (#2900)
---

## How is a report's period changer built?
- The order report prints one `.pg-seg` per unit (Year, Month, Week, Day), `role="group"` named by the unit: previous (`.pg-seg-item.pg-report-step`, `bi-chevron-left`, `aria-label` Previous), the unit, next (Next); the period stands beside as text (`report-period-text.md`). Look A's tools row (#8981, `look-report-screens.md`). The visitor report has date presets instead (`visitors/visitor-report-filters-and-form.md`).
- Before look A they were `.btn-group` rows of bare `.btn` links; Bootstrap 6's group rules only select `[class*=btn-]` children, so they never joined (#2716).
- They cannot call `pg_period_nav()`: their links carry the report's `&id=` (and `&ready=`, `commerce/order-report-ready-reports.md`). The visitor report's next-month link once dropped the id (#2716). The shipping report's pair uses `start_date` / `end_date`, a `.pg-seg` named Period of two steps.

## How are the layout selects named?
- The Summarize by / order by rows (order and visitor reports, `edit_form_list_view.php`; inside each report's settings drawer, `report-view.md`) are plain `.row`s of `<label for>` + select; liveform prints a select's id only when `output_field()` gets `'id'`. They sat in an `.input-group-text row` with the texts in divs: six unnamed comboboxes per screen.

## How is the Grand Total percentage printed?
- Through the `'{var:1}%'` template like every other percent cell, never a literal `100.00%` (`report-table-rows.md`).

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
