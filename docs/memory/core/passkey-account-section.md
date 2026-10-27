---
name: passkey-account-section
description: Passkeys on the account page (pg_passkey_account_section(), PR #1384, #2226): who may add one, the proofs incl. the e-mailed code, recovery codes of passkey-only accounts, rename and remove
---

## Where is it drawn?
- `pg_passkey_account_section()` (`includes/fn/passkeys.php`), after the two-step part in `pg_account_security_section()`: the My Account profile page of members and staff, default and custom layouts. Plain markup like `pg_mfa_account_section()`.
- The designed `account_security` widget lists them too: docs/memory/designer/passkey-widget-list.md (PR #1843).
- Says nothing before the upgrade, or where passkeys cannot work (`pg_passkey_ready()` `unavailable`) and the account has none; opened at another host name it gives the `host` sentence. Kept passkeys stay listed in both cases.
- While `logged_in_as_different_user`: the count only; `passkey.php` and `account_security.php` refuse changes then.

## Who may add a passkey, with what proofs?
- Any account, not past `PG_PASSKEYS_MAX` (the `limit` sentence). The form stays hidden until `PgPasskey.supported()`; otherwise "This browser cannot create passkeys."
- `pg_passkey_reauth_fields($algo, $mfa, true)`: the password unless algo 3, and with TOTP on a TOTP or recovery code (`code`); without TOTP a code e-mailed to the account (`email_code`; owner 2026-10-27, PR #2226: a stolen password alone must not add the thief's passkey).
- `register_options` runs `pg_mfa_reauth_refusal()` (`includes/fn/mfa.php`, as turning TOTP off): MFA attempt counter, password through the sign-in throttle, then the code; then `pg_passkey_email_code_check()`. Refusals `attempts`/`locked` 429 (Retry-After), `password`/`code`/`email_code`/`email_expired` 403, words from `pg_mfa_reauth_message()`, log `passkey not added (<code>)`. Only then is the `register` ceremony begun.
- A code is spent at `register_options`; a cancelled dialog needs the next one. A lockout-opening failure is an HTML 429 (`attempts` to `pg_passkey.js`).

- The e-mailed code and what an account without TOTP gets: [[passkey-without-totp]].

## How are rename and remove done?
- `account_security.php` actions `passkey_rename` (session only) and `passkey_remove`: form POSTs with the token and `passkey_id`, back to `pg_mfa_account_url()` (or a widget's return_to); refused proofs go to the `my_account_profile` form messages.
- Only a passkey of the signed-in account (`WHERE id AND user_id`); another id goes back silently before any proof is counted.
- Remove (`pg_passkey_remove_form()`, also beside each session it opened: [[passkey-sessions]]) asks for the proofs the account has (`pg_passkey_reauth_fields()`): the password unless algo 3, a code while TOTP is on; no e-mailed code (owner). An account with neither (Google-only, TOTP off) removes with its session.
- Log lines `user renamed a passkey (…)`, `user removed a passkey (…)`. The credential stays on the device; the form says to delete it there too.

## What was not verified?
- Sandbox 2026-10-23 (PR #1384): proofs, refusals and lockout through curl; no browser authenticator. PR #2226: unit tests only (no mail, no browser).

Related: docs/memory/core/passkey-ceremonies.md, docs/memory/core/two-step-sign-in.md, docs/memory/plans/passkeys.md
