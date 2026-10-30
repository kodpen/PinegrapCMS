---
name: carrier-webhook-drivers
description: The carrier webhook's Shippo (#3407), Sendcloud (#3431) and Kargonomi (#3633) sides: what each reader takes, the signature checks, how each registers and removes its webhook
module: carrier_webhook
---

## How does Shippo's side work?
- `pg_label_shippo_webhook_read()` takes only `track_updated`. With the pinned version the body is `{event, test, data: <tracking object>}` and `data.transaction` is the reference; an account on 2017-03-29 or older posts the transaction (its `object_id`). Status, time and number through `pg_label_shippo_track_answer()`, shared with track(); `is_return` false (the label row decides). Shippo's own test message (`transaction` null) carries nothing.
- Register: `POST /webhooks {event: track_updated, url, active, is_test}` (`is_test` for a `shippo_test_` token), id = `object_id`; an address of 200+ characters is refused locally, so the card shows it to add by hand. Remove: `DELETE /webhooks/{id}`, empty 2xx or 404 = removed. The webhooks API is beta; Shippo signs only under a sales agreement, so the key in the URL is the credential (its documented token-in-URL option). It retries on 408/429/5xx, wants an answer within 3 s, and tracks its own labels: nothing per label.

## How does Sendcloud's side work?
- `pg_label_sendcloud_webhook_read()` takes only `parcel_status_changed`; `parcel.shipment_uuid` is the stored shipment id. A return parcel (`is_return`) gives no event: its label is `r-<return>-<parcel>` and returns record nothing. `parcel.status.id` maps through `pg_label_sendcloud_webhook_status()` onto the tracking phases of `pg_label_sendcloud_status()` (cancelled, announcement failed, unknown ids: no status); time = `carrier_status_change_timestamp` (s), else `timestamp` (ms).
- Verify: `Sendcloud-Signature` = hex HMAC-SHA256 of the raw body with the account's secret key, plus the URL key.
- Register: Sendcloud keeps one webhook per integration. `GET /integrations`, the only one of `type` api (none or several, or one already sending elsewhere: refused, the card shows the URL to add by hand), `PATCH /integrations/{id} {webhook_active, webhook_url}` (204); id = the integration id. Remove: `GET /integrations/{id}` (404 = removed), only if the address is still ours `PATCH {webhook_active: false, webhook_url: null}`. Retries 10 times, out of order (the stale check handles it).

## How does Kargonomi's side work?
- `pg_label_kargonomi_webhook_read()` takes only `shipment.updated` (`meta.webhook.event_type`; none named also read). `shipment` is the GET /shipments/{id} shape, `shipment.id` the reference; status, time, number and carrier through `pg_label_kargonomi_track_answer()`, shared with track(). `is_return` false (outgoing shipments only). A repeated attempt (`attempt_number`) is the same news; the receiver records it once.
- Verify: `X-Webhook-Signature` = HMAC-SHA256 of the raw body with a `secret_key` the reference never says where to get. Register keeps a `secret_key`/`secret` found in the POST answer as `webhook_secret` in the sealed secrets (`pg_carrier_webhook_secrets_put()`; none drops an old one; remove drops it). With a secret the header must match (hex any case, `sha256=` prefix, or base64); without one the URL key is the credential, as with Geliver and Shippo.
- Register: `POST /webhooks {name: Pinegrap, url, event_type: shipment.updated, is_active: true}`, id from `data.id`, `webhook.id` or `id` (the reference shows no answer). Remove: `DELETE /webhooks/{id}`, 2xx or 404 = removed.
- Retries at 60, 120, 300, 600, 1200 s on non-2xx, none after 400/401/403/404/409/410/422: a wrong key or signature (401) and an unreadable body (400) are not retried; 429 and 503 are.
