---
name: report-screens
description: Plan (owner 2026-11-11, #8673): the order, visitor, shipping and ERP VAT report screens drawn as the dashboard: figures, a chart, the table, settings in a drawer, ready reports; steps RS1-RS8
---

## What is it for?
- Owner (2026-11-11), on the dashboard's Sales map card: "bu widget tümünü gör diyince https://dev.pinegrap.com/pinegrap/view_order_report.php sayfasını açıyor ancak burası uygun şekilde rapor sunmuyor. bu rapor sayfalarını da moderize edelim".
- Today a report screen is a form (summarize-by levels, filters, period) over a table; none draws a chart (`commerce/order-report-screen.md`, `ui/report-screen-controls.md`, `visitors/visitor-report-filters-and-form.md`, `commerce/shipping-report.md`). The card's "See all" opens a new report summarized by year, month and day, not the sales by region the card shows.
- His three answers: #8673, `workflow/decisions-made-panel-5.md`.

## Which screens, and what stays?
- All four: `view_order_report.php`, `view_visitor_report.php`, `view_shipping_report.php`, `erp_vat_report.php`. The saved lists (`view_order_reports.php`, `view_visitor_reports.php`) keep their place.
- Saved reports, filters, summarize-by levels, the addresses (`?id=`), rights and exports stay; nothing is removed.
- Every figure equals today's: the screens keep their own queries (`ui/order-report-totals.md`, `visitors/visitor-report-sql-pitfalls.md`, `visitor-report-site-time.md`).

## What does a report screen look like?
- As the dashboard (`ui/dashboard-v2-drawn-look.md`): the period picker and the summary figures on top, a chart under them, the table at the bottom.
- The chart follows the first summarize-by level: a line over time (year, month, day), bars for a named level (status, product, payment method, source, page), the map for a region or country level; a level with no fitting chart shows figures and table only.
- The report's settings (summarize by, filters, save) open in a drawer from the right, Bootstrap's own `<dialog class="drawer drawer-end">`; the form's field names and the filter builder (`create_filter()`, `pg_filter_control_name()`) stay.
- No new chart library: the dashboard's own drawing (`pgDash.tiles()`, `line()`, `bars()`, `rows()`, `chartTips()`, `markBars()` of `assets/js/pg_dash.js`, `pgDash.mapShapes()`).

## What is a ready report?
- A report defined in code (a registry: key, title, summarize-by levels, filters, period) and opened by an address parameter as a new, unsaved report; no table, no column. Saving it makes it the person's own saved report, as today.
- A strip on top of the screen lists the screen's ready reports: for orders by region, product, payment method, source; for visitors by source, page, country; each only where the screen's levels can give it.
- Each dashboard card's "See all" opens its own ready report with the card's period: the Sales map opens sales by region for the last 30 days.

## What does every step keep?
- No schema; a need for a column comes back as an `architect` question.
- One shared report view: a module under `includes/fn/` turns a report's result (levels, rows, totals) into figures, chart series and the table model, as pure functions with tests; each screen hands it its result.
- Every text through `lang()`, keys by `php tools/tr_add.php`; bundles rebuilt (`php tools/build_js.php`, `build_css.php`). The table's rules stay (`ui/report-table-rows.md`).
- Looked at in the sandbox (`ui/panel-screenshots.md`): wide and phone, light and dark, before and after, linked in the PR. The look on dev is the owner's, after landing.

## Who holds it, where does it start?
- Coordinators hand the steps out; claim as `[plan:report-screens] <step> — <topic>`. The steps: `report-screens-steps.md`. RS1 starts.
