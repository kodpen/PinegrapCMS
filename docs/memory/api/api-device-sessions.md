---
name: api-device-sessions
description: The three external API callers (application key, team devices session, OAuth connection): who the owner is, which scopes each holds, public token routes, account scopes, owner rights, revocation
---

## Which callers are there? (devices 2026.4.5, OAuth 2026.4.10)
- Application key: HTTP Basic (key + secret), a server's integration, calling as the account that owns the key.
- Team devices session: `POST /auth/login` (password, `otp`) gives a Bearer access token (1 h) and a refresh token (30 days, replaced on every `POST /auth/refresh`, the old one honoured 60 s); it calls as the person who signed in (`includes/api/devices.php`). The operator's switch and ceiling is an `api_apps` row of `kind = 'device'`.
- OAuth connection: an outside application a person approved on the consent screen (`api-oauth-server.md`), also Bearer.
- Both Bearer kinds are `api_devices` rows (`oauth_client_id > 0` for a connection): `api_current_device()` is non-null for both, `ws_api_device_writes_as_person()` tells them apart.

## What does a handler read?
- `api_current_app()['owner']`: the person signed in on the device or connection, the key's owner otherwise. Code that means "the caller" reads `$app['owner']`, never a fixed owner; Workspace, ERP cash and audit code then follow the right person by themselves. `$app['name']` becomes "username (device)" in logs.
- `api_load_owner()` turns the three yes/no rights (`manage_ecommerce`, `manage_contacts`, `manage_forms`) into booleans: a raw `'no'` is true under `==`. Other `manage_*` columns (module rights, TINYINT) are read raw by shape (`api_owner_permission_columns()`): test them with `!empty()`.

## Which scopes does each hold? (computed on every call, never stored)
- Key: its scopes ∩ what the owner may delegate (`api_effective_scopes()`).
- Device: the device app's ceiling ∩ owner, never `webhooks:manage`, always `account:read` and `account:write` (`api_device_scopes()`).
- Connection: consented ∩ OAuth ceiling ∩ owner (`pg_oauth_effective_scopes()`): no `webhooks:manage`, no automatic `account:*`.
- A person's own endpoints (notifications, devices, push) ask `account:read`/`account:write`; a server application holds them only when the operator grants them.

## What do routes for devices need?
- `'public' => true` belongs only to the token routes (`auth.login`, `auth.refresh`, `auth.logout`): such a route skips authentication, the rate bucket, the scope and Idempotency-Key.
- The OpenAPI security schemes (`api_openapi_security_schemes()`) describe the three callers in the words of the person-only 403s; the Bearer scheme keeps the name `deviceToken`, which generated clients refer to.
- `/auth/login` follows the website's door (`pg_mfa_gate()`): sign-in throttle and password first, then `otp` (401 `mfa_required`) or 403 `mfa_setup_required` (`two-step-sign-in.md`). An account with no right beyond its own account (a site member) gets no token.

## How does a session end?
- Every path that ends a person's sessions (password change or reset, sign out everywhere, account deletion, an e-mail address blocked in the firewall settings) goes through `pg_auth_token_revoke_user()`, which calls `api_devices_revoke_user()`; turning two-step verification on or off uses `pg_mfa_revoke_other_sessions()`, which does the same. Devices and OAuth connections end with them; a new such path calls one of the two.
- At most 20 devices per person per application; the least recently used gives way.

## Which notifications does an API caller see? (#2281)
- `pg_notification_visible()` with `api_notifications_rights()`, whose `orders_read` says the call's scopes hold `orders:read`: without it `new_order` and `order_return` leave the list, every unread count (`/auth/me`, `/workspace/sync` too) and the read targets. A device without Orders listed notices whose order answered 403.
- The panel and push sender pass no such key: unchanged. `out_stock` follows the e-commerce right alone.
