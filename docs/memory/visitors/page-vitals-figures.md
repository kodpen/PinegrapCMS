---
name: page-vitals-figures
description: Page speed figures from page_vitals_hourly: the p75 estimate from buckets and sums, the figures of a row, site / pages / trend readers, the reports/vitals API route and MCP tool
module: page_vitals
---

## How is the p75 worked out without raw values?
- `pg_vitals_p75($metric, $good, $needs, $poor, $sum)` (pure): rank = 0.75 × samples, placed in its bucket. Good is taken as uniform from 0 to the good threshold, needs work as uniform between the thresholds. The poor bucket is open, so it gets the mean the sum leaves after the other buckets are counted at their middles, as an exponential tail above the poor threshold.
- A bucket that holds every sample reads its mean straight from the sum (all-good INP spread over 20-100 ms gives about 80, not the 150 of a blind 0-200 spread).
- Accuracy (`tests/vitals_figures_test.php`): within 10 % and the same rating on a flat 0.5-6.5 s spread and on a long-tailed one; within 800 ms on a narrow 8-12 s poor band. It is an estimate, and the API says so.
- Why no stored percentile: `visitors/high-volume-counter-rules.md` (no percentile over raw rows; WV1 keeps no raw rows).

## What does a figure carry?
- `pg_vitals_figures($row)`: `views` (beacons) and per figure `samples` (sum of its buckets, not views: INP has none without an interaction), `p75`, `mean`, `good_share`, `needs_share`, `poor_share` (0-1, 4 places); null without samples.

## Which readers are there?
- `pg_vitals_site($from, $to, $device)`, `pg_vitals_trend()` (per day, days without beacons left out), `pg_vitals_pages($from, $to, $order, $limit, $device)`. Days are `Y-m-d` checked by `pg_vitals_where_sql()`; an unknown device means all.
- Pages: every page of the window summed in SQL (`GROUP BY page_id`, name from `page`, '' when deleted), the p75 order taken in PHP by `pg_vitals_sort_pages()`: highest p75 of `lcp`/`inp`/`cls` first, pages without samples of it last, ties to more beacons then lower id; or `views`.
- Nothing before the step has run (`pg_vitals_ready()`): empty figures.

## What does the API give?
- `GET /reports/vitals` (`reports.vitals`, scope `visitors:read`, `includes/api/resources/reports.php`, object `VitalsReport`): `from`, `to`, `device`, `order`, `site`, `days`, `pages` (`limit` 1-100, default 10). Window by `api_visitors_window()`, at most 400 days. 503 `service_unavailable` before the database update.
- The MCP tool `reports_vitals` comes from the route (`api/api-mcp-server.md`).

## Who else reads them?
- The Speed section (`visitor_vitals.php`, `ui/visitors-speed-section.md`) and the dashboard `perf` card's page load tile.
