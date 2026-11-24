---
name: email-block-list-sign-in
description: A member (role 3) whose address is on the e-mail block list signs in by no path (password, API, passkey, provider, reset link, remembered device); staff are never refused
module: auth
---

## What does the block list refuse?
- Sign-up with a listed address (`pg_member_register()`, `pg_member_activate()`), and every sign-in of a member (role 3) whose stored address is listed: `pg_login_email_block_refuses($role, pg_email_blocked($email))` in `includes/fn/auth.php`.
- Staff (role 0 to 2) are never refused: an operator who types their own address into the list must still reach the screen that holds it.

## Which sign-in paths ask it?
- Password: `validate_login()` (the sign-in screens, Change Password), `initialize_user()` for api.php credentials; a refusal costs a dummy `password_verify()` like a wrong password.
- Passkey without a name: `pg_passkey_passwordless_refusal()`; Google, Microsoft, Apple: `pg_signin_block_refuses()` in `signin.php`.
- The reset link (`set_password.php`): the password is set, but `pg_post_password_signin()` opens no session when `pg_post_password_signin_refusal()` says 'blocked' (or 'factor' for an account with a second step); the visitor is told to sign in, where the password is refused. Before, a blocked member reset the password and was signed in by the link.
- A remembered device: `initialize_user()` and `get_file.php` both ask `pg_auth_remembered_signin_refusal()` (`includes/authentication.php`, see `remembered-sign-in-refusals.md`); a blocked member's `software[auth]` token signs nobody in and the cookie is cleared. Before, the device stayed signed in for the token's whole life.

## What is not covered?
- A session already open when the address is listed runs on until it ends; only a new sign-in is refused.

## Tests?
- `tests/member_accounts_test.php` (`pg_post_password_signin_refusal()`, the remember-me check); `tests/login_refusal_test.php` for the password paths.
