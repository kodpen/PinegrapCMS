---
name: passkey-decisions
description: Lasting decisions of the passkeys work (approved 2026-10-19, built in 2026.11.0) and the memory file that holds each built piece
---

## What was decided for passkeys as a whole? (approved 2026-10-19)
- A small vendored verifier, no Composer; attestation never trusted, only the credential public key kept ([[webauthn-verifier]]).
- Two uses, both behind settings: a second factor wherever TOTP is one, the role-based "required from role" included ([[passkey-second-factor]]); and sign-in with a passkey alone, off by default ([[passkey-passwordless]]).
- Password sign-in and TOTP stay; nothing is removed or forced. Recovery codes stay the fallback.
- `/auth/login` is unchanged: team devices keep password + TOTP. The OAuth consent screen signs in with whatever the site offers.
- Relying party ID is the host alone (`HOSTNAME_SETTING`), also on a site in a sub-folder; the language prefix does not matter.
- Without TOTP, adding a passkey asks for the password and an e-mailed code (owner, 2026-10-27): [[passkey-without-totp]].

## Where are the later pieces recorded?
- Account page: [[passkey-account-section]] (#1384, #2226); designed widget: `designer/passkey-widget-list.md` (#1843, #2279); second factor: [[passkey-second-factor]] (#1862); passwordless and autofill: [[passkey-passwordless]] (#1882, #1960); sessions: [[passkey-sessions]] (#2250); e-mailed code and recovery codes without TOTP: [[passkey-without-totp]] (#2226, #2557, #2717).

## What is still open?
- Nothing planned. Later passkey work starts from these files; a change to a decision above goes to the architect first.
