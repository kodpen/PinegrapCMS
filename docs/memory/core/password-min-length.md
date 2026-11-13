---
name: password-min-length
description: A chosen password needs 8 characters while Require Strong Password is off: pg_password_refusal() on sign-up, Set/Change Password, the installer
module: auth
---

## What is accepted?
- Require Strong Password on (`config.strong_password`): `validate_password_strength()` decides, with each screen's own sentence, as before ([[password-strength-js]]).
- Off: at least `pg_password_min_length()` characters, counted like `mb_strlen()` (an emoji or "ş" is one); message "The password must be at least {var:1} characters long.". No other rule.
- Before, with the setting off (its default) any length was accepted, one character included: "abc" was saved on the Set Password page.

## Why 8?
- The floor NIST SP 800-63B sets for a password a person chooses, and the first step of the advisory meter's scoring. Longer rules stay behind the strong password switch.

## Where is it asked?
- `pg_password_refusal($password, $strong, $strong_message)` (`includes/fn/auth.php`), '' when accepted: `pg_member_register()` (sign-up widget and screen), `pg_member_activate()` (membership; an empty password of a Google sign-up is not checked), `set_password.php`, `change_password.php`, and `install/index.php` for the administrator (`$strong` false: a new site starts with the switch off; the Administrator step checks the length in the browser too).
- Passwords nobody types (random ones of Add User, Import Users, reset) and sign-in are not checked. An existing shorter password still signs in.

Tests: `tests/password_min_length_test.php`.
