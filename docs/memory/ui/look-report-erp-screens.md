---
name: look-report-erp-screens
description: ERP Dashboard, Cash Flow, Profit and Aging on look A's report recipes (#9696): server-drawn figure cards, the tools rows, the profit chart on the report view, what stayed
module: report_view
---

## Which ERP screens use the report recipes, and how?
- ERP Dashboard, Cash Flow (both views), Profit and Aging (#9696); the VAT report was done before and belongs to the report-screens plan.
- Every table is a `section.pg-card.pg-report-frame` around `table.pg-report-table`; opening rows `pg-report-l1`, totals `pg-report-total`; notes under a table are `.pg-report-frame-note`.
- A dashboard frame's head carries its "→" link at the right in `pg-text-2`. Architect (2026-11-15): the figure cards' tones and icons and that link stand.

## How are figures drawn without the report view's script?
- `pg_report_figures_html()` in `includes/fn/report_view.php` draws the same cards as `pg_report_view.js` (up to five a row, more in two even rows, unit smaller). A figure may add `url` (its name is a stretched link) and `class` (fg-danger below zero, fg-success for money in).
- Tones by meaning: money in teal, money out rose, a warning amber, the rest blue, violet or gray.

## What are the tools rows?
- Profit: the period form copied from the VAT report, plus the "Count orders without an invoice" `.pg-switch`.
- Cash Flow: "What happened" / "What is ahead" as a `.pg-seg` at the row's start; Bucket, Period, Start, End, the till, "Show" quiet, "Download CSV" quiet at the right. Bucket and Period send their own hidden forms through the `form` attribute, keeping the address values as the old filter selects did; the main form sends `group` only when it was chosen, so the bucket is still picked from the period's length.

## How is the profit chart drawn?
- `pg_report_view_profit_chart()`: net sales the line, costs and expenses (cost of goods, purchase costs, expenses) dashed, net profit in the tip, titled "Net sales and costs by month"; null for one month. Chart.js left the screen.

## What stayed?
- The dashboard's "Add Local Order" button (the shared buttons step), badges and alerts.
- Latest movements on the dashboard keeps the account on one line with its till under it; the table scrolls inside its frame on a phone.
