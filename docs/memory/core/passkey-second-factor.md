---
name: passkey-second-factor
description: A passkey alone is a second factor (PR #1862): pg_mfa_has_passkey/has_factor/gate_mode, the passkey-only verify screen, what still looks at TOTP only
---

## When does a passkey count as the second factor?
- `pg_mfa_has_passkey()`: the account keeps one and `pg_passkeys_available()` (table, relying party). On a site that cannot run a ceremony (plain http, IP address) it does not count, so the account is not locked out behind a passkey it cannot use; a TOTP key still counts there.
- `pg_mfa_gate_mode()`: TOTP or passkey → `verify`; neither and the role requires one → `setup`. The same "has a factor" test (`pg_mfa_has_factor()`) is used by the remember-me gate in `initialize_user()`, `device_limit.php`, `pg_post_password_signin()` and the token drop when `mfa_required_role` is raised.
- `mfa.php` verify mode without TOTP shows the passkey button, a recovery code field while the account has unused codes (PR #2226: given with its first passkey, kept by `pg_mfa_disable()` while a passkey remains; `pg_mfa_verify_code()` takes only recovery codes then), and Cancel; a browser without passkeys sees "Your browser could not use a passkey here."
- Password-only requests count a passkey too (`pg_mfa_has_factor()`): api.php's username and password sign-in in `initialize_user()` answers `mfa_required`, and `shipworks.php` refuses the account. Otherwise a role that requires two-step, satisfied by a passkey and never set up with TOTP, was let in by the password alone (#1862).
- The user screen (`edit_user.php`) shows two-step on for a passkey-only account and offers its reset (`pg_mfa_reset()` also removes passkeys and codes) for a lost passkey with no codes left.
- Not changed: `/auth/login` looks at TOTP only (team devices keep password + TOTP).

## Why does an unavailable site not count passkeys?
- Before #1862 an account with passkeys and TOTP turned off signed in with the password alone, and a required role forced TOTP setup even with a passkey. Counting a passkey the site cannot ask for would lock the account out with no codes left, so availability is part of the test.
