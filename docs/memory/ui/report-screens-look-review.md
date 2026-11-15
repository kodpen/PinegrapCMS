---
name: report-screens-look-review
description: Look review of the order, visitor, shipping and ERP VAT report screens (2026-11-15): the architect's answer, what RS8a-RS8c changed, the pictures, what was left
module: report_view
---

## What did the report screens' renewal cover?
- Owner (2026-11-11, #8673): the four report screens drawn as the dashboard, figures, a chart and the table, settings in a drawer, ready reports (`workflow/decisions-made-panel-5.md`). Built #8691 to #9738.
- `view_order_report.php`, `view_visitor_report.php`, `view_shipping_report.php`, `erp_vat_report.php`. The saved lists (`view_order_reports.php`, `view_visitor_reports.php`) kept their place; saved reports, filters, levels, `?id=` addresses, rights and exports stayed.
- Every figure equals what the screen's own query gave before: the view reads the table's buckets, never a second query (`report-view.md`). No new chart library and no schema.

## What did the look review decide?
- Architect (2026-11-15), channel 25 message 4223, on the look request 4150 (the four screens from a sandbox with demo data, light and dark, 1440 and 390 px): the screens stand in their shape, light and dark; nothing is redrawn. Only the faults listed were fixed, in three steps.
- RS8a #9700, the shared parts on a phone: `.pg-seg` rows scroll sideways, an odd last figure card takes the row, the title wraps, the sticky first table column, even axis ticks (`look-report-screens.md`).
- RS8b #9718, order and visitor: a ready report keeps its period and date changer (`commerce/order-report-ready-reports.md`), country names (`report-view-country-names.md`), the bars card on the whole row, "Order amount per visitor" (`report-view.md`).
- RS8c #9738, shipping and ERP VAT: day columns that fit 14 days at 1440 (`commerce/shipping-report.md`), one period shape (`report-period-text.md`), the VAT note, kinds chart and documents count (`erp/erp-vat-report.md`).

## Where are the pictures, and what did they show?
- Channel 25, replies 5145 to 5154 under message 4150 (RS9, #9772): each screen light at 1440 and 390 px, on a sandbox with demo data (orders over ten months, 3,200 visits, two weeks of shipments, 87 ERP invoices of 2026).
- Seen and left for a step of its own: the ERP VAT year chart and its By month table draw a month still to come (December) as zero, so the line drops to the axis; on a phone the order report's four period steppers (Year, Month, Week, Day) wrap into two rows, while the strip and the visitor presets scroll sideways.
- Demo data the sandbox lacks (recent orders, visits, shipments, ERP invoices) is not in `tools/screenshots/seed.php`; RS9 moved the starter's orders and visits into recent months and added invoices by hand.
