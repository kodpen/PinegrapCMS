---
name: edit-user-return-key
description: Return in an Edit User field saves through a hidden nameless first submit, not the Create Contact button printed inside the main form
---

## Which button does Return press on Edit User?
- The form's first submit button. `edit_user.php` opens `<form name="form">` with `<button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>` right after the token field.
- It has no name, so the create-contact, delete and early handlers (unlock, unlink, MFA reset) are skipped and the post reaches the final `else`, the save branch, which reads no button name. Keep it that way: never make that branch need `$_POST['submit_save']`.
- Ctrl+S and its tooltip look for `button[name="submit_save"]` first, so they still use the visible Save.

## Why does the form need it?
- For a user without a linked contact, the Contact card prints a named Create Contact submit inside the main form, before Save (sticky bar). Return in Username, User Email, Password Hint, Reward Points, Badge Label or a permission drawer field pressed it.
- Before PR #3511 that inserted a contact, linked it, redirected to Edit Contact and dropped the typed edits. Users with a contact were not affected (Save was first).
- Create Contact stays in this form: it posts `id` and `current_url` from it. General pattern: `return-key-default-button.md`.

## Which test pins it?
- `tests/edit_user_return_key_test.php`: `test_edit_user_return_key_form_starts_with_a_nameless_submit` and `test_edit_user_return_key_save_branch_reads_no_button_name`.
