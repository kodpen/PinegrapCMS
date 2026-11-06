---
name: seo-impact-window-days
description: The SEO screens' recent-views column starts its window with pg_seo_impact_since(): calendar days, not 86400-second blocks, so a clock change does not move the first day.
module: seo
---

## What does the window read?
- `pg_seo_traffic_join()` (`seo.php`) sums `visitor_content_hourly.views` from `stat_date >= pg_seo_impact_since(pg_seo_impact_window_days())` (30 days by default, `SEO_IMPACT_WINDOW_DAYS`).

## Why calendar days?
- It was `date('Y-m-d', time() - N * 86400)`. With a daylight saving change inside the window the first day moved by one: read just before midnight after the autumn change it started a day late, just after midnight after the spring change a day early. `pg_seo_impact_since()` (`includes/fn/seo.php`, pure) steps with `strtotime('-N days')` in the site time zone. Test: `tests/seo_impact_window_test.php`.

## What was checked and left?
- `pg_seo_speed_map()`'s window (`seo.php`, `time() - N * 86400` over `perf_stats` timestamps) is a rolling span of hours, not a day list: left.
- The ERP accountant link's expiry date in the mail (`includes/erp/accountant.php`) prints the day of the stored expiry moment (`time() + N * 86400`): the same moment the link stops working, so it is right as it is.
