---
name: storefront-api-customer-token
description: Storefront API design note: the customer token (auth_tokens row of kind customer, short access token, rotated refresh), how it differs from team-device tokens, and the auth_tokens.kind schema proposal
---

## What is a customer token?
- A row in `auth_tokens` (selector, `validator_hash`, `user_id`, `expires_at`, address, agent) with `kind = 'customer'`, written by `POST /storefront/customer/sign-in` or `sign-up`. The answer holds `customer_token` (access, 1 h) and `refresh_token` (30 days, rotated on every refresh, old one honoured 60 s like `api/api-device-refresh-rotation.md`).
- Access token: `cu1.<selector>.<exp>.<mac>`, HMAC with ENCRYPTION_KEY; checked by MAC, expiry and one primary-key read that the row still exists (revocation works at once). Refresh: `selector:validator`, the website cookie's own scheme.
- Sent in `Pinegrap-Customer-Token`, on top of the application's credentials (`storefront-api-callers.md`).

## Who may get one?
- Any site member (role 3) and staff, through the website's door: `pg_mfa_gate()` (sign-in throttle, password, `otp`), the e-mail confirmation and blocked-address rules of the sign-up form. Passkey and Google sign-in are later steps of the auth slice (they need a browser ceremony).
- At most 10 customer rows per person; the least recently used gives way.

## How does it differ from a team-device token?
- `/auth/login` gives an `api_devices` row bound to a Team devices application, only to panel accounts, holding staff scopes capped by the device app; a member gets no token there (`api/api-device-sessions.md`). That stays as it is.
- A customer token holds no scope: the application's `storefront:*` scopes decide the routes, the token only names the shopper. It never opens a panel route, never holds `account:*`, and a staff member's customer token is a shopper's token.
- Revocation is free: `pg_auth_token_revoke_user()` already deletes every `auth_tokens` row of the person (password change, sign out everywhere, account deletion).

## Schema proposal (approved when the auth slice starts)
- `install_add_column('auth_tokens', 'kind', "VARCHAR(16) CHARACTER SET ascii NOT NULL DEFAULT 'web'")` and `install_add_index('auth_tokens', 'idx_user_kind', "INDEX idx_user_kind (user_id, kind)")` in the open version's migration, under that day's free API label. Existing rows become `web`: running sites see no change.
- VARCHAR, not ENUM: a later kind needs no MODIFY on a table every request reads. The index serves the per-person count and eviction by kind; `idx_user` stays for the revoke-all path.

## Which readers must add `kind = 'web'`?
- The cookie sign-in lookup (`includes/authentication.php`), else a customer refresh token pasted as a cookie signs in on the website.
- The member's own session list (`includes/fn/widgets_account.php`): customer tokens get their own "apps" rows there, removable.
- `waf_address_has_staff_sign_in()` (`waf.php`), `view_waf_log.php` and the firewall settings' address read: an agent platform's address must never be spared a ban because a staff member shopped through it.
- The device-lock limit and the pinned-session rules (`change_password.php`, `security.save.php`, passkey revokes) count web sessions only.

Related: plans/storefront-api-cart-token.md, plans/storefront-api-routes-customer.md
