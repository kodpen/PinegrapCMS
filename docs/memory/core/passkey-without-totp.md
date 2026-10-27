---
name: passkey-without-totp
description: Adding a passkey to an account without TOTP (PR #2226): the e-mailed code (passkey.php email_code), the recovery codes the first passkey brings, what removing the last one does
---

## Why an e-mailed code?
- Owner, 2026-10-27: without TOTP the password is the only proof, so a stolen password could add the thief's passkey. Adding asks for the password and a code mailed to the account's stored address; removing asks for the password only.

## How does the e-mailed code work?
- "Send the code" posts `email_code` to `passkey.php` (refused `mfa_on` with TOTP on): `pg_passkey_email_code_send()` mails six digits, sent now and marked secret, to the account's stored address; 5 an hour per account (`passkey-mail`), `email_none`, `email_limit` 429, `email_send` 500.
- Kept in `$_SESSION['software']['passkey_email_code']` as a SHA-256 hash: 10 minutes, one per session, used once, ended by the fifth wrong guess (tests: `tests/passkeys_test.php`).

## What changes when an account without TOTP adds one?
- The passkey becomes its second factor (`core/passkey-second-factor.md`). `pg_passkey_registered()`: the first one revokes the other remember-me tokens and API devices, as turning TOTP on does; with no unused recovery code the account gets ten, in the `register` answer, which `PgPasskey.showRecoveryCodes()` shows once in place of the form.
- Removing the last passkey of such an account removes its codes (`pg_passkey_remove()`).

Related: docs/memory/core/passkey-account-section.md, docs/memory/core/passkey-second-factor.md
