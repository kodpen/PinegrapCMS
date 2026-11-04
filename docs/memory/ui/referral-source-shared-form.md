---
name: referral-source-shared-form
description: add_referral_source.php and edit_referral_source.php draw the referral source columns and save bar and read its posted values through includes/forms/referral_source_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/referral_source_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`): `pg_referral_source_form_card($values)` (name, sort order, code; `$values` null on create, which prints no values) and `pg_referral_source_form_values($_POST)` (name, code, sort order as text, in INSERT/UPDATE order). PR #2637.
- The screens follow the wide layout (`edit-screen-wide-layout.md`, step B11): `pg_referral_source_form_card($values)` is the form column, `pg_referral_source_form_status($values, $rows)` the sticky Summary (name, code, sort order as typed, an empty sort order reads 0; extra rows through `pg_referral_source_form_status_row()`: Other Referral Sources count on create, Last Modified with the user on edit, a dash for a row with no timestamp), `pg_referral_source_form_layout($columns, $buttons)` the row and the sticky save bar.
- A screen keeps its gates (`validate_user()`, `validate_ecommerce_access()`), the edit screen's not-found guard and record load, the form tag, the hidden `send_to` (both), `submitted_button_field` (create) and `id` (edit), buttons (edit adds Delete), the INSERT / UPDATE / DELETE, the log line and the redirect.

## What changed for a posted value?
- `pg_referral_source_form_values()` (pure, tests in `tests/referral_source_form_test.php`): values are trimmed; a missing or non-scalar value is empty; before, an array was saved as "Array" with a notice.
- `pg_referral_source_form_error($values, $others)` returns '{var:1} is required.' for a blank name, then a blank code; then refuses a sort order that is not a whole number 0–999 (empty is 0, stored through `pg_referral_source_sort_order()`); then a code or name another source already has (`$others`: name and code of every other row, edit leaves its own id out with `WHERE id <> ?`; compared without case like the table's collation), else ''. No unique index: the check is in the form only, as the issue asked (PR #5713, issues #5680, #5681). Before, the session's non-strict sql_mode stored 40000 as 32767 and 'abc' as 0, and two sources could share a code (orders keep only the code) or a name (old checkout pages key the options by name). Create and the edit save branch (never Delete) print it with `output_error()` and Go back before the INSERT / UPDATE. Before, a name or code of only spaces passed the browser's `required` and was stored, empty cells in the list and in checkout's 'How did you hear about us?' select.
- The sort order box carries `min="0" max="999"` and the integer mask without minus: the panel's +/- buttons read `max` from the box, and without it + at 999 wrote 1000, cut to 100 by `maxlength="3"`.
- Edit's Delete submit carries `formnovalidate`, so a cleared required box does not block it.

## What do the texts and the list say?
- The card heading is 'New Referral Source Information' on create and 'Referral Source Information' on edit; the field group says the source is shown in checkout's 'How did you hear about us?' choice (it said 'Order Preview Page Display Options'). Edit's heading description is its own 'Edit a referral source …' key (PR #5713, #5682).
- `view_referral_sources.php` has a Sort Order column and sorts by it first by default (then name), the order checkout shows; create, save and delete leave a `view_referral_sources` liveform notice the list prints (PR #5713, #5683).
