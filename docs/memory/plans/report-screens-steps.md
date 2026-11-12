---
name: report-screens-steps
description: Steps of plans/report-screens.md: RS1 the Sales map card's See all, RS2 the shared view on the order report, RS3 its ready reports, RS4-RS5 the visitor report, RS6 shipping, RS7 ERP VAT, RS8 close-out
---

## Which steps are there?
- S under 400 product lines, M about 1,000; no schema; each step its tests and its memory topic. They are built in the order written: each waits for the one before it.

- RS1 (S) The Sales map card's "See all" opens what the card shows. Done: #8691.
  - A registry of ready reports in a new `includes/fn/` module (key, title, levels, filters, period), pure, with one entry: sales by region, last 30 days, counted as the card counts (`includes/dashboard/v2/cards/salesmap.php`: the same orders, the same region field).
  - `view_order_report.php` opens a ready report by an address parameter as a new, unsaved report; an unknown key opens the plain new report. The card's link (`'url' => 'view_order_report.php'`) names the ready report. A level for the card's region field, if the report has none, is added in code.
  - Tests: the registry's shape, the parameter's reading, the report's levels and period for the entry. Topic `commerce/order-report-ready-reports.md`; `ui/dashboard-v2-shop-cards.md` corrected.
- RS2 (M) The shared view, on the order report. Done: #8709.
  - `includes/fn/report_view.php`: from a report's result (levels, rows, totals) the summary figures, the chart's kind and series (a line over time, bars for a named level, the map for a region level, none otherwise) and the table model; pure, tested.
  - `view_order_report.php` draws figures, chart and table with the dashboard's drawing (`assets/js/pg_dash.js`, served here as a bundle, `core/js-bundles.md`). The settings form moves into a drawer from the right (`<dialog class="drawer drawer-end">`) behind a "Report settings" button, open at once on a plain new report; field names, the filter builder and Save stay.
  - Every figure equals today's: the queries are not touched. Kept: `ui/report-table-rows.md`, `order-report-totals.md`, `report-dynamic-date-filter.md`, `list-period-navigator-and-clock.md`.
  - Topic `ui/report-view.md`; `commerce/order-report-screen.md` and `ui/report-screen-controls.md` corrected.
- RS3 (S) Done: #8724. The order report's ready reports: the strip on top (by region, product, payment method, source: those its levels can give); Save turns one into the person's saved report; the shop cards' links (`pg_dashboard_v2_shop_url()`) open their ready report with the card's period.
- RS4 (M) Done: #8764. The visitor report (`view_visitor_report.php`) on the shared view; its query traps and site time stay (`visitors/visitor-report-sql-pitfalls.md`, `visitor-report-site-time.md`, `high-volume-counter-rules.md`).
- RS5 (S) Done: #8800. The visitor report's ready reports (by source, page, country: those its levels can give) and the visitor cards' links.
- RS6 (S) Done: #8811. The shipping report (`view_shipping_report.php`) on the shared view; its period bound (`PG_SHIPPING_REPORT_MAX_DAYS`) and its lists stay (`commerce/shipping-report.md`).
- RS7 (S) The ERP VAT report (`erp_vat_report.php`) on the shared view; its figures, rates and export stay (`erp/README.md` first).
- RS8 (S) The owner's look and the close-out. The architect asks the owner to look at the four screens on dev; what he asks for becomes steps here. Then the lasting rules stand in the topics above and this plan's two files are deleted.

## Why this order?
- RS1 answers the owner's finding at once and needs no new look. The order report carries the shared view first, as the dashboard's shop cards point at it; the visitor report shares its filter builder; the two small screens follow the settled view.
