---
name: high-volume-counter-rules
description: Rules for a new high-volume counter, log or report: write-time buckets, sha1 keys, no query string in a key, MAX(id) caps, totals in SQL, ONLY_FULL_GROUP_BY, DST-safe day steps, derived data rebuilt.
---

## How is a busy counter or log table designed?
- Aggregate when writing: a bucket key and one `INSERT ... ON DUPLICATE KEY UPDATE`; a repeat raises a counter and adds no row (`waf_log`, `visitor_stats_hourly`, `visitor_content_hourly`).
- A key over utf8mb4 strings is stored as `sha1(...)`: a plain composite UNIQUE passes the 767-byte limit. Fixed-width columns may form the key themselves (`submitted_form_view_stats`).
- Never put the query string or another per-request payload in a key: under fuzzing every request becomes a new row, exactly when aggregation matters.
- A row cap is computed with `MAX(id)`, never `COUNT(*)` (an index scan on a live request).
- An index is paid on every write: keep only those a report query really uses.

## How is a report read?
- From the summary tables, never by counting raw `visitors` or `waf_log` rows.
- Totals in SQL (`GROUP BY` + `SUM()`), always over every matching row; detail rows in a separate `LIMIT`/`OFFSET` query; the full data as a streamed CSV (`MYSQLI_USE_RESULT`).
- Aggregated rows are summed (`SUM(hit_count)`, `SUM(page_views)`), never counted with `COUNT(*)`, or an attack looks like a handful of events.
- ONLY_FULL_GROUP_BY: no `GROUP BY 1, 2`, no ungrouped column, `SHA1()` after the grouping.
- No percentile over raw rows with a big `OFFSET` (MySQL walks every skipped row): the performance report dropped its p95 for `perf_stats` sums and a slow-request count, and keeps full detail only for the outliers.

## How are backfills written?
- Step days with `strtotime('+1 day', $ts)`, never `+ 86400` (DST splits a day).
- Do not sweep a source table while a rollup or backfill still reads it; when done `RENAME TABLE ... TO <table>_old`, not `DROP`.
- Summary tables are derived data: when one is wrong, `TRUNCATE` and rebuild from the source; a bucket cannot be fixed in place.

## Where are the stories behind each rule?
- docs/memory/visitors/visitor-rollup-tables.md, visitor-report-sql-pitfalls.md, visitor-rollup-backfill-runtime.md, visitor-rollup-consistency.md, article-view-daily-bucket.md; docs/memory/waf/waf-log-aggregation-and-cap.md; docs/memory/platform/performance-monitor-cost.md.
