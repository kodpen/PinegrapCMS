---
name: list-table-quick-edit-ship-date-adjustments
description: All Ship Date Adjustments list quick edit (ship_date_adjustment_list_edit.php): prefix, shipping method and signed days as fields, the adjustment screen's checks and save
module: ship_date_adjustment_list_edit
---

## Which cells change?
- Every list value `edit_ship_date_adjustment.php` changes (`pg_ship_date_adjustment_quick_edit_columns()`): Zip Code Prefix (required, exactly 3 characters), Shipping Method (a select of the screen's pick list, id => "name (code)"; the column is `shipping_method`, the field `shipping_method_id`) and Adjustment. Text: Created and Last Modified. `tests/ship_date_adjustment_list_edit_test.php` fails for a new column that is neither.
- Adjustment is one integer box holding the stored signed days (-999 to 999, negative is earlier, 0 refused): the screen's days box and earlier/later select in one field, since the driver gives a cell one input. The cell still reads "3 days earlier".
- A row whose stored method is not among the choices (deleted) keeps its method cell as text.

## How is a change checked and written?
- The screen's rules: three characters, `pg_ship_date_adjustment_form_days_valid()` on the days, `pg_ship_date_adjustment_form_method_exists()` and `pg_ship_date_adjustment_form_taken()` (one adjustment per prefix and method pair) with the screen's messages; then `pg_store_ship_date_adjustment_save()` and the log line "ship date adjustment (…) was modified".
- Rights: the screen's (`validate_ecommerce_access()`); `view_ship_date_adjustments.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON. The answer is the row read again (`pg_ship_date_adjustment_list_sql($id)`, `pg_ship_date_adjustment_list_cells()`).

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit-zones.md
