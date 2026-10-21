---
name: api-device-refresh-rotation
description: Refresh-token exchange (api_device_rotate_pair, devices and OAuth): next pair derived from the presented token so concurrent refreshes agree; conditional UPDATE; 60 s grace
---

## What does a refresh do?
- `POST /auth/refresh` (devices) and the OAuth token endpoint (`includes/oauth/endpoints.php`) both call `api_device_rotate_pair()` in `includes/api/devices.php`. The row keeps HMACs only (`access_hash`, `refresh_hash`, `refresh_prev_hash`), keyed with `ENCRYPTION_KEY`.
- The presented token is looked up as current first, then as previous. As previous within `api_device_refresh_grace()` (60 s) it is exchanged again; after that the device is revoked (a used token seen again means a copy exists).

## Why is the new pair derived, not random?
- Symptom (#855): an app whose calls all hit an expired access token sent the same refresh token 3-4 times at once; every request returned 200 with a different pair and only the last written one worked. `GET /auth/me` gave 401, 401, 401, 200 and the other refresh tokens gave "Yenileme jetonu geçerli değil", so the app was signed out depending on which answer it kept.
- Now `api_device_token_next($kind, $refresh_token, ENCRYPTION_KEY)` gives `pgrt_`/`pgat_` + the first 48 hex of `HMAC-SHA256("pg-device-next-<kind>\n" . trim(token))`. Every exchange of one token gives the same pair, so concurrent requests and a retry after a dropped answer all hold the pair on the row. Without the site key the next token cannot be computed from the last.
- Sign-in (`api_device_issue()`) still draws random tokens; only exchanges are derived.

## Why is the UPDATE conditional?
- `WHERE id = … AND refresh_hash IN (<presented>, <derived next>)`. A request that read the row before a concurrent exchange and the app's next exchange (R0 → R1 → R2) would otherwise write R1 back over R2. It now writes nothing and answers R1, which is the previous token and still works for the grace period (its exchange gives R2 again).
- Do not check affected rows: MySQL counts a row whose values did not change as 0, and two writes in one second set identical values.

## Where is it tested?
- `tests/api_devices_test.php`: same token gives the same pair, shape matches `api_device_token_new()`, depends on kind, token and key. The key is passed in because the test runner has no `ENCRYPTION_KEY` (`tests/privacy_test.php` asserts that).
