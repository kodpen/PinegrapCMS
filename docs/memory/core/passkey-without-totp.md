---
name: passkey-without-totp
description: An account without TOTP (PR #2226): the e-mailed code (passkey.php email_code) and its purposes, recovery codes from the first passkey and new ones later, removing the last one
---

## Why an e-mailed code?
- Owner, 2026-10-27: without TOTP the password is the only proof, so a stolen password could add the thief's passkey. Adding asks for the password and a code mailed to the account's stored address; removing asks for the password only.

## How does the e-mailed code work?
- "Send the code" posts `email_code` to `passkey.php` (refused `mfa_on` with TOTP on): `pg_passkey_email_code_send()` mails six digits, sent now and marked secret, to the account's stored address; 5 an hour per account (`passkey-mail`), `email_none`, `email_limit` 429, `email_send` 500.
- Kept in `$_SESSION['software']['passkey_email_code']` as a SHA-256 hash: 10 minutes, one per session, used once, ended by the fifth wrong guess (tests: `tests/passkeys_test.php`).
- Bound to a purpose (`pg_passkey_email_code_purpose()`): `add` by default, `recovery` when the request carries `purpose=recovery`; subject, body and log line name it. A code checked for the other purpose reads `email_expired` and stays; a code kept before purposes existed counts as `add`.

## What changes when an account without TOTP adds one?
- The passkey becomes its second factor (`core/passkey-second-factor.md`). `pg_passkey_registered()`: the first one revokes the other remember-me tokens and API devices, as turning TOTP on does; with no unused recovery code the account gets ten, in the `register` answer, which `PgPasskey.showRecoveryCodes()` shows once in place of the form.
- Removing the last passkey of such an account removes its codes (`pg_passkey_remove()`).

## How does such an account get new recovery codes?
- Decided in #2392: with the proofs of adding a passkey, the password (none for algo 3) and a code e-mailed for `recovery`. `pg_mfa_recovery_proof()` picks `totp` (TOTP on), `email` (`pg_mfa_has_passkey()` without TOTP) or nothing.
- `account_security.php` `mfa_recovery_regenerate`, `email` branch: skips the `pg_mfa_available()` refusal (codes are hashes, no key needed), keeps the MFA attempt counter, `pg_mfa_reauth_refusal()` for the password (`locked` → 429 page), then `pg_passkey_email_code_check(..., 'recovery')`; refusals go to the form messages; same log line `user created new two-step recovery codes`; the codes are shown by `pg_mfa_account_section()` from `mfa_codes_show`.
- The classic block sits in `pg_passkey_account_section()` before the `ready` and `limit` returns: remaining count, password, e-mailed code, Send the code (an inline fetch, not `PgPasskey`, so it works where the browser cannot make passkeys).
- The account security widget does not offer it yet (plan step 4c3b, its tree lives in `style_designer.js`).

Related: docs/memory/core/passkey-account-section.md, docs/memory/core/passkey-second-factor.md
