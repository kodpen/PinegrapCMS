---
name: reset-link-revoked-on-change
description: A pending password reset or set password link (user.token) is cleared when the password or the e-mail address changes, on the site, Edit User, the users list or the Workspace.
---

## Which changes end a pending link?
- `change_password.php`: the password UPDATE also writes `token = NULL, token_timestamp = 0`.
- `email_preferences.php` (signed in): the UPDATE clears them when the posted address differs from the stored one; an unchanged address leaves the link alone.
- `set_password.php` already cleared them (the link is single use); `reset_password.php` (panel) issues a fresh link after its update, which replaces the old one.

## Why?
- The link is mailed to the address of the moment and stays live for 24 hours. Before, a link sitting in an old or lost mailbox still set a new password after the owner had changed the password or moved to another address.

## Where is the test?
- `tests/account_reset_link_revoke_test.php` (source checks of both UPDATEs); the staff paths in `tests/member_accounts_test.php`.

- Staff changes of the address end it too: Edit User and the API (`pg_user_update()` in `includes/fn/user_write.php`), the users list's inline edit (`includes/fn/user_list_edit.php`) and the Workspace's `ws_change_write_user()` put `pg_user_reset_link_clear_sql()` (`includes/fn/auth.php`) before `user_email` in the SET. Before, a link sitting in the mailbox a member had lost stayed live after an administrator moved the account to a new address.

## How does the staff UPDATE tell a new address?
- `pg_user_reset_link_clear_sql($new_email)` sets `token` and `token_timestamp` with `IF(BINARY user_email = BINARY '<new>', kept, cleared)`; MySQL assigns left to right, so `user_email` is still the stored one there. Byte for byte, because the column's collation folds case and accents.
