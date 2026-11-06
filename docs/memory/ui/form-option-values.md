---
name: form-option-values
description: Option fields of forms: the site refuses a value outside the list, Edit Submitted Form keeps a stored one, check box group hidden field before the fieldset
---

## What does Edit Submitted Form do with a stored value the list no longer has?
- An option renamed or deleted after the submission: on an existing submission `pg_submitted_form_keep_stored_options()` (`includes/forms/submitted_form_form.php`) adds each such value of a pick list, radio or check box group as an extra option "<value> (not in the list)", selected as stored; its value is the stored one, so saving another field writes it back unchanged.
- Not for reference / product / page fields (their choices are records, `pg_coll_kind_options()`).
- Symptom fixed (#4226): the pick list opened on its first option, the boxes came unchecked, and saving any field replaced or dropped the value without a word.

## What does the site accept?
- `custom_form.php` holds a pick list, radio or check box value to the field's `form_field_options` values with `pg_cf_option_values_ok()` (`includes/fn/custom_form.php`), marking "Please choose a valid option for …" on the field.
- Allowed: an empty value; values compared exactly, spaces around either side aside; a nested array is refused.
- Not checked: reference / product / page fields (`pg_coll_check_field()` holds them), and a field with no option rows (a designed check box without a `value` posts "on" and stores no option).
- Symptom fixed (#4227): a hand-made request stored any value, which went to exports, e-mails and lists.
- Custom shipping and billing forms (`submit_custom_form()`, used by express order, shipping address and billing information) apply the same check through `pg_cf_option_value_error()`; before that a hand-made request stored any value in the order's `form_data` (bug hunt fix).
- Product forms: the express order holds them with `pg_cf_option_value_error()`; the designer cart widget's handler (`cart_action.php`) with `pg_product_form_value_error()` (`includes/fn/forms.php`), which also refuses a date, date and time, e-mail address or time that is not one, as the classic cart does. A refused value keeps the stored one and the error holds checkout. Before, a hand-made request (and in the widget any typed text) stored any value in the order's `form_data` (bug hunt fix). The classic cart (`shopping_cart.php`) does not hold product form options to the list yet.
- Tests: `tests/form_option_values_test.php`, `tests/custom_form_shipping_options_test.php`, `tests/product_form_values_test.php`.

## Where does a check box group's hidden field go?
- Before the `<fieldset>`, never right after the `<legend>`: Bootstrap 6 floats the legend and clears only its next sibling, so a hidden input there let the first option fall beside the legend at 0 px on the card's right edge and scrolled the page sideways (#4225). It still comes before the boxes, so PHP keeps their array.
