---
name: user-disabled
description: Disabled users (2026.11.0, 11.0.160): user.user_disabled_at, the helpers every sign-in path asks, the gates, the Account enabled switch and its guards (#6186)
module: auth
---

## What is a disabled user?
- `user.user_disabled_at` INT UNSIGNED, 0 = enabled, else the moment it was disabled (a stamp, as `user_created`). Decided in #6186; imported WordPress authors start disabled (`platform/import-wordpress.md`). `user_password_algo = 3` means an external account, not a disabled one.
- A disabled row matches no credential and its content stays.

## Which helpers are there? (`includes/fn/auth.php`)
- `pg_user_has_disabled_at()` probe (bridge-safe), `pg_user_is_disabled($row)` (pure), `pg_user_id_is_disabled($id)`, `pg_user_enabled_sql($alias)` (` AND user_disabled_at = 0`, nothing before the step). `get_file.php` has no `functions.php`: `pg_auth_user_disabled()` in `includes/authentication.php`.
- `create_member_user()` takes `disabled_at`.

## Where is it checked?
- `validate_login()` (falls into the unknown-name branch, dummy verify), `validate_user()` (deleted-account branch: session cleared), `pg_session_signed_in()`, `initialize_user()` (remember-me token, API password, session row), `get_file.php`.
- `forgot_password.php` (same neutral reply), the reset token in `set_password.php` and `get_set_password.php`, `shipworks.php`.
- Provider sign-in: `pg_signin_match_user()` and `pg_signin_address_user()` skip it; `pg_signin_reaches_disabled()` makes `signin.php` refuse an identity or address of a disabled account (no link, no second user).
- Passkey lookup (`includes/fn/passkeys.php`), `pg_mfa_screen_complete()` (`mfa.php`), `login_as_user.php`, `includes/api/auth.php` (key owner, `api_load_owner()`), `includes/api/devices.php` (bearer and OAuth tokens).
- The Workspace calendar subscription address (`workspace_ical.php`, `ws_calendar_feed_holder_state()` in `includes/workspace/calendar_feed.php`): a disabled holder gets the same 404 as an unknown token; its `ws_calendar_feed` row is kept, so turning the account back on brings the address back. Before this a disabled person's tasks, plan items and approval deadlines kept being read by their calendar service (#PR).

## What does the user screen do?
- `edit_user.php`: "Account enabled" switch, drawn and saved only with the column. `pg_user_form_may_disable()` (`includes/forms/user_form.php`) refuses one's own account and the last enabled administrator; off revokes remember-me tokens. The last-administrator checks of the screen count enabled administrators only. "Login as User" is hidden for a disabled account.
- The user list shows a Disabled badge (`includes/fn/user_list.php`).

## What is not covered?
- `delete_users.php` and the list's administrator count still count disabled administrators.
- The installer lock's password check runs on the bridge and is left alone.
