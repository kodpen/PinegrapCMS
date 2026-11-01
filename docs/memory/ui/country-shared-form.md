---
name: country-shared-form
description: add_country.php and edit_country.php draw the country options card and read its posted values through includes/forms/country_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/country_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_country_form_card($values)` (name, code, the zip code required and selected by default switches, transit adjustment days; `$values` null on create, which prints no values and no switch on) and `pg_country_form_values($_POST)` (the five fields as text, in INSERT/UPDATE order). The edit screen passes its stored row through `pg_country_form_values()` too.
- A screen keeps its gates (`validate_user()`, `validate_ecommerce_access()`), the edit screen's not-found guard and record load, the form tag, the hidden `id`, buttons (edit adds Delete), the INSERT / UPDATE, the reset of `default_selected` on every other country, the log line and the redirect.

## How is a taken code refused?
- `pg_country_form_code_error($code, $stored_code, $except_id)` answers 'The code that you entered is already in use by another country…' when another country has the code (`pg_country_form_code_taken()`, db_q, edit leaves its own id out). Only a non-empty code that is new or changed is looked up, so an edit keeping an old clash still saves; delete is never held back.
- The name and code are trimmed in `pg_country_form_values()`, and the code error compares against the trimmed stored code, so a legacy padded code posted back unchanged is not looked up. Why: `utf8mb4_unicode_ci` (PAD SPACE) ignores trailing but not leading spaces, so ' US' passed the check beside 'US', and checkout (trimmed post) never found it.
- Why: checkout's zone lookup and the product destination check take the country id from the first `countries` row with the code. Create/Edit Currency already refused a taken code.
- The screen draws the form again with the posted values and `pg_country_form_card($values, $errors)` marks the code field (`is-invalid`, `aria-describedby="code-error"`, message under it); nothing is written. Tests: `tests/commerce_code_taken_test.php`.

- Only the edit screen deletes: for each state of the country its `zones_states_xref`, `tax_zones_states_xref` and `verified_shipping_addresses` rows, then the `states`, `zones_countries_xref`, `tax_zones_countries_xref` and `countries` rows. Every one of these DELETEs, and the save's reset of `default_selected` on the other countries, carries `or output_error('Query failed.')`: a failure stops before the log line and redirect instead of leaving half a country or two default countries (`tests/checked_writes_country_shipping_currency_test.php`, which also pins Edit Shipping Method's DELETEs).

## Are the name and the code required?
- Yes (#4413, PR #4456): `pg_country_form_required_errors($values)` answers 'Enter a name.' / 'Enter a code.' for a blank one; both boxes carry `required` and the card prints the message under the field. Edit checks it too; delete is never held back. Why: a nameless country was an empty row in every list and zone picker, and a codeless one cannot be matched by checkout. Create Country now has the back arrow to `view_countries.php`, and its breadcrumb lost a copied `?filter=all_ad_regions`.

## What changed with the move?
- `edit_country.php` runs every query through `db_q()` with the integer id from the not-found guard (before: `escape()` concatenation of `$_POST['id']`); the guard loads the row it checks, so the screen reads the country once.
- A missing or non-scalar posted value is empty (before, an array was saved as "Array" with a notice). The edit card now sits in the same `row` wrapper as the create card.
- Tests: `tests/country_form_test.php`; `tests/db_q_commerce_tail_test.php` counts the `db_q()` calls of both screens.
