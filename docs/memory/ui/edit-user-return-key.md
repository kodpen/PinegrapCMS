---
name: edit-user-return-key
description: Return in an Edit User field saves through a hidden nameless first submit; the Create Contact button saves the form first, then creates and opens the contact
---

## Which button does Return press on Edit User?
- The form's first submit button. `edit_user.php` opens `<form name="form">` with `<button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>` right after the token field.
- It has no name, so the create-contact, delete and early handlers (unlock, unlink, MFA reset) are skipped and the post reaches the final `else`, the save branch, which reads no button name. Keep it that way: never make that branch need `$_POST['submit_save']`.
- Ctrl+S and its tooltip look for `button[name="submit_save"]` first, so they still use the visible Save.

## Why does the form need it?
- For a user without a linked contact, the Contact card prints a named Create Contact submit inside the main form, before Save (sticky bar). Return in Username, User Email, Password Hint, Reward Points, Badge Label or a permission drawer field pressed it.
- Before PR #3511 Return pressed Create Contact. General pattern: `return-key-default-button.md`.

## What does Create Contact do?
- It is handled inside the save branch, after the user UPDATE, the access save and the log line: the form is saved with every check of that branch, then a contact is inserted with the saved e-mail address, linked, and Edit Contact opens (`send_to` = the Edit User address). A user that already has a contact (a second press) gets that contact opened, not a new one.
- Before PR #4113 (#4065) it was its own early branch that read only `id`: every unsaved edit (role, rights, badge, e-mail) was dropped without a word.
- Edit Contact's title names the contact by `pg_contact_heading_name()` (`includes/fn/contacts.php`): full name, else company, else e-mail, so a contact made from a user is not "Edit Contact : ". The contacts list's row name uses the same function.

## What does the Recent Activity button count?
- The drawer lists the user's last 100 log entries; the button counts them all (`COUNT(*)`) and reads "All (n)" or, beyond 100, "Last 100 of n" (PR #4113).

## Which test pins it?
- `tests/edit_user_return_key_test.php`: `test_edit_user_return_key_form_starts_with_a_nameless_submit` and `test_edit_user_return_key_save_branch_reads_no_button_name`.
- `tests/user_screens_words_test.php`: `test_user_screens_create_contact_runs_after_the_save`, `test_user_screens_contact_heading_name_falls_back_to_email`, `test_user_screens_activity_button_counts_every_entry`.
