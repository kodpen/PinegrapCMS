---
name: report-dynamic-date-filter
description: Order and Visitor reports: a 'Day(s) Ago' filter on a date field compares a bare Y-m-d date through pg_report_dynamic_value() (#3725)
module: forms
---

## How do the order and visitor reports turn a dynamic filter value into an operand?
- Through `pg_report_dynamic_value($dynamic_value, $attribute, $field_type)` in `includes/fn/forms.php`, called with the `$field_type` the filter switch set. For `'days ago'` on a `'date'` field it returns `date('Y-m-d', strtotime('-N days'))`; everything else is `get_dynamic_value()`'s value.

## Why not get_dynamic_value() directly?
- Its `'days ago'` is `Y-m-d H:i:s` (now minus N × 86400). The report's field side is `FROM_UNIXTIME(..., '%Y-%m-%d')` and `prepare_sql_operation()` compares quoted strings, so a bare date sorted before the same day with a time: "Order Date is equal to 1 Day(s) Ago" was always empty, "is not equal to" matched everything, ">= 7 Day(s) Ago" dropped the whole boundary day (#3725).

## Are site callers of get_dynamic_value() changed?
- No. Form list views, pages, widgets, the search index and sitemap info are site output and keep `get_dynamic_value()` as it is.

Tests: `tests/report_dynamic_date_test.php`.
