---
name: api-php-credentials-in-body
description: api.php accepting username/password in the request body: API_USERNAME vs API_AUTHENTICATED, CSRF skip, remember-me, login throttle, WAF rate buckets.
---

## Why does api.php accept username and password in the body?
- Not for the panel's XHR: `migration.php` connects to another installation's `api.php` this way (see `send_api_request()`); `barcode_*_inventory.php` uses the same path. `integration.php` (key/secret) is the modern equivalent; the `api.php` path stays because it is the transfer screen's only connection method.

## Which two constants must not be confused?
| Constant | Meaning |
|---|---|
| `API_USERNAME` | a username was SENT in the body |
| `API_AUTHENTICATED` | the password was VERIFIED (defined only by `initialize_user()`) |
- `validate_token()` skips the CSRF token ONLY if `API_AUTHENTICATED`. A check on `API_USERNAME` would let another site bypass the token with a made-up name in the body: `api.php` reads the body from `php://input`, so `Content-Type` is no obstacle, an `enctype="text/plain"` form suffices and no preflight request occurs.
- For the same reason `initialize_user()`'s remember-me branch never runs on a request carrying `API_USERNAME`: a request stating its identity in the body is answered by that alone, not by a cookie.

## How are password attempts counted?
- With `pg_login_throttle_guard()` / `pg_login_record_failure()`, same bucket as the login screens. A new endpoint accepting a password MUST add the counter, else the login screen's lock is bypassed through it. `pg_login_throttle_deny()` returns JSON when `API_USERNAME` is set.

## How does the WAF rate-limit these endpoints?
- Both ends are outside `waf_handle_rate_sensitive()` because each has its OWN bucket: `integration.php` per app (`includes/api/ratelimit.php`), `api.php` per address (`waf_rate_limit_api`, `waf_handle_rate_api()`).
- `waf_rate_limit_sensitive` is a login/payment measure and does not fit `api.php` (an open chat window alone sends 30 requests/minute). Bot-class exemption stays in both: migration calls come from another server.
- Adding a new visitor endpoint to `api.php` needs nothing for rate: the bucket is tied to the endpoint, not the action name. An action list would silently drop any unlisted new store action to 30/min.
