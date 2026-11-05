---
name: cross-site-json-requests
description: api.php and booking_slots.php refuse a browser's cross-site request (pg_request_cross_site()), because the session cookie can be SameSite=None after a checkout.
module: auth
---

## Why do token-less JSON endpoints check where the request came from?
- api.php's public site actions (`add_to_cart`, `get_shipping_methods`, the `site_chat_*` actions, …) and booking_slots.php's JSON actions (`hold`, `confirm`, `release`, `payment`) check no form token. They leaned on the Lax session cookie, which a cross-site POST does not carry.
- `pg_session_cookie_allow_cross_site()` re-issues the session cookie as `SameSite=None` on the checkout screens (and before an Apple `form_post` sign-in) for the gateway's return. It stays None until the id changes, so for the rest of that visit a page on another site could post a `text/plain` body (a form with `enctype="text/plain"` or a no-cors fetch) that decodes as an action: fill the visitor's cart, release or confirm their booking hold, write in their chat.

## What is refused?
- `pg_request_cross_site($_SERVER)` (`includes/fn/auth.php`): `Sec-Fetch-Site: cross-site` when the browser sends the header (`same-site`, a sibling subdomain, passes); otherwise an `Origin` whose host and port differ from `Host`, or the opaque `null`. No header at all is not cross-site (server-to-server clients).
- api.php answers 403 `{status: error, code: cross_site}` before any action, except a request that signs in with `username` and `password` (it brings its own account). booking_slots.php answers 403 `{error: request}` before reading its body; the deposit form post and the gateway return (GET `mode`) run before the check and keep their own token or return handling.
- Pinned in `tests/cross_site_request_test.php`.
