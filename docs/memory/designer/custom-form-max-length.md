---
name: custom-form-max-length
description: A custom form field's maximum length (form_fields.maxlength) is checked by custom_form.php as well as the browser: which types, how it is counted, the message
module: custom_form
---

## Where is a field's maximum length held?
- `form_fields.maxlength` (0 for none). The legacy form draws it as `maxlength` on a text box, text area, e-mail address and file upload (`includes/fn/custom_form.php`, `get_form_info()`); a designed form writes the control's `maxlength` into the row on save.
- `custom_form.php` refuses a longer value too: anything can POST to it, so the attribute alone held nobody but a person using the form as drawn.

## Which fields are checked on submit, and how?
- Text box, text area and e-mail address that the form drew for the visitor; not a rich text area (its value is HTML, not the text counted) and not a list of choices (held to its options).
- `pg_cf_value_too_long($value, $maxlength)`: letters, not bytes (`mb_strlen`), and CRLF counts once, as the browser counts a text area's line break.
- Message: '{var:1} can be at most {var:2} characters long.' on the field.
- Added in the form submissions bug hunt; pinned by `tests/custom_form_submit_checks_test.php`.

## What is not checked here?
- The panel (Add/Edit Submitted Form), the API and MCP are not held to it, as with the visitor rules (`custom-form-field-rules.md`).

Related: docs/memory/designer/custom-form-options-and-rules.md, docs/memory/designer/custom-form-url-prefill.md
