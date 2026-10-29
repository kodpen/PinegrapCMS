---
name: passkey-passwordless
description: Sign-in with a passkey alone (PR #1882): the switch config.passkey_signin (11.0.41, default off), the button among the sign-in buttons, passkey.php options, the answer handled by index.php
---

## How is it switched on?
- `config.passkey_signin` (2026.11.0, 11.0.41, `TINYINT DEFAULT 0`), read into `PASSKEY_SIGNIN` with `?? 0`; the switch sits in Settings › Security, two-step card, only once the column exists.
- Default off (owner, 2026-10-26): an upgraded site keeps its sign-in screen until a manager turns the button on.
- `pg_passkey_passwordless_enabled()`: switch on and `pg_passkeys_available()`.

## Where does the button show?
- `pg_passkey_signin_button()` leads `pg_signin_buttons()`, so every screen that prints the social buttons gets it: classic login, membership and registration screens, login and registration widgets, custom layouts.
- Those buttons sit inside the password form, so it is a `type="button"` carrying token, send_to, return_to (the current page) in data attributes. `assets/js/pg_passkey.js` shows it only when the browser can use passkeys, asks `passkey.php` (`signin_options`, `passwordless=1`), and posts the answer to `index.php` in a form it builds (`pg_passkey_signin=1`, credential, the surrounding form's `remember_me`).

## What does index.php check?
- `pg_passkey_passwordless_signin()`: token, cookies, banned IPs, 30 attempts per address in 10 minutes (`passkey-ip`), `pg_passkey_signin_check($input, 0)` (user handle and user verification required).
- The account must still exist and not be a member (role 3) whose address is blocked (`pg_passkey_passwordless_refusal()`, as `validate_login()` refuses the password).
- A refusal is logged with its code and shown as the plain "The passkey was not accepted." on the login form's `email` field, back on the page the button was on.
- Success completes as after the second step: `pg_device_limit_gate(..., true)`, device cookie, remember-me cookie, `pg_session_sign_in()`, order, `send_user_to_login_home()`. No code is asked: a passkey verified on the device is both factors.

## Does a password reset remove the passkeys?
- No (owner, 2026-10-26): a password reset or change leaves them working, also for sign-in with a passkey alone, since a passkey is a proof on its own; turning two-step off keeps them too. The member takes them back under My Account › Security, the reset channel (owner, same day; removing one ends its sessions: `core/passkey-sessions.md`), the administrator with the two-step reset (`pg_mfa_reset()`); account deletion removes them.

## Where does autofill offer passkeys? (PR #1960)
- On screens that only sign in (`pg_passkey_autofill_for()`: `pg_signin_buttons()` with `$signup` false or `'none'`: login screen, login widget), the button carries `data-autofill`; the script then adds `webauthn` to the end of the form's `email` field `autocomplete` and runs `credentials.get()` with `mediation: 'conditional'`. A pick posts the same answer to `index.php`. Screens that also register are left alone.
- The server keeps one sign-in ceremony per session: a button click aborts the autofill request and waits for its options request to settle before asking for its own, or the autofill's later answer would replace the button's ceremony.
