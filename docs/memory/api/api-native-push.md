---
name: api-native-push
description: Native push for team devices (M7a #9703, M7b): /push/devices routes, the broker call, push_subscriptions.provider (11.0.215), PG_PUSH_BROKER_URL, the badge body, 429 hold, forget on revoke
module: push_broker
---

## What does it do?
- A team device sends its FCM or APNs token to `POST /push/devices` (`provider` fcm|apns, `token`, `bundle_id`; `account:write`, X-Dry-Run, 201 `{"registered": true}`). The site trades it at the push broker for an https endpoint and keeps only that endpoint as a `push_subscriptions` row: `provider` 'fcm'/'apns', `auth_selector` `api:<device id>`. The token is never stored.
- The broker gives the same endpoint for the same site and token, so a repeat updates the row (`pg_push_subscription_save()`, ON DUPLICATE KEY).
- `POST /push/devices/delete` tells the broker to forget each row of this device with a provider, then deletes it, whatever the broker answers: `{"registered": false}`.
- Only a team devices session: an application key or an OAuth connection (`oauth_client_id > 0`) gets 403 (`api_push_device_load()`, `includes/api/resources/account.php`).

## When does it answer 503?
- While `PG_PUSH_BROKER_URL` (`includes/fn/push_broker.php`) is empty, the site has no VAPID key, or step 11.0.215 has not run. The constant stays empty until the broker is live; `data/config.php` may define it first for a test broker (https only, `pg_push_broker_base()`).
- `GET /meta` `features.native_push` is `pg_push_broker_ready()`: the same three conditions. `/auth/me` `features.push` stays web push readiness.

## How is the broker called?
- Only through `pg_push_broker_call($action, $payload, $send = null)`: register is `POST {broker}/v1/endpoints` (provider, token, bundle_id; answer 200/201 `{"endpoint", "expires_at"}`), forget is `POST {broker}/v1/endpoints/delete` (`endpoint`). The contract is Kodpen's (#9607's proposal).
- Headers (`pg_push_broker_headers()`, pure): `Authorization: vapid t=…, k=…` signed for the broker's origin with `pg_push_vapid_token()`, as the sender signs; `X-Pinegrap-License` with the normalised key when `pg_license_key_valid()` (the licence check's bearer cannot share Authorization); `X-Pinegrap-Site` as the Kodpen API client sends it.
- Quotas and who may use the broker are the broker's settings: the site passes its refusal on (`pg_push_broker_answer()`, pure): 429 with Retry-After (1-3600 s, 60 without), 400/404/410/422 as 422 field `token`, other 4xx as 403, the broker's own words (`pg_push_broker_detail()`, plain text, 300 characters) after the site's message; no answer or 5xx is 503. Forget counts 2xx, 404 and 410 as done.

## How does the sender treat a broker row? (M7b)
- `pg_push_send_to_subscription($row, $badge)` (`includes/push.php`) posts `pg_push_body()`: `{"v":1,"badge":n}` with `Content-Type: application/json` for a row with a provider, the empty push for a browser. No text, title or name. The badge is `pg_push_badge()`: unread rows that pass `pg_notification_visible_to_user()`, counted once per person (`pg_push_notify_user()`); the device's own count can differ when its application lacks `orders:read`.
- 404/410 delete the row (`pg_push_outcome()` 'gone'). A 429 writes `pg_push_hold_text()` ("HTTP 429, retry after <UTC time>") into `last_error`; the row is skipped until then (`pg_push_held_until()`), and the queue waits at least as long (`GREATEST()` with its back-off). `pg_push_retry_after()`: seconds or an HTTP date, 1 s to a day, 60 without. Browser rows get the same 429 rule.
- The sessions screen's test push sends a broker row its badge.

## When is the broker told to forget?
- `pg_push_broker_forget_endpoints()` before the rows go: a device revoke (`api_device_forget_push()`, every sign-out path), `POST /push/devices/delete`, and Stop on the sessions screen. Nothing is asked without a broker address; a forget waits 5 s and stops at the first unanswered call. A disabled account's rows (the queue) and a privacy erasure delete without asking.

Test: `tests/push_broker_test.php` (stubbed broker answers).
