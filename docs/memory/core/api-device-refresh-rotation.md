---
name: api-device-refresh-rotation
description: Refresh-token exchange (api_device_rotate_pair, devices and OAuth): next pair derived from the presented token so concurrent refreshes agree; conditional UPDATE; 60 s grace from the first exchange
---

## What does a refresh do?
- `POST /auth/refresh` (devices) and the OAuth token endpoint (`includes/oauth/endpoints.php`) both call `api_device_rotate_pair()` in `includes/api/devices.php`. The row keeps HMACs only (`access_hash`, `refresh_hash`, `refresh_prev_hash`), keyed with `ENCRYPTION_KEY`.
- The presented token is looked up as current first, then as previous. As previous within `api_device_refresh_grace()` (60 s) it gets the same pair again; after that the device is revoked (a used token seen again means a copy exists).
- The grace period counts from the first exchange of that token (`refresh_rotated_at`). Replays inside it do not restart it.

## Why is the new pair derived, not random?
- Symptom (#855, fixed in PR #1036): an app whose calls all hit an expired access token sent the same refresh token 3-4 times at once; every request returned 200 with a different pair and only the last written one worked. `GET /auth/me` gave 401, 401, 401, 200 and the other refresh tokens were rejected, so the app was signed out depending on which answer it kept.
- Now `api_device_token_next($kind, $refresh_token, ENCRYPTION_KEY)` gives `pgrt_`/`pgat_` + the first 48 hex of `HMAC-SHA256("pg-device-next-<kind>\n" . trim(token))`. Every exchange of one token gives the same pair, so concurrent requests and a retry after a dropped answer all hold the pair on the row.
- Sign-in (`api_device_issue()`) still draws random tokens; only exchanges are derived.

## Why is the UPDATE conditional?
- `WHERE id = … AND refresh_hash IN (<presented>, <derived next>)`. A request that read the row before a concurrent exchange and the app's next exchange (R0 → R1 → R2) would otherwise write R1 back over R2. It now writes nothing and answers R1 with an access token that is no longer on the row; R1 is the previous token and exchanges to the current pair R2 within grace.
- `refresh_rotated_at = IF(refresh_hash = <derived>, refresh_rotated_at, now)` keeps the first exchange time. It comes before `refresh_hash` in the SET list because single-table UPDATE assignments are evaluated left to right (placed after, it would see the new hash). Before, every replay reset the time: with a derived pair, a copied token replayed every < 60 s stayed alive and received the app's own pair, and the revocation never fired.
- Do not check affected rows: MySQL counts a row whose values did not change as 0, and two writes in one second set identical values.

## What are the trade-offs?
- The next token is only as unpredictable as `ENCRYPTION_KEY` is secret: anyone with the key and an old refresh token of a device can compute the later ones. That person has already read `config.php`.
- A token rotated by the earlier random-pair code within 60 s before the upgrade gets, on replay, a derived pair that is not on the row, so that app signs in once.

## Where is it tested?
- `tests/api_devices_test.php`: same token gives the same pair, shape matches `api_device_token_new()`, depends on kind, token and key. The key is passed in because the test runner has no `ENCRYPTION_KEY` (`tests/privacy_test.php` asserts that). The UPDATE needs a database and is not unit-tested.
