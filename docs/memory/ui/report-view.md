---
name: report-view
description: The report screens' shared view (report_view.php, pg_report_view.js): figures and a chart over the table drawn with pg_dash.js, the chart per level; order (#8709) and visitor (#8764) reports
module: report_view
---

## What does a report screen draw now?
- Under its toolbar and date changer: a card of summary figures, a card with the chart, then the report's own table. The settings (name, summarize by, filters, Save & Run) sit in a drawer from the right. On the order report since #8709 and the visitor report since #8764; the shipping and ERP VAT reports follow (`plans/report-screens-steps.md`).

## Where do the figures come from?
- `pg_report_view_figures($grand, $charge_columns, $symbol)`: Orders, then the Grand Total row's money columns in the table's order (Subtotal, Discount, Tax, Shipping, the shown charge columns, Total). Read from the same buckets the table prints, so a figure always equals its Grand Total cell; the queries are untouched. Money is `pg_money_text()` text (the symbol decoded, tiles are written with textContent).
- The visitor report: `pg_report_view_visitor_figures($grand, $ecommerce, $symbol)` from the Grand Total row's own sums (`$grand_count`, `$grand_page_views`, ...): Visitors, Page Views, Custom Form Submitted %, and only with `ECOMMERCE` (`$output_order_cells`) Order Completed %, Order Total and Average Order Amount, each the text of its cell (a share over visitors, 0% with none; money rounded, never cut).

## Which chart does a level get?
- `pg_report_view_chart_kind($level)` on the first summarize-by level: `line` for year, month, day; `map` for billing country (world map, `pg_sales_map_country_code()`, two spellings of a country are one place); `none` without a level or for a level of one person's details (names, addresses, phone, e-mail, card, IP, PO, reference and tracking codes); `bars` for every other named level (billing state included: a state has no country beside it to pick a map).
- The line runs over the finest date level the report starts with (`pg_report_view_time_groups()`): year, month, day draws days named "2026 Ekim 11"; year alone draws years. A single year was a line of one point.
- The order report measures money (`pg_report_view_chart()`, "Total by …"); the visitor report counts visitors (`pg_report_view_visitor_chart()`, "Visitors by …", its country level the map). Both go through `pg_report_view_chart_by()` with a measure: what is ranked, drawn, written in a row and said on hover.
- Bars and the map's rows list the ten largest totals (`PG_REPORT_VIEW_ROWS`); map shading is four steps by rank (`pg_report_view_steps()`), a lone place the darkest.
- Every point, bar and country says its figure on hover and keyboard focus (`pgDash.chartTips`, `dashboard-v2-chart-tips.md`).

## How is it drawn?
- The server prints the data as `<script type="application/json" id="pg_report_view_data">` (JSON_HEX_TAG) inside `<section class="pg-dash pg-report-view">`; `assets/js/pg_report_view.js` draws it with `pgDash.tiles`, `line`, `rows`, `chartTips`, `mapShapes`.
- Bundles: the screen loads `pg_dash.min.js` (the dashboard's whole bundle; its grid, edit and panel starts return without `#pg_dash_grid`) and then its own `pg_report_view.min.js`. A source sits in one bundle only (`core/js-bundles.md`); splitting the drawing core out of `pg_dash.min.js` was left for when a screen needs it without the cards.
- Styles: the dashboard's sheet (`pg_dashboard_v2_style_url()`) through the shell's `head`, plus a small `@layer custom` block in the screen for the view's grid, the line's height and its end labels.

## Where are the report's settings?
- In a drawer from the right behind Report settings: `report-settings-drawer.md`.

Tests: `tests/report_view_test.php`, `tests/order_report_screen_layout_test.php`, `tests/visitor_report_screen_layout_test.php`.
