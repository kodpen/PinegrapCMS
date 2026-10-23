---
name: passkey-ceremonies
description: Passkeys of accounts: user_passkeys (11.0.13), includes/fn/passkeys.php, passkey.php, the passkey at the second step of mfa.php, the account page; what is not built yet
---

## What is built? (PR #672, passkeys step 2)
- Table `user_passkeys` (2026.11.0, 11.0.13): one row per credential. Binary values are base64url in `ascii_bin` columns; the unique key is `credential_hash` (SHA-256 hex of the raw ID), since an ID of up to 1023 bytes is past the 767-byte index of old row formats. `user_handle` is 32 random bytes drawn at the account's first passkey and copied to the next ones (never derived from site keys).
- Module `includes/fn/passkeys.php` around the verifier (`docs/memory/core/webauthn-verifier.md`): `pg_passkey_registration_begin()` / `pg_passkey_register()`, `pg_passkey_signin_begin($user_id)` / `pg_passkey_signin_check()`, `pg_passkeys_of_user()`, `pg_passkeys_forget()` (called by `pg_mfa_reset()`; `edit_user.php`'s delete now also calls `pg_mfa_reset()` as `delete_users.php` did, since MySQL 5.7 hands the highest id out again after a restart). Tests: `tests/passkeys_test.php` (software authenticator with the W3C ES256 test key).
- `passkey.php`: POST + session token, JSON only. `register_options` only for an account with TOTP on and after the password and a code (PR #TBD, step 3: `core/passkey-account-section.md`); `register` needs the ceremony only that begins; both refused while logged in as another user. `signin_options` serves a sign-in waiting in `mfa.php` verify mode.
- `mfa.php` verify mode shows "Use a passkey" when the account has one and the browser can (`assets/js/pg_passkey.js`). The answer is posted to `mfa.php` (`pg_mfa_action=passkey`), counts against `pg_mfa_attempt_blocked()` and completes through `pg_mfa_screen_complete()`.

## What removes an account's passkeys?
- `pg_mfa_reset()`: the administrator two-step reset on `edit_user.php` (after a suspected compromise an old passkey must not answer the second step once TOTP is set up again) and both account deletion paths (PR #672).
- The account page's remove, one at a time (`core/passkey-account-section.md`).
- The self-disable on the account page (`pg_mfa_disable()` after `pg_mfa_self_disable_check()`) keeps them, listed and removable: the person proved the password and a code, and passkeys become a first factor in step 4.

## Why is the sign-in answer posted to mfa.php, not passkey.php?
- Completing a sign-in redirects (device limit screen, send_to, home page); a JSON answer to fetch() cannot. The screen that owns the step takes the answer; passkey.php only hands out options and keeps registrations.

## How are ceremonies kept?
- `$_SESSION['software']['passkey_ceremonies'][purpose]`: challenge (base64url), account, time. One per purpose; a newer one replaces the older. Taken out before the answer is read, so any answer uses it up; refused after 360 s or for another account.
- Relying party: `URL_SCHEME` + `HOSTNAME_SETTING`, never the Host header. A page opened at another host answers `host` ("Passkeys work at … only"); an IP address or plain http (but localhost) is `unavailable`.

## What does a visitor see on a refusal?
- One sentence per site/session/list code (`unavailable`, `host`, `challenge`, `none`, `limit`, `duplicate`); every refusal of the credential itself reads "The passkey was not accepted." The code goes to the activity log (`access denied (passkey not accepted: …)`, `passkey not added (…)`).

## What is not built yet? (plans/passkeys.md)
- The designed `account_security` widget (step 3b); the classic account page has the list, add, rename and remove.
- The gate: an account with passkeys but no TOTP is not asked for a second step; a required role still sets up TOTP. Passwordless sign-in: `pg_passkey_signin_begin(0)` and the check already handle it (user handle required, UV required) but no screen offers it.
