---
name: user-screen-checks
description: The checks of the user create, edit, import and bulk delete screens: role, private folder dates, notify mail, registered filter, default view, repeated headings
---

## How is the posted role read?
- `pg_user_form_role($value, $missing)` (`includes/forms/user_form.php`): only a whole number 0-3 (`ctype_digit()`); anything else is null and the screen answers "Access denied." Create and Import pass `3` for a missing field; Edit keeps the stored role when nothing is posted (the last administrator's disabled cards).
- Why: the old `(int)` cast turned `role=abc` or `role=` into 0, which passed the 0-3 check and saved an administrator.

## What happens to a private folder's expiration date?
- `pg_user_form_check_expiry_dates($post)` runs before anything is written on all three screens: a checked folder with a date that is not one stops the save with an error naming the folders (`pg_user_form_expiry_problems()`, pure). A date already gone saves and comes back as a warning (`pg_user_form_past_expiry_warning()`).
- Import uses `pg_user_form_folder_rights()` too; its own copy of the rule is gone.
- Why: an invalid date saved rights 0 under "The user has been saved."

## What does the notice say after "Notify User"?
- `add_user.php` reads `email()`'s answer; `pg_new_user_notice($notified, $sent, $url, $password)` (`includes/fn/auth.php`) says when it failed and points to "Forgot password". A failed mail is queued with `add_warning()`, not `add_notice()` (#6555): the account has a password nobody knows. A notified user's password is never shown; a user not notified gets it, as before.
- `import_users.php` lists the users whose e-mail failed in a warning.
- Import reads the e-mail address and user name of a row trimmed in the check loop too (`$row_email_address`, `$row_username`), as the writing loop does: before #9121 " name@example.com" from a spreadsheet refused the whole file as an invalid address, and " admin" passed the in-use check only to be skipped later as taken by an earlier row.

## What is "My Registered Users"?
- `(user.user_contact > 0)`. Create writes NULL without a contact (`pg_user_contact_sql()`); it wrote '' which a lenient server stores as 0, so every panel-made user was listed.

## How does the list's default view show in the picker?
- `pg_user_list_filters()` starts with `default` => "Roles and Permissions", selected while shown; its heading is the same, so it is no longer a second "All My Users".

## What does the import do with two headings for one field?
- `pg_import_unique_columns()` (as Import Contacts) keeps the first; the later one is named in a warning. Before, the contact INSERT named the column twice and failed after the user row was written.

## What else?
- Bulk delete skips an id whose user is already gone (its null role read as an administrator).
- The private folder tree (`get_acl_folder_tree('view')`) leads a folder's label with the parents it leaves out ("pinegrap_cafe › private"), never the root, as `select_folder()` does.
- Issues #5431-#5439.
