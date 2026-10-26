---
name: passkeys
description: Plan (approved 2026-10-19): WebAuthn passkeys as a second factor and for sign-in with a passkey alone
---

## Why?
- Sign-in offers password, Google/Microsoft/Apple and TOTP two-step (`includes/fn/mfa.php`, tables `user_mfa`, `user_mfa_recovery`); no WebAuthn anywhere. Craft CMS 5 ships panel passkeys; WordPress core has none. Passkeys resist phishing and remove TOTP friction for staff.

## What is decided?
- Library: a small vendored WebAuthn verifier under `includes/webauthn/` (no Composer), supporting `packed`/`none` attestation and ES256/RS256 assertions; attestation is not trusted (no roots), only the credential public key is kept, as most sites do.
- Table `user_passkeys` (2026.11.0, 11.0.13; columns and why in docs/memory/core/passkey-ceremonies.md). Challenges live in the PHP session (short TTL).
- Two uses, both settings-gated: (a) second factor: a passkey satisfies the two-step requirement wherever TOTP does, including the role-based "required from role" setting; (b) passwordless: the sign-in form shows "Sign in with a passkey" (conditional UI / autofill) when the site allows it; the first factor then is the passkey and the two-step requirement counts as satisfied.
- Where: Account Security widget for members and the panel account page for staff (`account_security` widget already lists sessions and connected sign-ins); the `edit_user.php` two-step reset removes passkeys too. Recovery codes stay the fallback.
- Owner (2026-10-26): a password reset or change leaves the passkeys working; the member takes them back under My Account › Security (step 4e), the administrator with the two-step reset.
- API `/auth/login` is unchanged (team devices keep password + TOTP); OAuth consent screen signs in with whatever the site offers.
- Password sign-in and TOTP remain; nothing is removed or forced.

## What are the steps? (one PR each)
- 1 done (#594, core/webauthn-verifier.md).

- 2 done (#672, core/passkey-ceremonies.md): `user_passkeys`, `passkey.php`, the answer posted to `mfa.php` (the screen that owns the step, since completing a sign-in redirects); `pg_mfa_reset()` removes passkeys.

- 3 done (#1384, core/passkey-account-section.md): classic account page, registration behind re-authentication (only with TOTP on), the `passkeys` export section.

- 3b done (#1843, designer/passkey-widget-list.md): the designed `account_security` widget and its add form.

- 4a done (#1862, core/passkey-second-factor.md): the gate counts a passkey; a passkey satisfies `mfa_required_role`.

- 4b done (#1882, core/passkey-passwordless.md): passwordless button, `config.passkey_signin` (11.0.41, default off).

- 4c Recovery codes for passkey-only accounts and adding a passkey without TOTP (waits on the open question below). (S)

- 4d done (#1960, core/passkey-passwordless.md): passkeys in the email field's autofill on screens that only sign in.

- 4e Sessions and passkeys: a sign-in with a passkey records it on its session (`auth_tokens`); My Account › Security names the passkey beside the session and offers "Sign out and remove the passkey", and removing a passkey ends the sessions it opened. A synced passkey (iCloud, Google) is one credential on many devices: removing it stops them all. Needs a column (open question). (S)

- 5 Memory: update `core/passkey-ceremonies.md` and `two-step-sign-in.md` with each step.

## Open questions?
- `rpId` on sites served from a sub-folder or several hosts: use the host only; the language prefix does not matter.
- Step 4: what an account without TOTP proves to add a passkey (adding needs TOTP on until then), and whether removing one then needs more than its password.
- Step 4e: a column for a session's passkey (`auth_tokens.passkey_id`) needs the owner's approval.

## Where to start?
- Step 4c: `core/passkey-account-section.md` (what adding asks for), `core/passkey-ceremonies.md` (what exists, refusal words), `two-step-sign-in.md` and `social-sign-in.md` for the session and remember-me rules.
