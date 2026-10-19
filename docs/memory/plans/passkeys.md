---
name: passkeys
description: Plan (approved 2026-10-19, in progress: verifier, table, endpoints and the mfa.php passkey step done): WebAuthn passkeys as second factor or passwordless sign-in; account screens, gate left
---

## Why?
- Sign-in offers password, Google/Microsoft/Apple and TOTP two-step (`includes/fn/mfa.php`, tables `user_mfa`, `user_mfa_recovery`); no WebAuthn anywhere. FIDO counts about five billion passkeys in 2026; Craft CMS 5 ships panel passkeys natively while WordPress core still has none. Passkeys are phishing-resistant and remove SMS/TOTP friction for staff who sign in daily.

## What is decided?
- Library: a small vendored WebAuthn verifier under `includes/webauthn/` (no Composer), supporting `packed`/`none` attestation and ES256/RS256 assertions; attestation is not trusted (no roots), only the credential public key is kept, as most sites do.
- Table `user_passkeys` (2026.11.0, 11.0.13; columns and why in docs/memory/core/passkey-ceremonies.md). Challenges live in the PHP session (short TTL).
- Two uses, both settings-gated: (a) second factor: a passkey satisfies the two-step requirement wherever TOTP does, including the role-based "required from role" setting; (b) passwordless: the sign-in form shows "Sign in with a passkey" (conditional UI / autofill) when the site allows it; the first factor then is the passkey and the two-step requirement counts as satisfied.
- Where: Account Security widget for members and the panel account page for staff (`account_security` widget already lists sessions and connected sign-ins); admin can remove a user's passkeys on `edit_user.php` like the TOTP reset. Recovery codes stay the fallback.
- API `/auth/login` is unchanged (team devices keep password + TOTP); OAuth consent screen signs in with whatever the site offers.
- Legacy: password sign-in and TOTP remain; nothing is removed or forced.

## What is built?
- Step 1 (PR #594): the verifier and its tests, docs/memory/core/webauthn-verifier.md.
- Step 2 (PR #NNN): `user_passkeys` (11.0.13, with `alg`, `backup_eligible`, `backup_state`, `user_handle`), `includes/fn/passkeys.php`, `passkey.php` (registration, sign-in options), the passkey button and answer in `mfa.php` verify mode, `assets/js/pg_passkey.js`; docs/memory/core/passkey-ceremonies.md. Changed from the sketch: the sign-in answer is posted to the screen that owns the step (`mfa.php`), because completing a sign-in redirects.

## What remains?
3. UI in Account Security widget and panel account (list, add with `PgPasskey.register()`, rename, remove), admin reset on `edit_user.php` (S). Rename and remove need endpoint actions. Adding asks for the current password first (as turning two-step off does): a stolen session must not plant a passkey, which becomes a first factor in step 4.
4. Sign-in form conditional UI (passwordless: `pg_passkey_signin_begin(0)`, answer posted to the sign-in screens), two-step integration: the account found by the passkey must still exist, an account with a passkey and no TOTP gets `verify` mode, a passkey satisfies `mfa_required_role`, recovery codes for passkey-only accounts (S–M).
5. Memory: update `core/passkey-ceremonies.md` and `two-step-sign-in.md` with each step.

## Open questions?
- `rpId` on sites served from a sub-folder or several hosts: use the host only; the language prefix does not matter.

## Where to start?
- Step 3; read `docs/memory/core/passkey-ceremonies.md` (what exists, refusal words) and `two-step-sign-in.md` (the account section `pg_mfa_account_section()`, `edit_user.php` reset rules). Step 4 also `social-sign-in.md` for the session and remember-me rules.
