---
name: visitors-speed-section
description: Page Speed section of the Visitors overview (speed.php, #6475): period, device and order filters, p75 tiles, slowest pages, trend, old-screen redirect, perf card tile
module: visitor_insights
---

## Where is it?
- `pg_visitor_speed_section($window, $device, $order)` in `includes/fn/visitor_insights.php` draws it; `includes/visitors/speed.php` (`pg_visitors_section_speed()`) puts it in `visitors.php#speed` with the overview's period as the window (V17, #6475). The section draws nothing until `pg_vitals_ready()`; the flyout anchor follows the same check.
- `visitor_vitals.php` only answers a 301 to `visitors.php?…#speed`: its `p` kept (30 days when absent, its old default), `device` and `order` renamed (`pg_visitor_insight_overview_query()`). Panel search "Page Speed" opens the anchor.

## Which filters?
- At the heading's end: device (all, desktop, mobile, tablet) and order (`lcp`, `inp`, `cls`, `views`) as `speed_device` and `speed_order`, so another section's `device` is never read; `pg_visitors_speed_choice()` takes only an offered value. Each filter is a `pg_filter_select()` form to `visitors.php#speed` keeping `p`, `from`, `to`, `compare` and the other filter.
- The period is the overview's (today to a custom range); `pg_visitor_insight_window()` stays for the old links' `p`.

## What does it show?
- Tiles: the site p75 of LCP, INP, CLS (`pg_vitals_site()`), coloured by `pg_vitals_tone()` (Google's thresholds via `pg_vitals_bucket()`), the note "N% good of M measured"; measured views.
- Trend (`pg_visitor_speed_trend()`): the days of `pg_vitals_trend()` (a day without beacons left out) as `pg_visitors_chart()`: the LCP p75 in s over Google's good limit dashed, each day's INP, CLS and measured views in the tooltip; nothing under two days. It was an inline SVG line over a table of every day.
- Slowest pages: 25 from `pg_vitals_pages()`, 5 shown at first, names and links through `pg_visitor_label_content_rows()`, a deleted page as "Deleted page", figures in their tone (`pg_visitor_insight_figure_cell()`), the good LCP share.
- Text form: `pg_vitals_display()` (LCP in s from 1 s, INP in ms, CLS score with two places).
- No beacons in the window: one line saying staff, Preview and the designer are not measured.

## What does the dashboard perf card add?
- `pg_dashboard_v2_perf_vitals_tile()` (`includes/dashboard/v2/cards/perf.php`): "Page load (p75)", the site LCP p75 of the last 7 days with its good share, after the Average tile; no tile without samples. The card's gate and other figures are unchanged.
- Tests: `tests/visitor_insights_test.php`.
