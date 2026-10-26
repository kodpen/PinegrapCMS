---
name: passkeys
description: Plan (approved 2026-10-19; steps 1-4b done, 4c-4d left): WebAuthn passkeys as second factor or passwordless sign-in; designed account widget and the gate left
---

## Why?
- Sign-in offers password, Google/Microsoft/Apple and TOTP two-step (`includes/fn/mfa.php`, tables `user_mfa`, `user_mfa_recovery`); no WebAuthn anywhere. Craft CMS 5 ships panel passkeys; WordPress core has none. Passkeys resist phishing and remove TOTP friction for staff.

## What is decided?
- Library: a small vendored WebAuthn verifier under `includes/webauthn/` (no Composer), supporting `packed`/`none` attestation and ES256/RS256 assertions; attestation is not trusted (no roots), only the credential public key is kept, as most sites do.
- Table `user_passkeys` (2026.11.0, 11.0.13; columns and why in docs/memory/core/passkey-ceremonies.md). Challenges live in the PHP session (short TTL).
- Two uses, both settings-gated: (a) second factor: a passkey satisfies the two-step requirement wherever TOTP does, including the role-based "required from role" setting; (b) passwordless: the sign-in form shows "Sign in with a passkey" (conditional UI / autofill) when the site allows it; the first factor then is the passkey and the two-step requirement counts as satisfied.
- Where: Account Security widget for members and the panel account page for staff (`account_security` widget already lists sessions and connected sign-ins); the `edit_user.php` two-step reset removes passkeys too. Recovery codes stay the fallback.
- API `/auth/login` is unchanged (team devices keep password + TOTP); OAuth consent screen signs in with whatever the site offers.
- Password sign-in and TOTP remain; nothing is removed or forced.

## What is built?
- Step 1 (PR #594): the verifier and its tests, docs/memory/core/webauthn-verifier.md.
- Step 2 (PR #672): `user_passkeys` (11.0.13, with `alg`, `backup_eligible`, `backup_state`, `user_handle`), `includes/fn/passkeys.php`, `passkey.php` (registration, sign-in options), the passkey button and answer in `mfa.php` verify mode, `assets/js/pg_passkey.js`; docs/memory/core/passkey-ceremonies.md. Changed from the sketch: the sign-in answer is posted to the screen that owns the step (`mfa.php`), because completing a sign-in redirects; `pg_mfa_reset()` removes passkeys (admin reset, both deletes).
- Step 3 (PR #1384): the classic account page, registration behind re-authentication (only with TOTP on), the `passkeys` export section; docs/memory/core/passkey-account-section.md.
- Step 3b (PR #1843): the designed `account_security` widget, the `security.passkeys` list, the add form and its script; docs/memory/designer/passkey-widget-list.md. Design templates needed nothing: they take the starter tree.

- Step 4a (PR #1862): the gate counts a passkey; a passkey-only account gets `verify` mode, a passkey satisfies `mfa_required_role`: docs/memory/core/passkey-second-factor.md.
- Step 4b (PR #1882): passwordless button, `config.passkey_signin` (11.0.41, default off: owner, 2026-10-26, upgraded sites keep their sign-in screen): core/passkey-passwordless.md.

## What remains?
4c. Recovery codes for passkey-only accounts and adding a passkey without TOTP (waits on the open question below). (S)
4d. Conditional UI: passkeys in the username field's autofill (`mediation: 'conditional'`), same `index.php` answer. (S)
5. Memory: update `core/passkey-ceremonies.md` and `two-step-sign-in.md` with each step.

## Open questions?
- `rpId` on sites served from a sub-folder or several hosts: use the host only; the language prefix does not matter.
- Step 4: what an account without TOTP proves to add a passkey (adding needs TOTP on until then), and whether removing one then needs more than its password.

## Where to start?
- Step 4c: `core/passkey-account-section.md` (what adding asks for), `core/passkey-ceremonies.md` (what exists, refusal words), `two-step-sign-in.md` and `social-sign-in.md` for the session and remember-me rules.
