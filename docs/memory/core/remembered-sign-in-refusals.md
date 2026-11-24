---
name: remembered-sign-in-refusals
description: The one decision that refuses a remembered device (software[auth] token): disabled, blocked member, required two-step missing; asked by initialize_user() and get_file.php alike (#11454)
---

## Which remembered devices are not signed in?
- `pg_auth_remembered_signin_refusal($user_id)` in `includes/authentication.php` answers 'disabled', 'blocked' (a member on the e-mail block list), 'factor' (the role requires two-step and the account has no factor) or ''. The order is fixed by the pure `pg_auth_remembered_refusal($disabled, $blocked, $factor_owed)`: the first reason wins.

## Who asks it, and what does each do?
- `initialize_user()` (`includes/fn/auth.php`): 'disabled' and 'blocked' clear the cookie ('blocked' also writes a log line); 'factor' revokes the token and sends the visitor to the setup (`pg_mfa_gate()`).
- `get_file.php`'s own remember-me: 'disabled' and 'blocked' clear the cookie; 'factor' only declines the token and leaves the cookie, so the panel's next request makes the full decision. Before #11454 it checked only the disabled flag: a file link opened first signed a blocked member, or an account owing the two-step setup, into a session that then passed the panel.

## How does get_file.php answer without functions.php?
- With functions.php loaded the function asks the panel helpers (`pg_user_id_login_blocked()`, `pg_mfa_required_for_user()`, `pg_mfa_has_factor()`). In `get_file.php` it reads `config.banned_email_addresses` and `config.mfa_required_role` itself; the list match is the shared `pg_auth_email_on_list()` (also behind `pg_email_blocked()`), `pg_email_fold()` sits beside it.
- Its factor answer is on the safe side: only an authenticator key counts, a passkey does not (whether a passkey counts depends on the host). A passkey-only account of a required role is not adopted by a file request; the panel signs it in on its next page.

## Tests?
- `tests/remembered_signin_test.php` (order, list match, both paths ask it); `tests/member_accounts_test.php` (`test_remember_me_refuses_blocked_member`).
