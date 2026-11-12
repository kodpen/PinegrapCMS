---
name: dashboard-v2-engagement-card
description: New dashboard's Online Engagement card (engagement): what it shows when nobody is on the site (the last visits, Last visits list), the time used, the bounded read, when it sends empty
---

## What does the card show when nobody is on the site?
- Owner (2026-11-11, `workflow/decisions-made-panel-5.md`): "bu widget ta en azından en son çevrim içi olma zamanlarını gösterebilirdi."
- "Nobody is on the site right now." stays as the heading (`p.pg-dash-card-empty.pg-dash-live-nobody`); under it "Last visits": the last five visits, each with how long ago, the clock in the site's time and the landing page (`ul.pg-dash-live-pages.pg-dash-live-last`, a muted dot, the page at the end). Then "See all".
- Nobody means no visit with `stop_timestamp` in the last 20 minutes (`$active === 0`, widget 4's span). If visits started in the last 30 minutes all the same, their minute bars and the 30-minute line follow the list.
- With someone on the site the card is as before: the count, the minute bars, "Pages they came in on".
- Before (until #8697) the card showed the one line and nothing else.

## Which time is "last online"?
- `stop_timestamp`, the visit's last request; a visit with none counts from `start_timestamp`, a visit with neither is left out (`pg_dashboard_v2_last_visit_rows()`, pure, `includes/dashboard/v2/cards/engagement.php`).
- How long ago: `get_relative_time()` with `'format' => 'plain_text'`. The clock: `get_absolute_time()` with `'type' => 'time'`, `'timezone_type' => 'site'` and `'format' => 'plain_text'`; without the last one it returns a `<time>` element, which the renderer would print as text.

## How is the read bounded?
- `pg_dashboard_v2_last_visits()`: `SELECT MAX(id) FROM visitors`, then the rows with `id` in the 200 ids below it (`PG_DASHBOARD_V2_LAST_VISITS_WINDOW`), no `ORDER BY`, no `COUNT`; the five rows (`PG_DASHBOARD_V2_LAST_VISITS`) are picked and sorted in PHP (`visitors/high-volume-counter-rules.md`).
- Visits are written in start order, so the 200 newest ids hold the last-seen visits but for one older visit kept open longer than 200 later ones; the card accepts that.

## When does the card send empty?
- Only when there is nothing to draw: nobody on, no visit in 30 minutes and no last visit (a site without visits). `pg_dash.js` then shows the line alone and calls no renderer. Otherwise `empty` is `''` and the renderer draws `nobody` as its heading.

- "See all" opens the visitor report's ready report by landing page for today (`view_visitor_report.php?ready=visitors_by_page&p=today`, `visitors/visitor-report-ready-reports.md`); before #8800 the saved reports list.

Pinned in `tests/dashboard_v2_engagement_test.php`. Related: docs/memory/ui/dashboard-v2-cards-look.md
