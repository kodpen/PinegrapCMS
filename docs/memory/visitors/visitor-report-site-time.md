---
name: visitor-report-site-time
description: Why the Visitor Report's year/month/day groups and Date filter go through pg_site_time_sql(): the fixed session offset, daylight saving shifts per row, the window, what is left
module: site_time_sql
---

## What was wrong?
- `init.php` sets the MySQL session to the site's offset at request time (`SET time_zone = date('P')`), not to a named zone: many hosts lack MySQL's time zone tables (deliberate; switching to a named zone is an architect question).
- In a daylight saving zone `FROM_UNIXTIME()` therefore read every timestamp from the other season an hour off. Europe/Berlin on 30 Oct 2026 (+01:00): a visit at 1 Oct 00:20 CEST grouped as 30 September, the default 31-day report showed a day outside its own heading, and `Date = 10/01/2026` dropped the first hour of 1 Oct and took the first hour of 2 Oct. The window (`mktime()`), its heading, the CSV Date column and the daily summary (`strtotime()`) all follow DST, so only the raw-row groups were off. Zones without DST (Turkey) were never affected.

## What does pg_site_time_sql() do?
- `includes/fn/site_time_sql.php`, pure. `pg_site_time_shifts($from, $to, $zone, $now)` lists, from `DateTimeZone::getTransitions()`, the shift (zone offset at that moment minus the session offset `date('Z')` now) per segment of the window, equal neighbours merged.
- `pg_site_time_sql($column, $format, $from, $to)` gives `FROM_UNIXTIME(col, fmt)` when every shift is 0, `FROM_UNIXTIME(col ± d, fmt)` for one shift (a window wholly in the other season), else `FROM_UNIXTIME(CASE WHEN col < t1 THEN col + d0 ... ELSE col + dn END, fmt)`.
- A row outside the window takes the nearest end's shift, so the window must hold every row the query reads.

## Which window does the report give it?
- The date changer's `$start_timestamp` / `$stop_timestamp`; with a Date filter (which replaces that range) `MIN(visitors.start_timestamp)` (indexed) through `time()`. Used for the Date filter operand and `get_summarize_by_column()`'s year, month and day.

## What is still on the fixed offset?
- `view_order_report.php`, the `view_orders.php` sparkline, the API sales report (`includes/api/resources/reports.php`) and the rollup backfill in `includes/fn/seo.php`; each can reuse the helper in its own step.

Related: docs/memory/visitors/visitor-rollup-backfill-runtime.md, docs/memory/visitors/visitor-report-sql-pitfalls.md
