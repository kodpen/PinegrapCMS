---
name: state-shared-form
description: add_state.php and edit_state.php draw the state/province options card and read its posted values through includes/forms/state_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/state_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_state_form_card($values)` (name, code, country; `$values` null on create, which prints no values and no preselected country) and `pg_state_form_values($_POST)` (name, code, country id as text, in INSERT/UPDATE order). PR #2619.
- A screen keeps its gates (`validate_user()`, `validate_ecommerce_access()`), the edit screen's not-found guard and record load, the form tag, the hidden `id`, buttons (edit adds Delete), the INSERT / UPDATE, the log line and the redirect.

## What does a delete clear?
- Only the edit screen deletes: the `states` row, then its `zones_states_xref`, `tax_zones_states_xref` and `verified_shipping_addresses` rows. This stays in `edit_state.php`.

## What changed for a posted value?
- `pg_state_form_values()` (pure, tests in `tests/state_form_test.php`): a missing or non-scalar value is empty; before, an array was saved as "Array" with a notice.
