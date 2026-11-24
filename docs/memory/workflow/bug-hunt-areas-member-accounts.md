---
name: bug-hunt-areas-member-accounts
description: Bug hunt area 21, member accounts (2026-11-24): sign-up, sign-in, reset, e-mail change, blocked and deleted members; the four faults fixed, what read clean, what was left
---

## What was the area?
- Member sign-up, sign-in, password reset and e-mail change: `registration_entrance.php`, `membership_entrance.php`, `forgot_password.php`, `set_password.php`, `get_set_password.php`, `change_password.php`, `email_preferences.php`, `my_account_profile.php`, `affiliate_sign_up_form.php`.
- And in `includes/fn/auth.php`: `pg_member_register()`, `pg_member_activate()`, `validate_login()`, `initialize_user()`, `pg_post_password_signin()`, the reset rate limit. Hunted in the PR titled "Bug hunt — member accounts".
- To look at: the reset token's life and single use, no answer that tells whether an address exists, rate limits, the session renewed at sign-in, a blocked or deleted member's open session.

## What did it fix? (its PR lists each with file and line)
- Sign-up with "Remember me" minted the token beside `pg_login_set_device_cookie()`, so the new member's session was unbound and revoking that device did not end it (`core/session-token-binding.md`).
- A member whose address is on the block list reset the password and was signed in by the link, and a remembered device kept signing them in (`core/email-block-list-sign-in.md`).
- An address changed on Edit User, the users list, the API or the Workspace left a pending reset link live at the old mailbox (`core/reset-link-revoked-on-change.md`).
- `set_password.php` stopped with a TypeError on a posted `k[]` list: `pg_set_password_token()`.

## What was read clean?
- The reset token: 16 random characters stored as SHA-256, 24 hours, cleared when used, replaced by a new request, cleared by a password or address change on the site; the link is built from `HOSTNAME_SETTING` and goes to the stored address only.
- Forgot password answers one sentence for every address, rate limited per address subject and per mailbox before the lookup (`pg_password_reset_guard()`).
- Every sign-in path goes through `pg_session_sign_in()`, which renews the session id and the form token; the second step renews it too.
- A disabled or deleted member: Edit User's switch and account deletion revoke the tokens, so the bound session ends on its next request; `validate_user()`, `pg_session_signed_in()` and `initialize_user()` refuse the row as well.
- `waf_sensitive_scripts()` counts both entrances and forgot password.

## What was left as it is?
- Sign-up and the e-mail address change say "already in use" for a taken address: the usual sign-up answer, not covered by the one-message rule of the sign-in screens (`core/sign-in-one-message.md`).
- A signed-in member changes the address on E-mail Preferences without typing the password, and the old address gets no notice; the session is the credential (`core/reset-link-revoked-on-change.md`).
- A session already open when an address is put on the block list runs on until it ends.

Related: docs/memory/workflow/bug-hunt.md
