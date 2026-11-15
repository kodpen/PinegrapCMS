---
name: report-period-text
description: The period beside the calendar icon on the order, visitor, shipping and ERP VAT report screens: one shape, pg_report_view_range(), the panel's date text and an en dash (RS8c)
module: report_view
---

## How is a report's period written?
- One shape on the four report screens (`view_order_report.php`, `view_visitor_report.php`, `view_shipping_report.php`, `erp_vat_report.php`): `pg_report_view_range($start, $stop)` joins the panel's date text of the first and last day (`get_absolute_time()`, type date: "Paz 15 Kas 2026") with an en dash; a one-day period names its day once.
- Before RS8c there were three shapes: a hyphen between the panel's dates (order, visitor), the list heading's long months (shipping) and the site's numeric dates (ERP VAT).
- Table cells keep the site's date format (`prepare_form_data_for_output()`); only the text beside the calendar icon follows this rule.

Tests: `tests/look_report_screens_test.php` (`test_report_screens_one_period_shape`).
