---
name: reset-link-revoked-on-change
description: A pending password reset or set password link (user.token) is cleared when the owner changes the password or the e-mail address on the site.
---

## Which changes end a pending link?
- `change_password.php`: the password UPDATE also writes `token = NULL, token_timestamp = 0`.
- `email_preferences.php` (signed in): the UPDATE clears them when the posted address differs from the stored one; an unchanged address leaves the link alone.
- `set_password.php` already cleared them (the link is single use); `reset_password.php` (panel) issues a fresh link after its update, which replaces the old one.

## Why?
- The link is mailed to the address of the moment and stays live for 24 hours. Before, a link sitting in an old or lost mailbox still set a new password after the owner had changed the password or moved to another address.

## Where is the test?
- `tests/account_reset_link_revoke_test.php` (source checks of both UPDATEs).

## What is not covered?
- Panel e-mail changes (`pg_user_update()` in `includes/fn/user_write.php`, the Workspace's `ws_change_write_user()`) still leave a pending link; an administrator made them.
