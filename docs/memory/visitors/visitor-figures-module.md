---
name: visitor-figures-module
description: includes/fn/visitor_figures.php: the visitor figures of a window (totals, breakdown, traffic series, content), which days come from the daily summary and which from the raw rows
module: visitor_figures
---

## What does the module answer?
- `pg_visitor_totals($from, $to)`: visits, page views, forms, orders (`pg_visitor_summary_figures()`), plus `summary_days`, `raw_days`, `summary_to`.
- `pg_visitor_breakdown($dimension, $from, $to, $limit)`: `$dimension` is a key of `pg_visitor_summary_dimensions()` (its SQL counts the raw rows, its name finds the summary rows); answers `totals`, `rows` (value `''` is a visit without one), `summary_days`, `raw_days`, `summary_to`; false for an unknown dimension.
- `pg_visitor_traffic($first, $last, $grain)`: hour/day/week (Monday)/month buckets of `visitor_stats_hourly`, empty ones included (pure `pg_visitor_series()`). `pg_visitor_content()`: `pg_visitor_top_content()` for a window.
- `$from`/`$to` are Y-m-d (whole days) or Unix moments (last one counted, so "yesterday up to this hour" works): `pg_visitor_window_bounds()`.
- Callers: `GET /reports/visitors` (#6356), the Visitors screen sections, later the dashboard cards.

## Which days come from the summary?
- `pg_visitor_summary_range()`: the contiguous run of days with a totals row (`dimension = ''`) ending at the most recent one (pure `pg_visitor_summary_run()`); an island behind a gap is not used.
- `pg_visitor_day_split()` (pure, through `pg_visitor_day_sources()`): a day is summary when it is whole in the window, inside the run and before today; everything else, and the cut part of a day, is raw. Neighbouring days of one source are one part, so a window needs at most one query per source.
- A day is read from one source only: a summarized day whose rows the cleanup has not removed yet is not counted twice.
- `pg_visitor_breakdown()` reads a summarized day that has visits but no row of the asked dimension from the raw rows (`pg_visitor_parts_move_days()`); the totals still come from the summary.

## How are the two lists added up?
- `pg_visitor_merge_rows()`: by value without regard to case (`mb_strtolower`), the first spelling kept, largest first then by value. The summary's rest row (`is_rest = 1`) is never listed; it counts in the totals only, which come from the totals rows.
- With both sources each list is read `max(500, limit × 4)` deep, so a value outside one source's top can still reach the merged top.

## What did the API keep?
- `api_reports_visitors()` answers as before (null for an empty value) plus `summary_days`; `by=channel` reads the summary dimension `channel` (`visitor-channel-rule.md`). `api_visitors_dimensions()` and `api_visitors_summary_dimensions()` stay in the API file; the second maps `by` to the module's dimension.
