---
name: referral-source-shared-form
description: add_referral_source.php and edit_referral_source.php draw the referral source card and read its posted values through includes/forms/referral_source_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/referral_source_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_referral_source_form_card($values)` (name, sort order, code; `$values` null on create, which prints no values) and `pg_referral_source_form_values($_POST)` (name, code, sort order as text, in INSERT/UPDATE order). PR #2637.
- A screen keeps its gates (`validate_user()`, `validate_ecommerce_access()`), the edit screen's not-found guard and record load, the form tag, the hidden `send_to` (both), `submitted_button_field` (create) and `id` (edit), buttons (edit adds Delete), the INSERT / UPDATE / DELETE, the log line and the redirect.

## What changed for a posted value?
- `pg_referral_source_form_values()` (pure, tests in `tests/referral_source_form_test.php`): a missing or non-scalar value is empty; before, an array was saved as "Array" with a notice.
