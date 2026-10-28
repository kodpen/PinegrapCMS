---
name: country-shared-form
description: add_country.php and edit_country.php draw the country options card and read its posted values through includes/forms/country_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/country_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_country_form_card($values)` (name, code, the zip code required and selected by default switches, transit adjustment days; `$values` null on create, which prints no values and no switch on) and `pg_country_form_values($_POST)` (the five fields as text, in INSERT/UPDATE order). The edit screen passes its stored row through `pg_country_form_values()` too.
- A screen keeps its gates (`validate_user()`, `validate_ecommerce_access()`), the edit screen's not-found guard and record load, the form tag, the hidden `id`, buttons (edit adds Delete), the INSERT / UPDATE, the reset of `default_selected` on every other country, the log line and the redirect.

## What does a delete clear?
- Only the edit screen deletes: for each state of the country its `zones_states_xref`, `tax_zones_states_xref` and `verified_shipping_addresses` rows, then the `states`, `zones_countries_xref`, `tax_zones_countries_xref` and `countries` rows.

## What changed with the move?
- `edit_country.php` runs every query through `db_q()` with the integer id from the not-found guard (before: `escape()` concatenation of `$_POST['id']`); the guard loads the row it checks, so the screen reads the country once.
- A missing or non-scalar posted value is empty (before, an array was saved as "Array" with a notice). The edit card now sits in the same `row` wrapper as the create card.
- Tests: `tests/country_form_test.php`; `tests/db_q_commerce_tail_test.php` counts the `db_q()` calls of both screens.
