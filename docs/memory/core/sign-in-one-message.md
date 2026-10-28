---
name: sign-in-one-message
description: The sign-in screens give one message for an unknown name and a wrong password, and an unknown name costs the same password_verify() (pg_login_refused_message(), pg_password_dummy_verify())
module: auth
---

## What does a refused sign-in show?
- `index.php`, `registration_entrance.php`, `membership_entrance.php` and `change_password.php` mark one error on the password field: `pg_login_refused_message()` ("The email address, username or password you entered is incorrect…", plus the forgot-password hint when `FORGOT_PASSWORD_LINK` is on; `change_password.php` says "current password", no hint). The name the visitor typed stays in its field, the password is cleared.
- Before this, an unknown name got "could not be found" on the name field, so the screens told an attacker which accounts exist (owner decision 2026-10-28, `plans/security-findings-2026-10.md` item 5).

## Does the site log still say which half was wrong?
- Yes: `validate_username()` still picks the log line ("password invalid" or "email or username invalid") for staff. Only the visitor's message is shared.

## Why does an unknown name take as long as a wrong password?
- `validate_login()` used to return at once when no row matched, skipping the hash, so the reply time showed the account existed. Now it calls `pg_password_dummy_verify()` there, on a blocked address, and `pg_password_verify()` calls it for an account without a password (algo 3, empty column).
- `pg_password_dummy_hash()` is a fixed bcrypt hash at the cost `password_hash()` uses on the running PHP (10; 12 from PHP 8.4), the cost `password_needs_rehash()` keeps stored hashes at. The dummy always refuses, whatever is typed.
- A legacy MD5 row (algo 0) is still quicker than bcrypt until its first sign-in upgrades it.

## What stays as it was?
- Lockouts and the question screen (`pg_login_throttle_guard()`, `pg_login_captcha_gate()`, `pg_login_record_failure()` before the message), WAF counters, MFA, remember-me, passkeys.

## Tests?
- `tests/login_refusal_test.php`: the message names neither half, the hint, the dummy hash's cost matches `password_hash()` on this PHP, the dummy never accepts.
