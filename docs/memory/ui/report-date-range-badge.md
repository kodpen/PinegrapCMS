---
name: report-date-range-badge
description: The order and visitor reports' date range badge under the period controls: two get_absolute_time() <time> elements printed as markup, never through h() (#9042)
---

## How is the date range under the period buttons drawn?
- `view_order_report.php` and `view_visitor_report.php` build `$output_date_range` from `get_absolute_time(array('timestamp' => …, 'type' => 'date'))`, which returns `<time datetime="Y-m-d">Per 15 Eki 2026</time>` (HTML unless `'format' => 'plain_text'`); the stop date follows after ` - ` when it differs.
- The badge (`<span class="badge fg-body fw-light border-2">`) prints it as it is: the text is `date()` output and the translated day and month names.

## What went wrong before?
- The visitor report printed it through `h()`, so the badge read `<time datetime="2026-10-15">…</time> - …` as text (#9042). Kept as markup, like the order report, the `datetime` attributes stay for machines.
- `tests/report_period_display_test.php` checks the badge and that the date is a `<time>` element.
