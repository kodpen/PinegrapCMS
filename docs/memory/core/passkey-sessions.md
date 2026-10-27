---
name: passkey-sessions
description: Sessions opened with a passkey (PR #2250): auth_tokens.passkey_id (11.0.46), the sign-in mark, the session row on the account page, removing a passkey ends its sessions
---

## How does a session know its passkey?
- `auth_tokens.passkey_id` (2026.11.0, 11.0.46, `INT UNSIGNED NOT NULL DEFAULT 0`, `idx_passkey`; owner approved 2026-10-27). 0 for every other sign-in. Probe: `pg_auth_tokens_have_passkey()` (upgrade bridge).
- `pg_passkey_signin_check()` leaves a mark on success (`$_SESSION['software']['signin_passkey']`, `pg_passkey_signin_mark()`): the second step in `mfa.php` and the passwordless sign-in both pass through it. `pg_login_set_device_cookie()` takes it with `pg_passkey_signin_marked()` when it mints the token and writes the id on the row.
- Why a session mark and not a parameter: the device limit screen can come between the check and the mint (`device_limit.php` mints a request later). The mark is for one account, taken by any mint of that account, and ignored after 10 minutes, so a sign-in given up at that screen never names the passkey on a later password sign-in (`tests/passkey_sessions_test.php`).

## What does the account page show?
- `pg_account_security_section()`: a session row opened with a passkey says "Signed in with the passkey …" and offers "Sign out and remove the passkey": `pg_passkey_remove_form()` (also under each passkey), the proofs of removing (`core/passkey-without-totp.md`). Not offered while signed in as another user.
- The designed widget's device rows do not show it yet (step 4c2).

## What ends with a passkey?
- `pg_passkey_remove()` → `pg_passkey_sessions_end()`: its tokens go, so the sessions bound to them end (this browser too when it signed in with it). A synced passkey is one credential on all devices of its cloud account: they all go. Locked (pinned) sessions stay, as with "sign out of all devices".
- `pg_passkeys_forget()` (two-step reset, account deletion) ends the sessions of all the account's passkeys.

## What was not verified?
- Unit tests only; the step ran in CI's install and upgrade job, not twice by hand (no database in the session).

Related: docs/memory/core/passkey-account-section.md, docs/memory/core/passkey-passwordless.md
