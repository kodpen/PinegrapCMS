---
name: edit-contact-return-key
description: Return in an Edit Contact field saves through a hidden nameless first submit; before, with the address suppressed, it pressed Allow again, lifted the block and dropped the edits
---

## Which button does Return press on Edit Contact?
- The form's first submit button. `edit_contact.php` opens `<form name="form">` with `<button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>` right after the token field, as Edit User does (`edit-user-return-key.md`).
- It has no name, so the Allow again and Delete branches are skipped and the post reaches the final `else`, the save branch, which reads no button name. Never make that branch need `$_POST['submit_save']`.
- The save bar keeps its own `pg_save_bar_return_button()` before Delete (`save-bar-helper.md`); it is no longer the first submit of the form.

## Why does the form need it?
- While the contact's address is suppressed (a bounce or complaint, `pg_mail_bounce_contact_badge()` in `includes/fn/mail_bounce_report.php`), the Subscriber Information card prints a named `submit_allow_again` button inside the form, before the save bar.
- Return in any box (name, e-mail, phone, notes) pressed it: the block on the stored address was lifted, the screen reloaded and every edit was lost. Fixed in the Contacts screens bug hunt PR.

## Which test pins it?
- `tests/contact_screens_test.php`: `test_contact_screens_edit_contact_return_key_saves`.
