---
name: performance-monitor-cost
description: Request-path cost rules for perf_monitor_shutdown(): fastcgi_finish_request, row cap, index cost, p95 without big OFFSET, PERF_MONITOR_SAMPLE_RATE.
---

## What must the performance monitor never do?
`perf_monitor_shutdown()` writes one row per request; the measuring tool must not distort what it measures.
- `fastcgi_finish_request()` is PHP-FPM only. mod_php, CGI and IIS lack it, so all shutdown work runs before the response completes and the delay really reaches the visitor. The function keeps a `$flushed` flag: if it could not flush, the most expensive work (the sweep) never runs.
- Row cap `PERF_MONITOR_MAX_ROWS` (default 200,000), same reason as `waf_log`: time-based retention cannot bound the table on a busy site. Computed with `MAX(id)`, not `COUNT(*)`.
- Indexes are write cost. Report queries were audited: `idx_peak_memory` (report sorts a derived table), `idx_script` (`GROUP BY` on a `CASE` expression) and `idx_duration` (percentile query already filtered by `log_timestamp`) were never used. Five secondary indexes became two.
- Never compute p95 with a huge `OFFSET`: MySQL walks every skipped row, so on millions of rows the report page becomes the heaviest query on the server. Above 20,000 rows, read the slowest 5% slice and take its minimum; by definition the 95th percentile lies in that slice, same result.

## How do I reduce load on a busy site?
- Lower `PERF_MONITOR_SAMPLE_RATE` first; do not switch the monitor off. 10% sampling gives the same averages and percentiles with a tenth of the writes.
- All constants are listed with comments in `data/config(default).php`.
