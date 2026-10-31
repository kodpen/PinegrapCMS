---
name: api-sales-report-periods
description: GET /reports/sales: periods are site days and months by site.timezone's rules across clock changes; only periods with orders come back, at most 400; more is refused with 422 instead of being cut
---

## What does GET /reports/sales add up?
- `api_reports_sales()` (`includes/api/resources/reports.php`): complete and exported orders (or the one `status` asked for) between `from` and `to`, grouped by day or month of the site's zone. Only periods that hold orders are rows; empty days are not filled in.
- `api_reports_sales_tally($rows, $max)` builds `periods` and `totals` from the grouped rows; `totals` is the sum of the periods.

## How is an order's day found?
- The session's `time_zone` is `date('P')`, the offset of the moment of the request (`init.php`), so `FROM_UNIXTIME()` put every order of a window at today's offset: asked in winter, a Berlin order at 00:30 on 31 March (summer, 22:30 UTC) fell on 30 March, and a window across a clock change could count a period too many against the limit (issue #3411).
- Now `api_reports_sales_zone_offsets($from, $to, $zone)` lists the zone's offsets over the window as stretches (`DateTimeZone::getTransitions()`, the zone PHP runs in, which `GET /meta` gives as `site.timezone`), and `api_reports_sales_period_sql()` adds the stretch's offset to `order_date` in a `CASE` and reads the sum as a date on `CAST('1970-01-01 00:00:00' AS DATETIME)`. No `CONVERT_TZ` (MySQL's named zone tables may be missing on a host) and no session zone take part; grouping, `LIMIT` and the window limit still run in SQL on those periods.
- Tested across Berlin's spring and autumn 2026 changes, by day and month, in `tests/api_reports_test.php` (PR #3911).

## What went wrong before?
- The query ended `LIMIT 400` and the totals were added up from the rows that came back, while the answer gave `from`/`to` back as asked. With orders on more than 400 days of a daily window (any window over ~13 months for a busy shop) the client got the first 400 days and their totals: 500 days of one 100-kuruş order each read as `totals.total = 40000`, last period 2016-02-04 (issues #910, #919; fixed by PR #1014). By month the same cut sat at 400 months.

## What does it do now?
- The query reads `LIMIT 401` (`api_reports_sales_max_periods()` + 1). When 401 rows come back, `api_reports_sales_tally()` returns null and the endpoint answers 422 `validation_failed`, field `from`: "Orders fall on more than 400 days of this window. Ask for a shorter one, or by month." (by month: "… months …"). Otherwise every period with orders is in the answer and the totals are the window's.
- A long window over a quiet shop still works: the limit counts periods with orders, not calendar days. That is why the visitor reports' rule (`api_visitors_window()`, every window over 400 days is refused) was not copied: it would have refused windows that answer correctly today.
- The route description in `includes/api/schema.php` says the same. Tested in `tests/api_reports_test.php`.
