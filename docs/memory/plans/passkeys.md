---
name: passkeys
description: Plan (approved 2026-10-19, not started): WebAuthn passkeys for panel users and members as a second factor or passwordless sign-in beside TOTP; registration in Account Security, admin reset
---

## Why?
- Sign-in offers password, Google/Microsoft/Apple and TOTP two-step (`includes/fn/mfa.php`, tables `user_mfa`, `user_mfa_recovery`); no WebAuthn anywhere. FIDO counts about five billion passkeys in 2026; Craft CMS 5 ships panel passkeys natively while WordPress core still has none. Passkeys are phishing-resistant and remove SMS/TOTP friction for staff who sign in daily.

## What is decided?
- Library: a small vendored WebAuthn verifier under `includes/webauthn/` (no Composer), supporting `packed`/`none` attestation and ES256/RS256 assertions; attestation is not trusted (no roots), only the credential public key is kept, as most sites do.
- Table `user_passkeys` (user_id, credential_id, public_key, sign_count, transports, aaguid, name, created_at, last_used_at), created by a 2026.11.0 migration step. Challenges live in the PHP session (short TTL).
- Two uses, both settings-gated: (a) second factor: a passkey satisfies the two-step requirement wherever TOTP does, including the role-based "required from role" setting; (b) passwordless: the sign-in form shows "Sign in with a passkey" (conditional UI / autofill) when the site allows it; the first factor then is the passkey and the two-step requirement counts as satisfied.
- Where: Account Security widget for members and the panel account page for staff (`account_security` widget already lists sessions and connected sign-ins); admin can remove a user's passkeys on `edit_user.php` like the TOTP reset. Recovery codes stay the fallback.
- API `/auth/login` is unchanged (team devices keep password + TOTP); OAuth consent screen signs in with whatever the site offers.
- Legacy: password sign-in and TOTP remain; nothing is removed or forced.

## What remains?
1. Verifier library + unit tests with fixed test vectors (M).
2. Migration step, registration/assertion endpoints (`passkey.php`), session challenge handling (S–M).
3. UI in Account Security widget and panel account, admin reset (S).
4. Sign-in form conditional UI, two-step integration in `includes/fn/auth.php` (S–M).
5. Memory: `docs/memory/core/passkeys.md`; update `two-step-sign-in.md`.

## Open questions?
- `rpId` on sites served from a sub-folder or several hosts: use the host only; the language prefix does not matter.

## Where to start?
- Step 1 and 2; read `docs/memory/core/two-step-sign-in.md` and `social-sign-in.md` for the session and remember-me rules.
