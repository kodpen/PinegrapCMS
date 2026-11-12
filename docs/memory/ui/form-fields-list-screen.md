---
name: form-fields-list-screen
description: A custom form's fields list (view_fields.php) and Edit Field: the notices Create, Save and Delete leave are printed once, and the form type is title-cased with pg_upper_words()
---

## Why did "The field has been saved." show on every visit?
- Create (`add_field.php`), Save and Delete (`edit_field.php`) and Delete Selected (`delete_fields.php`) queue their notice on `new liveform('view_fields')`. The list printed `$liveform->get_messages()` but never removed the form, so the notices piled up in the session and came back on each reload (#9028).
- `view_fields.php` now calls `$liveform->remove_form()` after the page is printed, as the trap in `liveform-notices-trap.md` asks. Pinned by `tests/form_fields_screen_heading_test.php`.

## Why did the heading read "özel Form"?
- The fields list's heading and title, and Edit Field's heading ("Edit {var:1} Field") and summary line ("Custom Form: <name>"), passed `lang('custom form')` ("özel form") through byte-wise `ucwords()`, which leaves a multibyte first letter small (#9032).
- All three field screens (`view_fields.php`, `edit_field.php`, `add_field.php`) use `pg_upper_words()` (`includes/fn/contacts.php`, Turkish `i` → `İ`), the rule of `panel-avatar.md`. Same test file.
