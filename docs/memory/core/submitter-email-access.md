---
name: submitter-email-access
description: How a member gets a submitted form's rights by e-mail address (submitter or watcher) and why a blank address never matches
---

## When does an e-mail address give a member a submission's rights?
- When the member's stored address equals the submission's connect-to-contact e-mail field (`form_fields.contact_field = 'email_address'`), letter case aside, or a `watchers` row for that item carries the member's address.
- Checked in `add_or_remove_watcher.php`, `get_form_item_view.php`, the three comment-attachment paths of `get_file.php`, and `add_comment.php` (posting a comment to a record whose page has submitter security).

## Why must a blank address never match?
- A submission without an e-mail field has no `form_data` row (null), and a watcher added by user id has `email_address = ''`. The old `mb_strtolower(USER_EMAIL_ADDRESS) == mb_strtolower($submitter)` turned null into '' and let a member with no stored address view any such submission and its attachments; `email_address = ''` matched every user-id watcher row likewise (and `mb_strtolower(null)` is deprecated on PHP 8.1).
- Fix (#5015 for the watcher handler, #5100 for the form item view and file access, #PRNUM for posting a comment, where the old comparison let such a member comment on any such submission): the submitter check is `pg_email_address_matches()`; a watcher lookup by address adds `AND (email_address != '')`.

## Where does the helper live?
- `pg_email_address_matches()` sits in `includes/authentication.php` (moved from `includes/fn/auth.php` in #5100) because `get_file.php` never loads `includes/fn/` ([[get-file-shared-code]]). Pure PHP, within that file's contract.
- Tests: `tests/watcher_email_match_test.php`, `tests/submitter_email_match_test.php`.
