---
name: api-device-pairing
description: Pairing a team device with a QR code (device_pairing.php, POST /auth/pair, table api_device_pairings): code storage, who may make one, the exchange, limits, revocation
---

## How does pairing work?
- A person signed in to the panel opens `device_pairing.php` and presses "Show a pairing code": the answer to that POST draws a QR (`pg_qr_svg()`) holding `{"pinegrap":"pair","v":1,"api_base":…,"code":"pgpc_…"}` (`api_pairing_payload()`), not a URL. Sent with `Cache-Control: no-store`; a countdown, New code, Cancel.
- The team app sends the code to `POST /auth/pair` (`auth.pair`, public, 201 `AuthSession`, handler `api_auth_pair()` in `resources/account.php`) and gets the same session `/auth/login` gives. No password or `otp`: the person passed both on the site, so passkey and Google-only accounts can use the app.

## How is a code kept?
- `pgpc_` + 48 hex from `random_bytes()`; only `api_secret_hash()` (HMAC with `ENCRYPTION_KEY`) is stored in `api_device_pairings.code_hash` (table 11.0.70, decided #2470). 300 s, once, bound to the person and the device application active when it was made (`api_pairing_usable()`).
- A new code deletes the person's unused ones and every row a day old; Cancel deletes the unused ones. `api_devices_revoke_user()` / `_revoke_app()` delete pending codes too (`api_pairings_forget_*()`).

## Who may make one?
- `api_pairing_refusal()`: not while `logged_in_as_different_user` (no 30-day session as an impersonated person), not with no usable device application, not for an account `api_device_account_has_rights()` refuses. The screen uses `validate_user()` and the panel's own `$user` as the owner (the API's `api_load_owner()` is gated on `PG_API_ENTRY`).

## What does the exchange check?
- Shape, then the hash, then the code's own application (`api_device_app_check()`: switched off, expired, IP allow list) before the code is spent; the owner's rights; then a conditional UPDATE (`used_timestamp = 0 AND expires_timestamp > now`) lets one of two racing exchanges through.
- 401 `pairing_invalid` for unknown, used, expired or wrong-application codes (in `api_error_catalogue()`). Ten failures per address (`waf_ip_subject()`, counted addresses only) in 15 min → 429 with `Retry-After`, logged with `waf_log_event()`, no firewall offence. Ten codes per person per hour.
- Site Log in the person's name: "showed a device pairing code", "signed in from a device with a pairing code ({var:1})".

## Where is the screen reached from?
- The account menu's "Pair a device" row (`pg_account_menu()` in `includes/fn/output.php`), under Connected applications. It shows while a device application is active and not expired and `api_device_account_has_rights()` accepts the person; a cheap `api_apps` query comes first, so `scopes.php`/`devices.php` load only on sites with devices.

Tests: `tests/api_device_pairing_test.php`, the menu row in `tests/panel_leftovers_test.php`. Issue #1640.
