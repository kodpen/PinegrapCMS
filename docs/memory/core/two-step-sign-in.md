---
name: two-step-sign-in
description: Two-step sign-in (TOTP + recovery codes, 2026.4.8 step 8.40): where the gate sits in every sign-in path, storage, API and ShipWorks behaviour, recovery, encryption key risk
---

## What is built?
- Authenticator-app codes (RFC 6238, own code, no library) and 10 one-time recovery codes: `includes/fn/mfa.php` (pure parts tested in `tests/mfa_test.php`), screen `mfa.php` (modes `verify` and `setup`).
- Tables `user_mfa` (encrypted `totp_secret`, `last_step` against replay, `pending_secret` during setup) and `user_mfa_recovery` (SHA-256 hashes, `used_at`); `config.mfa_required_role` (99 = nobody required), constant `MFA_REQUIRED_ROLE`, card in Settings › Security.
- `pg_mfa_table_exists()` probes both tables by exact name; before the upgrade callers treat it as off. `pg_mfa_available()` also needs `ENCRYPTION_KEY` and openssl.
- Pre-build audit: `git show 88e7ac6d:docs/_tespit_2026_10_08/2fa_kesif_raporu.md`
- Not built: e-mail codes at sign-in, trusted devices. QR: inline SVG from `includes/fn/qr.php` (qr-code-generator.md).
- A passkey also answers verify mode and alone is a factor: passkey-second-factor.md.
- `pg_mfa_reauth_refusal()`: the account page's proof check (TOTP off, passkey add/remove).

## Where does the gate sit?
- `pg_mfa_gate($user_id, $username, $send_to, $remember, $origin)`: after the password (or Google) was accepted and the throttle cleared, BEFORE `pg_device_limit_gate()` and before any remember-me token. A token minted earlier would sign the browser in on its next request through `initialize_user()` with no code.
- Callers: `index.php`, `membership_entrance.php`, `registration_entrance.php`, `signin.php` (Google and other providers; a provider is not a second factor). `device_limit.php` re-checks via `mfa_passed` in its pending record. The remember-me branch of `initialize_user()` gates too: a remembered device of a covered account without a factor is sent to setup and its token revoked; raising `mfa_required_role` drops the unpinned tokens of newly covered accounts without one (not the operator's own).
- The gate regenerates the session id, stores `$_SESSION['software']['mfa_pending']` and redirects to `mfa.php`; `sessionuserid` is not set until the code passes.
- `change_password.php` opens no session unless the browser is already signed in as that account; `set_password.php` signs in only through `pg_post_password_signin()`, which refuses accounts with a second factor.
- New account paths (`custom_form.php`, `submit_order.php`, member register/activate) need no gate.

## What do password-based API paths do?
- `/auth/login` (team devices): `otp` field; missing → 401 `mfa_required`, required-but-not-set-up → 403 `mfa_setup_required`; code attempts share `pg_mfa_attempt_blocked()` with `mfa.php` (429 + Retry-After 600).
- `api.php` with `API_USERNAME`/`API_PASSWORD` (barcode screens): defines `API_MFA_REQUIRED`, no user is loaded, answer `mfa_required`.
- `shipworks.php`: refuses accounts with a second factor (plain English XML error).
- A right password with a missing code is not counted as a failed password.
- The install screen lock (`install/index.php`) stays password only; `developer_lock.php` (global PIN) is not a second factor.

## How is an account recovered?
- Recovery codes; `edit_user.php` "reset two-step verification" (`pg_mfa_reset`, also removes passkeys): own account or a lower role (administrators: any); a requiring role sets it up again at next sign-in.
- While logged in as another user (`logged_in_as_different_user`) the account screen cannot change the second factor.
- Last resort: delete the account's `user_mfa` row.

## What breaks it?
- Resetting the encryption key (Settings › Commerce) re-encrypts only card numbers; every TOTP key becomes unreadable and those accounts need a reset. The settings screen warns when `user_mfa` has enabled rows.
- Turning the second factor on or off (`pg_mfa_revoke_other_sessions()`) drops the account's other remember-me tokens and all its API devices; this browser keeps its token.
