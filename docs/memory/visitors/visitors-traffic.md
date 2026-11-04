---
name: visitors-traffic
description: The traffic section of visitors.php (includes/visitors/traffic.php): headline tiles, the line by the period's grain with the earlier period dashed, the busiest content in the tooltip, the empty states
---

## What does the section show?
- Four tiles: visits, page views, pages a visit (one decimal, `–` without a visit), each with `pg_visitors_change()` against the earlier period when comparing is on; people on the site now (`visitors.stop_timestamp` in the last 20 minutes, the dashboard's rule), with no change.
- Under them the line: visits and page views by the period's grain from `pg_visitor_traffic()` (hourly rollup), and with comparing on the earlier period's visits dashed, bucket against bucket (a shorter earlier period padded with null, a longer one cut).

## Where do the figures come from?
- Tiles: `pg_visitor_totals(from_ts, to_ts - 1)` and the same for the previous window, so today is set against yesterday up to the same time.
- A one-day period ending now draws no hours after the present one (null, not 0: `pg_visitors_traffic_cut()`); the dashed yesterday is drawn whole.

## What is in the tooltip?
- `pg_visitors_chart()` takes `notes`, one text per label, shown as the tooltip footer by `pg_visitors.src.js`. Hour grain: `pg_visitor_top_content_by_hour()`; day, week and month: `pg_visitor_top_content()` per bucket, the bucket cut to the period (`pg_visitors_traffic_ranges()`). Past 62 buckets no note is read (one rollup query a bucket).

## What does it show with no data?
- Tiles at 0 and, instead of a line, "Nothing in this period."; while `pg_visitor_backfill_state()` is not done the empty text says the figures are still being built, and with data a note at the heading says the history is still being added to the line.
