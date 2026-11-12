---
name: visitor-report-ready-reports
description: The visitor report's ready reports (over time, by source, by landing page, by country), the &p= period, the strip, and the Visitor Summaries, Visitor map and On the site now cards' See all (#8800)
module: ready_reports
---

## Which ready reports does the visitor report have?
- In `pg_ready_reports()` with `screen` `visitor_report`, each 30 days, no filter:
- `visitors_over_time` "Visitors over time": Year, Month, Day, each by alphabet (the time order), drawn as a line; `visitors_by_source` "Visitors by source": Host Name (`referring_host_name`) by number of visitors; `visitors_by_page` "Visitors by landing page": Landing Page (`landing_page_name`); `visitors_by_country` "Visitors by country": Country, drawn as the map (`ui/report-view.md`).
- Source is the referring host, not the UTM source the order report's "Sales by source" uses: most visits carry no UTM tag, every referred visit a host. The report has no level of read pages, so "by page" is the landing page.

## How does `view_visitor_report.php` open one?
- `?ready=<key>`: `pg_ready_report_requested($_GET['ready'] ?? null, 'visitor_report')`; an order key, an unknown key or an array opens the plain new report (Year, Month, Day); an `id` wins.
- Before the form came back from a save it sets the levels (one not named is -None-), the order by and the Name field (the title). The tab and breadcrumb read the title instead of "[new visitor report]". The settings drawer stays shut.
- The strip (`pg_ready_report_strip('visitor_report', …)`) sits under the toolbar as on the order report (`commerce/order-report-ready-strip.md`).

## How is the period given?
- Not by a date filter: by the date changer's preset in the address, `&p=today|7d|30d|90d` (`pg_ready_report_visitor_preset($days)`: 1, 7, 30, 90 days; any other count gives none and the last range chosen stays). So the date changer stays and the period can be changed; its preset links carry `ready` while one is open.
- `pg_ready_report_url($key, $days)` writes `p` for a visitor entry (its own period without days) and `days` for an order entry. `&days=` is not read on this screen.
- Saving keeps no period, as for any visitor report without a date filter: the range stays per report in the session.

## Where do the visitor cards lead?
- Visitor Summaries: `visitors_over_time` with the card's 1, 7 or 30 days; before, the saved reports list.
- Visitor map: `visitors_by_country` with its days; before, the overview `visitors.php?p=…`.
- On the site now: `visitors_by_page` for today (its rows are the pages people came in on); before, the saved reports list.
- The cards and the screen share one gate (role below 3 or `manage_visitors`), so a link never leads to Access denied.

## What does Save do?
- Inserts a new saved report: the form posts to `view_visitor_report.php` with no `id` or `ready`.

Tests: `tests/visitor_report_ready_test.php`.
