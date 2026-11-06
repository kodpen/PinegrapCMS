---
name: list-table-quick-edit-zones
description: All Shipping Zones list quick edit (zone_list_edit.php): name and the four rates as fields, the zone screen's checks and save, rates not edited written as stored
module: zone_list_edit
---

## Which cells change?
- Every list value `edit_zone.php` changes (`pg_zone_quick_edit_columns()`): Name (required, 50 characters, the screen's input limit) and Base, Primary Weight, Secondary Weight and Item Rate (money; blank is 0). Text: Last Modified. The countries and states are several choices and not on the list. `tests/zone_list_edit_test.php` fails for a new column that is neither.

## How is a change checked and written?
- The screen's checks: `pg_zone_form_name_error()` and `pg_zone_form_rate_errors()` (an amount of 0 or more, at most `PG_ZONE_FORM_RATE_MAX`); a stored blank name refuses every change until the zone is named.
- `pg_zone_quick_update()` writes through the screen's `pg_store_zone_save()` with the zone's own country and state links read back (so `zone.updated` is announced) and logs "shipping zone (…) was modified". Only the edited rate is read from text (`pg_zone_form_rate()`); the others go back in stored minor units (`pg_zone_quick_write()`), never through an amount format.
- Rights: the screen's (`validate_ecommerce_access()`); `view_zones.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON. The answer is the row read again (`pg_zone_list_sql($id)`, `pg_zone_list_cells()`).

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-settings-lists.md
