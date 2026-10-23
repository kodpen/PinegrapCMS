---
name: passkey-account-section
description: Passkeys on the account page (pg_passkey_account_section(), PR #TBD): who may add one, the re-authentication in passkey.php, rename and remove in account_security.php, what is shown when
---

## Where is it drawn?
- `pg_passkey_account_section()` (`includes/fn/passkeys.php`), after the two-step part in `pg_account_security_section()`: the My Account profile page of members and staff, default and custom layouts. Plain markup like `pg_mfa_account_section()`.
- Not in the designed `account_security` widget yet (plan step 3b).
- Says nothing before the upgrade, or where passkeys cannot work (`pg_passkey_ready()` `unavailable`) and the account has none; opened at another host name it gives the `host` sentence. Kept passkeys stay listed in both cases.
- While `logged_in_as_different_user`: the count only; `passkey.php` and `account_security.php` refuse changes then.

## Who may add a passkey?
- Only an account with TOTP on (`pg_mfa_enabled()`): in this version a passkey only answers the second step in `mfa.php` verify mode, which only accounts with TOTP are asked. Otherwise the section says to turn it on first, and `register_options` answers 403 `mfa_off`.
- Not past `PG_PASSKEYS_MAX` (the `limit` sentence). The form stays hidden until `PgPasskey.supported()`; otherwise "This browser cannot create passkeys."

## How does adding re-authenticate?
- `register_options` takes `current_password` (none for algo 3, Google-only) and `code` (TOTP or recovery) and runs `pg_mfa_reauth_refusal()` (`includes/fn/mfa.php`), the check that turning TOTP off uses: MFA attempt counter, then the password through the sign-in throttle (`pg_login_throttle_locked()`, `pg_login_record_failure()`), then the code. Refusals: `attempts`, `locked` 429 (`locked` with Retry-After), `password`, `code` 403; words from `pg_mfa_reauth_message()`; log `passkey not added (<code>)`.
- Only then is the `register` ceremony begun; `pg_passkey_register()` refuses without it, so a stolen session cannot plant a passkey that answers the second step.
- The code is spent at `register_options`: a cancelled browser dialog needs the next code (the script empties the field). Success reloads the page.
- The failure that opens a sign-in lockout is answered by `pg_login_record_failure()` with the HTML error page (429), not JSON; `pg_passkey.js` reads a non-JSON 429 as `attempts`.

## How are rename and remove done?
- `account_security.php` actions `passkey_rename` (session only) and `passkey_remove`: form POSTs with the token and `passkey_id`, back to `pg_mfa_account_url()` (or a widget's return_to); refused proofs go to the `my_account_profile` form messages.
- Only a passkey of the signed-in account (`WHERE id AND user_id`); another id goes back silently before any proof is counted.
- Remove asks for the proofs the account has (`pg_passkey_reauth_fields()`): the password unless algo 3, a code while TOTP is on. An account with neither (Google-only, TOTP off) removes with its session: its passkeys answer no sign-in step in this version.
- Log lines `user renamed a passkey (…)`, `user removed a passkey (…)`. The credential stays on the device; the form says to delete it there too.

## What did the sandbox show (2026-10-23)?
- MariaDB, http://localhost: `mfa_off` without TOTP; wrong password, wrong or spent code (TOTP and recovery) refused; options after both proofs; 5th wrong code `attempts`; the lockout-opening failure an HTML 429, the next a JSON `locked` with Retry-After. Rename and remove of another account's id change nothing; escaped names; export JSON and PDF. No browser authenticator: the ceremony itself was not run.

Related: docs/memory/core/passkey-ceremonies.md, docs/memory/core/two-step-sign-in.md, docs/memory/plans/passkeys.md
