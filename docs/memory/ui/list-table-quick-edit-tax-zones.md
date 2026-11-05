---
name: list-table-quick-edit-tax-zones
description: All Tax Zones list quick edit (tax_zone_list_edit.php): the name and the rate, the tax zone screen's checks and save, the countries and states kept
module: tax_zone_list_edit
---

## Which cells change?
- Every list value `edit_tax_zone.php` changes (`pg_tax_zone_quick_edit_columns()`): Name (required, 50 characters) and Tax Rate. Text: Last Modified. The countries and states (several choices) are not list columns; they stay on the screen. `tests/tax_zone_list_edit_test.php` fails for a new column that is neither.
- Before, the list had no quick edit.

## How does the rate open and how is it read?
- It opens trimmed to its decimals (`format_tax_rate()`, 18.500 -> 18.5) in the panel's decimal separator (`pg_tax_zone_quick_value()`). The write reads it with `parse_tax_rate()` like the screen: a comma is a decimal point, above 100 becomes 100, an unreadable value is refused with the screen's message.

## Who may change them, and how is a change written?
- The screen's rights (`validate_ecommerce_access()`: role below 3 or manage commerce); `view_tax_zones.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON.
- `pg_tax_zone_quick_change()` (pure) applies the form's checks (`pg_tax_zone_form_name_error()`, `parse_tax_rate()`) to the stored zone with the one change; `pg_tax_zone_quick_update()` reads the zone's country and state links and writes through `pg_store_tax_zone_save()` (stamp, `tax_zone.updated`), so the links are written back unchanged, then logs like the screen.
- The answer redraws the name, the rate (with its sort value) and Last Modified (`pg_tax_zone_quick_saved()`).

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
