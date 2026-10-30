---
name: carrier-label-geliver
description: Geliver label driver (2026.11.0, #3096): two-step flow, offers, barcode as tracking number, PDF only, statuses, TRACK_UPDATED webhook (#3245), what is unverified
---

## What is it?
- `includes/shipping/geliver.php`, key `geliver` in `pg_label_builtin_drivers()` (token: Geliver panel, API tokens). Contract: `carrier-label-drivers.md`. From the public reference https://docs.geliver.io (`llms-full.txt`), base `https://api.geliver.io/api/v1`, `Authorization: Bearer`, answers `{result, data}` or `{result: false, code|errorCode, message}`.
- Fields: `api_token` (secret, required), `mode` (select live/test, default test: `"test": true` makes a free shipment), `sender_address` (a Geliver sender address id; empty = the default sender at Geliver), `service` (a service code such as `YURTICI_STANDART`; empty = the cheapest offer). No sender address fields: Geliver keeps the sender address itself.

## How does create() work?
- `$shipment`: file header (order_number, recipient with `state_code` = plate, parcel cm/kg, items, total in minor units, currency, service, return). `districtName` = `district`, else `city` (`tr-district-carriers.md`).
- `POST /shipments` → offers; while `percentageCompleted` < 100 it reads `GET /shipments/{id}` up to 5 times, 2 s apart. The offer: the asked service's (shipment `service`, else the account's), else `offers.cheapest`. In test mode the service is ignored (only `GELIVER_STANDART` offers). No offer → plain failure; the unpaid shipment stays at Geliver.
- `POST /transactions {offerID}` buys the label. A transport error after the request went out, a 5xx or a non-JSON answer is `unclear` (it may have been charged); `result: false` (E1066 balance etc.) is a plain refusal.
- `tracking_number` is the carrier's `trackingNumber`, else Geliver's `barcode`: some carriers give their number only at the branch, and the barcode is what the branch scans. `carrier` maps providerCode YURTICI/SURAT/ARAS/MNG/PTT; others stay '' (the shipping method decides).
- The label PDF is fetched from `labelURL` only over https on `geliver.io` hosts and without the token; a failed download does not fail create() (label() fetches it again).

## What do the other operations do?
- `test`: `GET /addresses/{sender}` or the first sender address; `account` = its short name.
- `label`: pdf only (Geliver reports ZPL in `labelFileType` but the reference has no way to ask for it). `cancel`: `DELETE /shipments/{id}`. `track`: `GET /shipments/{id}`; trackingStatusCode PRE_TRANSIT created, TRANSIT in_transit (sub `out_for_delivery` out_for_delivery), DELIVERED, RETURNED, FAILURE/CANCELED exception; without one the shipment `statusCode`; UNKNOWN is a plain failure.
- `track` is asked hourly by the tracking job (`shipment-tracking.md`); its time is `statusDate`, else the tracking status's `updatedAt`/`createdAt` (`pg_label_geliver_track_answer()`, shared with the webhook).
- No pickup endpoint (`shipment-label-pickup-drivers.md`).

## How does the TRACK_UPDATED webhook work?
- Receiver: `carrier-webhook.md`. `_webhook_read()` takes `{event, metadata, data: <shipment>}` (the reference's test message, fixture `webhook_track_updated.json`), only `TRACK_UPDATED`, `data.id` the reference, `data.isReturn` the return flag; `_webhook_register()` `POST /webhook {type, url}`, `_webhook_remove()` `DELETE /webhook/{id}` (404 = gone).
- No signature in the reference (`headerName`/`headerValue` undocumented for create): the key in the URL is the credential.

## How is it tested?
- `tests/carrier_label_geliver_test.php` through `pg_label_call()` with `pg_label_geliver_transport()` set to a stand-in (no waiting while one is set) and answers in `tests/fixtures/geliver/` built from the reference's examples.
- Webhook unverified: a live delivery, retries on a non-2xx, a query string kept in the URL.
- Unverified without the owner's test key: a live call, whether the recipient's `cityName` is read when `cityCode` is missing, the error HTTP statuses, and the label host (`labels3.geliver.io` in the reference).
