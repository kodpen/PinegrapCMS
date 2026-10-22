---
name: performance-monitor-cost
description: Performance monitor cost rules (perf_monitor_shutdown()): hourly perf_stats buckets, slow requests only in perf_log, no percentile, sanity gate, flush-gated sweep, row cap, sampling.
---

## What does each measured request write?
- `perf_monitor_shutdown()` (`includes/fn/seo.php`) folds it into an hourly `perf_stats` bucket with one `INSERT ... ON DUPLICATE KEY UPDATE` (key `sha1(area|label[|entity_type|entity_id])` + `hour_start`, 2026.3.2). Only a request at or over `PERF_MONITOR_SLOW_MS` (1000 ms) also gets a detail row in `perf_log`, with its address, user agent and query string.
- Why: one site reached 1.6 million `perf_log` rows (one per request); the report took 40 s because its percentile query walked them, while every visitor inserted into the same table.
- A failed request (status 400+) is grouped under `[<status>]`, not its URL: scanners choose URLs, and one bucket per probed path would let a stranger decide the table's size. `api.php` is not measured by default (`PERF_MONITOR_IGNORE_SCRIPTS`).
- A duration below 0 or over an hour is dropped: a summary row never forgets one wrong value, and MySQL clamps out-of-range numbers silently (strict mode is off).

## What must the monitor never do on the request path?
- `fastcgi_finish_request()` is PHP-FPM only. mod_php, CGI and IIS lack it, so the shutdown work runs before the response completes and the visitor waits. The `$flushed` flag keeps the sweep (1 in 200 requests: retention `PERF_MONITOR_RETENTION_DAYS`, 30 days, then the `PERF_MONITOR_MAX_ROWS` cap of 200,000 buckets) off a request that could not flush.
- Indexes are write cost: `idx_peak_memory`, `idx_script` and `idx_duration` on `perf_log` were never used by a report and were dropped (2026.3.1).

## Why is there no percentile?
- `view_performance_log.php` reads `perf_stats`; averages come from sums (`total_ms / hits`), never an average of averages. A percentile needs every duration, which is no longer kept; the slow-request count (`slow_hits`) replaces it and links to each slow row. Do not bring a p95 back over raw rows.

## How do I reduce load on a busy site?
- Lower `PERF_MONITOR_SAMPLE_RATE` first rather than switching the monitor off (the Site Settings switch, `PERF_MONITOR_ENABLED`; `PERF_MONITOR` false in `data/config.php` stops it before any timer). 10% sampling gives the same averages with a tenth of the writes.
- The constants are listed with comments in `data/config(default).php`; `PERF_MONITOR_MIN_DURATION_MS` is listed there but nothing reads it.
