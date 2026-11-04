---
name: state-shared-form
description: add_state.php and edit_state.php draw the state/province wide layout (Options card, Summary, save bar) and read its posted values through includes/forms/state_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/state_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_state_form_cards($values, $errors = array(), $status_rows = '')` (the form column with the Options card: name, code, country; `$values` null on create, which prints no values and the default country; and the sticky status column), `pg_state_form_status_row()`, `pg_state_form_layout($cards, $buttons)` (the row with the sticky save bar) and `pg_state_form_values($_POST)` (name, code, country id as text, in INSERT/UPDATE order). PR #2619.
- A screen keeps its gates (`validate_user()`, `validate_ecommerce_access()`), the edit screen's not-found guard and record load, the form tag, the hidden `id`, buttons (edit adds Delete), the INSERT / UPDATE, the log line and the redirect.

## How is the screen laid out?
- The product_builder pattern (`ui/edit-screen-wide-layout.md`, B11, PR #6698): form column, sticky status column, sticky save bar. The Summary shows the name, the code and the country (the selected option's label, `pg_state_form_selected_label()`, empty for the 'Select a country' choice of a lost country) and follows the fields as they change; edit adds the Shipping Zones, Tax Zones and Verified Shipping Addresses it is in (`pg_state_form_delete_counts()`, read once as `$delete_counts` and passed to the Delete confirmation too) and Last Modified (`states.timestamp` and `user`). Edit's Delete is `formnovalidate`. Tests: `tests/state_form_test.php`.

## How is a taken code refused?
- `pg_state_form_code_error($values, $stored, $except_id)` answers '…already in use by another state in this country…' when another state of the same country has the code (`pg_state_form_code_taken()`, db_q). Looked up only for a non-empty code, and on edit only when the code or the country changed; delete is never held back.
- The screen draws the form again with the posted values; `pg_state_form_card($values, $errors)` prints the message under the code field. `edit_state.php` now loads the row before the branch for this. Tests: `tests/commerce_code_taken_test.php`.

- Only the edit screen deletes: the `states` row, then its `zones_states_xref`, `tax_zones_states_xref` and `verified_shipping_addresses` rows. This stays in `edit_state.php`.

## Are the name and the code required?
- Yes (#4413, PR #4456): `pg_state_form_required_errors($values)` ('Enter a name.' / 'Enter a code.'), `required` boxes, the message under the field; checked on create and save, never on delete. The country select (`select_country()`, `includes/fn/forms.php`) names and orders countries in the panel language (#4426, `commerce/country-names-display.md`); its value stays the country id.

## Must the country exist?
- Yes (#5598): `pg_state_form_country_error()` answers 'Select a country.' for an id that is no positive number and 'The selected country was not found. Select a country.' for one no `countries` row has; create and save refuse it under the Country field. The edit screen shows the message for a state whose stored country is gone, and `pg_country_select_options()` then puts an empty selected 'Select a country' option first. Why: the state was saved under a deleted country, listed with no country, and the edit screen's select fell back to the first country, so a save moved it there unseen.

## What changed for a posted value?
- `pg_state_form_values()` (pure, tests in `tests/state_form_test.php`): a missing or non-scalar value is empty; before, an array was saved as "Array" with a notice. The name and code are trimmed; the code error compares against the trimmed stored code (PAD SPACE ignores only trailing spaces, so ' TX' passed beside 'TX' and checkout's trimmed lookup missed it).
