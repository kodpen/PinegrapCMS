---
name: visitors-speed-section
description: The Speed section (visitor_vitals.php until the Visitors overview takes it): period, device and order filters, p75 tiles with the good share, slowest pages, trend; the perf card's page load tile
module: visitor_insights
---

## Where is it?
- `pg_visitor_speed_section($window, $device, $order)` in `includes/fn/visitor_insights.php` draws it; `visitor_vitals.php` (Visitors flyout "Page Speed", panel search, gate `validate_visitors_access()`) prints it under its toolbar. When `visitors.php` (plans/visitors-overview.md V1) lands, its `speed` section calls the same function and the root screen goes (WV5, #PR).
- The flyout and the panel search show it only once `pg_vitals_ready()`; before that the screen says the upgrade must run.

## What does the toolbar hold?
- Period `p`: `7d`, `30d`, `90d` (`pg_visitor_insight_window()`, whole days ending today, default `30d`, the overview's key names), device (all, desktop, mobile, tablet), order (`lcp`, `inp`, `cls`, `views`); filters keep each other (`keep`). A link to AI Visibility keeps the period.

## What does it show?
- Tiles: the site p75 of LCP, INP, CLS (`pg_vitals_site()`), coloured by `pg_vitals_tone()` (Google's thresholds via `pg_vitals_bucket()`), the note "N% good of M measured"; measured views.
- Slowest pages: 25 from `pg_vitals_pages()`, names and links through `pg_visitor_label_content_rows()`, a deleted page as "Deleted page", the good LCP share.
- Trend: `pg_vitals_trend()` by day, newest first in the table; the LCP p75 as an inline SVG line (`pg_visitor_spark_svg()`, no chart library, `aria-hidden`) with the good limit dashed.
- Text form: `pg_vitals_display()` (LCP in s from 1 s, INP in ms, CLS score with two places).
- No beacons in the window: one line saying staff, Preview and the designer are not measured.

## What does the dashboard perf card add?
- `pg_dashboard_v2_perf_vitals_tile()` (`includes/dashboard/v2/cards/perf.php`): "Page load (p75)", the site LCP p75 of the last 7 days with its good share, after the Average tile; no tile without samples. The card's gate and other figures are unchanged.
- Tests: `tests/visitor_insights_test.php`.
