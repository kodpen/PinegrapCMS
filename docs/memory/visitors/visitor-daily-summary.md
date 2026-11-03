---
name: visitor-daily-summary
description: The daily visitor summary written for every day through yesterday: what a day holds, the day order of a run, where the raw rows take over (pg_visitor_rows_from), who reads it
module: visitor_retention
---

## What does a summarised day hold?
- With `config.visitor_summary` on (default), every day through yesterday is summarised into `visitor_stats_daily` with the report's grouping keys, whatever `visitor_retention_days` is (0 included); top 100 values per dimension plus one rest row (`is_rest`); the total row (`dimension = ''`) is written last and marks the day done, also as an all-zero row for a day without visits. Only summarised days are deleted (#6352).

## In which order does a run write the days?
- `pg_visitor_summary_days_due()` (pure, `tests/visitor_summary_days_test.php`): yesterday and the day before, written again on the first run of a day and then at most hourly (`recent_day`/`recent_at` in the marker), so an order completed after midnight on a visit begun before it is counted; then the unsummarised days before the retention boundary, oldest first, so the removal is not held up; then every other unsummarised day backwards from yesterday to the oldest visit. Days before the oldest raw row are never written.
- The summary takes at most 60 % of the cleanup run's budget; the run is due hourly even with retention off while the summary is on, and every tick while days are left.

## Where do the raw rows take over?
- `pg_visitor_rows_from()`: the first moment the raw rows are complete: the boundary, or an earlier midnight while the summary has not reached the days before it (`pg_visitor_summary_covered_until()`, also the removal's limit); 0 with retention off. The saved-report screen reads the summary only before it; `pg_visitor_summary_raw_from()` returns the same, so the API and the AI referrals read the summary only where rows are gone until the figures module reads it wherever it covers.

## Who reads it?
- Reports and `GET /reports/visitors` read the summary before that moment and raw rows after it (year/month/day + at most one grouping, no filters), answer `summary_to`, and say which days a report cannot cover.
