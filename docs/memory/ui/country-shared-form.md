---
name: country-shared-form
description: add_country.php and edit_country.php draw the country options card and read its posted values through includes/forms/country_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/country_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_country_form_card($values, $errors, $status_rows)` (the two columns; `$values` null on create, which prints no values and no switch on), `pg_country_form_status_row()`, `pg_country_form_edit_status_rows()`, `pg_country_form_layout($cards, $buttons)` (the row with the sticky save bar) and `pg_country_form_values($_POST)` (the five fields as text, in INSERT/UPDATE order). The edit screen passes its stored row through `pg_country_form_values()` too.

## How is the screen laid out?
- The product_builder pattern (`ui/edit-screen-wide-layout.md`, B11, PR #6677): the form column holds Options (name, code) and Delivery (Extra Delivery Days, Zip Code Required); the sticky status column holds Checkout (the Selected by Default switch) and a Summary of the code, the zip rule and the extra days, following the fields as they change. Edit adds States, Shipping Zones and Tax Zones (the counts of `pg_zone_form_member_delete_counts()`, read once as `$usage` and reused by Delete's confirmation) and Last Modified (`countries.timestamp` and `user`), in one `db_q()` beside the record load.
- A screen keeps its gates (`validate_user()`, `validate_ecommerce_access()`), the edit screen's not-found guard and record load, the form tag, the hidden `id`, buttons (edit adds Delete), the INSERT / UPDATE, the reset of `default_selected` on every other country, the log line and the redirect.

## How is a taken code refused?
- `pg_country_form_code_error($code, $stored_code, $except_id)` answers 'The code that you entered is already in use by another country…' when another country has the code (`pg_country_form_code_taken()`, db_q, edit leaves its own id out). Only a non-empty code that is new or changed is looked up, so an edit keeping an old clash still saves; delete is never held back.
- Name and code are trimmed in `pg_country_form_values()`; the check compares the trimmed stored code. Why: PAD SPACE collation ignores trailing, not leading, spaces, so ' US' passed beside 'US' and checkout never found it; checkout and the destination check take the first row with the code.
- The form is drawn again with the code field marked (`is-invalid`, `code-error`); nothing is written. Tests: `tests/commerce_code_taken_test.php`.

## What does delete take?
- Only the edit screen deletes: for each state of the country its `zones_states_xref`, `tax_zones_states_xref` and `verified_shipping_addresses` rows, then the `states`, `zones_countries_xref`, `tax_zones_countries_xref` and `countries` rows. Every DELETE and the `default_selected` reset carry `or output_error('Query failed.')` (`tests/checked_writes_country_shipping_currency_test.php`).

## Are the name and the code required?
- Yes (#4413, PR #4456): `pg_country_form_required_errors($values)` answers 'Enter a name.' / 'Enter a code.' for a blank one; both boxes carry `required` and the card prints the message under the field. Edit checks it too; delete is never held back. Why: a nameless country was an empty row in every list; a codeless one cannot be matched by checkout.

## How are the extra delivery days checked?
- The field (`transit_adjustment_days`, 'Extra Delivery Days') takes a whole number of 0 or more: `pg_country_form_transit_days_error()` (in `pg_country_form_required_errors()`) refuses anything else under the field, an empty box is 0, and the screens store `pg_country_form_transit_days()` (int). The box is an `integer` inputmask with `min="0"`. Why (#5597): the INT UNSIGNED column without strict mode stored '-5' as 0 and '12,50' as 12 silently.

## What else?
- `edit_country.php` runs every query through `db_q()` with the id from the not-found guard. Tests: `tests/country_form_test.php`; `tests/db_q_commerce_tail_test.php` counts the screens' `db_q()` calls.
