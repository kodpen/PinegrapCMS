---
name: ship-date-adjustment-shared-form
description: The ship date adjustment create and edit screens share includes/forms/ship_date_adjustment_form.php: what it holds, what stays in the screens
---

## Where is the ship date adjustment form?
- `includes/forms/ship_date_adjustment_form.php` (gate `PG_FUNCTIONS_DIR`). `add_ship_date_adjustment.php` and `edit_ship_date_adjustment.php` stay at the root as thin wrappers and `require_once` it.
- `pg_ship_date_adjustment_form_card($liveform, $status_rows)` prints the two columns and loads the shipping methods itself; `pg_ship_date_adjustment_form_layout($cards, $buttons)` wraps them in the row with the sticky save bar; `…_status_row()` draws one Summary row.

## How is the screen laid out?
- The product_builder pattern (`ui/edit-screen-wide-layout.md`, B11): the form column holds Options (zip code prefix, shipping method, days + earlier/later); the sticky status column holds a Summary of the prefix, the method and the adjustment ("3 days later"), following the fields as they change. Edit adds Last Modified (`last_modified_timestamp` and its user, joined in the record load).
- `…_shipping_method_options($methods, $selected)` and `…_adjustment_type_options($value)` (later when empty) build the two pick lists.
- The edit screen's input group had no `w-auto`; the shared card uses the create screen's `w-auto` for both.

## What does a save share?
- `pg_ship_date_adjustment_form_check($liveform, $id)` (`null` on create, the posted id on edit): the four required fields, a three character prefix, a shipping method that exists in `shipping_methods`, a prefix + shipping method no other adjustment uses (through `db_q()`), a direction of exactly `earlier` or `later` (`…_type_valid()`; any other value was saved as later), a whole number of days from 1 to 999 (`…_days_valid()`, `ctype_digit`: `is_numeric()` let 1.5 become 2 days and 1e2 100 days; #5562).
- `…_adjustment($days, $type)` is the stored signed value (earlier is negative); `…_fields($adjustment)` turns a stored value back into `adjustment_days` / `adjustment_type` for the edit screen.

## What stays in the screens?
- Create: the INSERT, its log line and notice.
- Edit: the not-found guard, the record load, delete, the UPDATE.
- Tests: `tests/ship_date_adjustment_form_test.php`.
