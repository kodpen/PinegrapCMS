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
- Owner (2026-10-27, «4c önerin kabul»): an account without TOTP adds a passkey with its password **and** a one-time code e-mailed to the account's address, so a stolen password alone cannot add the thief's passkey; removing one needs the password only.
- Owner (2026-10-27, «4e ekle»): the column `auth_tokens.passkey_id` is approved (built in 11.0.46).
- API `/auth/login` is unchanged (team devices keep password + TOTP); OAuth consent screen signs in with whatever the site offers.
- Password sign-in and TOTP remain; nothing is removed or forced.

## What are the steps? (one PR each)
- Done: 1 (#594, core/webauthn-verifier.md), 2 (#672, core/passkey-ceremonies.md), 3 (#1384, core/passkey-account-section.md), 3b (#1843, designer/passkey-widget-list.md), 4a (#1862, core/passkey-second-factor.md), 4b (#1882) and 4d (#1960) (core/passkey-passwordless.md).

- 4c done (#2226, core/passkey-account-section.md): the classic account page adds a passkey without TOTP (password + e-mailed code); the first passkey of such an account gives it recovery codes, which `mfa.php` takes.

- 4e done (#2250, core/passkey-sessions.md): `auth_tokens.passkey_id` (11.0.46), the session row names its passkey, removing a passkey ends its sessions.

- 4c2 done (#2279, designer/passkey-widget-list.md): the widget adds a passkey without TOTP (e-mailed code), its device rows name the session's passkey.

- 4c3 done (#2557, core/passkey-without-totp.md): the classic account page gives an account with passkeys and no TOTP new recovery codes for the password and a code e-mailed for that purpose (decided in #2392).

- 4c3b New recovery codes in the account security widget for such an account: reuse the add form's Send the code with `purpose=recovery` and post `mfa_recovery_regenerate` with `current_password` and `email_code`. Waits for `style_designer.js` (the widget's tree), which other PRs hold. (S)

- 5 Memory: update `core/passkey-ceremonies.md` and `two-step-sign-in.md` with each step.

## Open questions?
- `rpId` on sites served from a sub-folder or several hosts: use the host only; the language prefix does not matter.

## Where to start?
- Step 4c3b: the account security widget's tree in `style_designer.js` and its renderer, `core/passkey-without-totp.md` (the server side is done).
