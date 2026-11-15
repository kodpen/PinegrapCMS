---
name: api-native-push
description: Native push for team devices (M7a, #9703): POST /push/devices and /push/devices/delete, the push broker call, push_subscriptions.provider (11.0.215), PG_PUSH_BROKER_URL, GET /meta features.native_push
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
- Only through `pg_push_broker_call($action, $payload, $send = null)`: register is `POST {broker}/v1/endpoints` (provider, token, bundle_id; answer 200/201 `{"endpoint", "expires_at"}`), forget is `POST {broker}/v1/endpoints/delete` (`endpoint`). The contract is Kodpen's; these paths are the proposal of #9607.
- Headers (`pg_push_broker_headers()`, pure): `Authorization: vapid t=…, k=…` signed for the broker's origin with `pg_push_vapid_token()`, as the sender signs; `X-Pinegrap-License` with the normalised key when `pg_license_key_valid()` (the licence check's bearer cannot share Authorization); `X-Pinegrap-Site` as the Kodpen API client sends it.
- Quotas and who may use the broker are the broker's settings: the site passes its refusal on (`pg_push_broker_answer()`, pure): 429 with Retry-After (1-3600 s, 60 without), 400/404/410/422 as 422 field `token`, other 4xx as 403, the broker's own words (`pg_push_broker_detail()`, plain text, 300 characters) after the site's message; no answer or 5xx is 503. Forget counts 2xx, 404 and 410 as done.

## What is left for M7b?
- The sender gives a broker row the body `{"v":1,"badge":n}`, honours 429 Retry-After, and a device revoke tells the broker to forget (`plans/mobile-api-shelved.md`). Until then a broker row is woken with the empty request a browser gets.

Test: `tests/push_broker_test.php` (stubbed broker answers).
