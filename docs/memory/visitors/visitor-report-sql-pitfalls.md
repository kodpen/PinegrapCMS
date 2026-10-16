---
name: visitor-report-sql-pitfalls
description: How the visitor report page aggregates in SQL not PHP, ONLY_FULL_GROUP_BY traps in rollup queries, and why only the visitors table left MyISAM.
---

## How should the report page aggregate?
`view_visitor_report.php` used to fetch EVERY matching visitor row without LIMIT and aggregate in a PHP array; it even appended every row to `$results[...]['visitors'][]` with the `detail` box closed and never used it, which is why the page would not open.
- Totals come from `GROUP BY` + `SUM()`/`COUNT()` and ALWAYS cover all matching rows (paging does not affect totals).
- Detail rows come from a separate query with `LIMIT`/`OFFSET`, 500 at a time.
- Full data: streaming CSV with `MYSQLI_USE_RESULT` + `flush()` every 1000 rows; peak memory is one row. The `mysqli_query()` default pulls the whole result into PHP, so not accumulating in an array is not enough.
- `$where`, `prepare_sql_operation()` and all `summarize_by` options are unchanged; advanced filters still read the raw `visitors` table.

## ONLY_FULL_GROUP_BY traps (MySQL 5.7+ default)
- No ordinal in GROUP BY (`GROUP BY 1, 2`): removed in MySQL 8. Write the alias.
- Do not select a non-grouped column with GROUP BY. The `visitor_content_hourly` hourly-peak query resolves ties in PHP (`ORDER BY stat_hour, id`, first row wins), not with `GROUP BY c.stat_hour`.
- Compute the bucket key AFTER grouping. `SHA1(...)` inside a GROUP BY select asks MySQL to prove the hash is functionally dependent on its inputs, which varies by version. Backfill groups in the derived table and hashes outside.

## Why was `visitors` moved off MyISAM?
- Table-level locks. `update_visitor_page_data()` runs `UPDATE visitors` on EVERY page view; in MyISAM an UPDATE locks the table exclusively, so page views already queued behind each other. MyISAM concurrent insert does not help: it only works when the table has no holes, and a constantly updated table always has holes.
- A 20-second report read lock on top makes every site visitor queue; that was why the site slowed while the panel was open.
- Second reason: after an unclean shutdown a MyISAM table this size needs `REPAIR TABLE`, which can take hours with the table unwritable.
- `visitors` went first (2026.3.6); 2026.4.8 moved the remaining tables, `search_items` (FULLTEXT) last in step 8.14.
