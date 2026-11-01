---
name: form-field-errors
description: Which controls a refused server submission marks (is-invalid, aria-invalid, linked invalid-feedback) on designed forms, member widgets and classic liveform fields (#4458).
module: custom_form
---

## What does a refused submission mark on a designed form?
- Every control of a failed field gets `is-invalid` and `aria-invalid="true"`; one `div.invalid-feedback.d-block.pg-inline-feedback` with the message as plain text and an id is added after the field's last control (after a `<label>` right behind it, so a check box keeps its label; a check box or radio inside a `form-check` row gets it last in that row, after a privacy link, PR #4567 for #4544), and every control names it in `aria-describedby` (an existing hint id is kept first).
- Feedback id: `<first control id>-feedback`, else `pg-field-<name>-<n>-feedback`. A message `''` marks without a text.
- Before #4458 only the alert box listed the errors; the fields carried nothing (accessibility report rule `a11y_error_association`).

## Where is it applied?
- `pg_form_mark_invalid_controls($tree, $errors)` (`includes/fn/custom_form.php`, pure; tests `tests/form_invalid_controls_test.php`). `$errors` comes from `liveform::field_error_texts()`: failed fields by name with the alert HTML reduced to text (`pg_liveform_message_text()`); the form-wide `''` error is left out.
- Custom form widget: right after `pg_cf_apply_field_bindings()` (controls are named by field id then, as the errors are).
- Member widgets: at the start of `_pg_member_fill_from_form()`, before the skip list, so password fields are marked though never refilled.
- Both run before the Messages node consumes the liveform.

## And the classic `liveform::output_field()` path?
- The inline text it prints for a failed field has id `<control id>-feedback` (or `pg-field-<name>-feedback`) and the control's `aria-describedby` names it, after any hint given in `aria-describedby`. The designed sign-up's CAPTCHA comes from this path (`get_captcha_fields()`).

## What does a required lone check box say?
- `pg_cf_required_message()` (`includes/fn/custom_form.php`): a `check box` field with at most one option (a consent) says "Check this box to continue."; any other required field "<label> is required.". Before PR #4567 a consent repeated its whole sentence plus "is required." (#4544).
