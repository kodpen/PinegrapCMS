---
name: form-field-errors-before-sending
description: A designed form field the browser refuses says what is wrong in text beside it, linked by aria-describedby: pg_cf_validation_script() (#9054)
module: custom_form, custom_form_steps
---

## What does a visitor see when the browser refuses a field?
- The custom form widget and the member widgets draw `<form novalidate class="needs-validation pg-cf-form">`: the browser shows none of its own messages.
- On submit `pg_cf_validation_script()` writes, for each refused field, what the server writes for a refused submission ([[form-field-errors]]): one `div.invalid-feedback.d-block.pg-inline-feedback` (`data-pg-source="check"`), id `<first control id>-feedback` (else `pg-field-<name>-feedback`), named in every control's `aria-describedby`, and `aria-invalid="true"`. Then it focuses the first refused control.
- Place: after the field's last control (after a label right behind it), last in a `form-check` row, last in an `input-group` (which gets `has-validation`, so the text wraps under the box and its button). The server's marking places an input group's text the same way now.
- Symptom before (#9054): `/bulten` with "abc" in the e-mail box turned three fields red with an icon and gave no text anywhere.

## Which words?
- `pg_cf_validation_messages()`, printed into the script in the page's language: a missing answer "<label> is required.", a lone check box "Check this box to continue." (as `pg_cf_required_message()`), a malformed e-mail "Please enter a valid e-mail address for <label>" (as the custom form's check). The label is read from the page: a group's own `legend` (not a step's), else the control's `label` or `aria-label`, a closing `*` or `:` left off, none over 80 characters.
- No label: "Fill in this field." or, for options, "Choose one of the options."; e-mail "Enter a valid e-mail address.". Other checks (pattern, range, length) take the browser's `validationMessage`.

## When does the text change or go?
- After every input or change: a field the script reported on is checked again (text updated, or text, `aria-invalid` and the server's `is-invalid` removed once right). A field not reported on gets text live only inside a `.was-validated` form or step.
- A server-refused field keeps the server's text until the script finds the field wrong itself; it then takes over that same element.
- A stepped form's Next calls `form.pgFieldErrors(step)` for the shown step (`pg_cf_steps_script()`); `reportValidity()` only without it.

Tests: `tests/custom_form_field_errors_test.php` (wording, markup, order, Node syntax check), `test_fic_input_group_feedback_last` in `tests/form_invalid_controls_test.php`.
