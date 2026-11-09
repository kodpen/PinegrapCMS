---
name: carrier-label-geliver
description: Geliver label driver (2026.11.0, #3096): two-step flow, offers, barcode as tracking number, PDF only, statuses, webhook in carrier-webhook-geliver.md, what is unverified
---

## What is it?
- `includes/shipping/geliver.php`, key `geliver` in `pg_label_builtin_drivers()` (token: Geliver panel, API tokens). Contract: `carrier-label-drivers.md`. From the public reference https://docs.geliver.io (`llms-full.txt`), base `https://api.geliver.io/api/v1`, `Authorization: Bearer`, answers `{result, data}` or `{result: false, code|errorCode, message}`.
- Fields: `api_token` (secret, required), `mode` (select live/test, default test: `"test": true` makes a free shipment), `sender_address` (a Geliver sender address id; empty = the default sender at Geliver), `service` (a service code such as `YURTICI_STANDART`; empty = the cheapest offer). No sender address fields: Geliver keeps the sender address itself.

## How does create() work?
- `$shipment`: see the file header (recipient `state_code` = plate, parcel cm/kg, total in minor units). `districtName` = `district`, else `city` (`tr-district-carriers.md`).
- `POST /shipments` → offers; until `percentageCompleted` is 100 or `totalOffersCompleted` reaches `totalOffersRequested` (seen stuck at 99) it reads `GET /shipments/{id}` (`PG_LABEL_GELIVER_OFFER_READS` = 15, 2 s apart). Offer: the asked service's (shipment's, else account's), else `offers.cheapest`, else the list's lowest `totalAmount`/`amount`. Test mode ignores the service. No offer → plain failure; the unpaid shipment stays at Geliver ("Teklif alındı").
- Why: on dev (2026-11-09) the test offer came after ~18 s with `cheapest: null`; 5 reads and a cheapest-only pick failed.
- Checkout fees never come from Geliver, only from the shop's shipping methods.
- `POST /transactions {offerID}` buys the label. A transport error after the request went out, a 5xx or a non-JSON answer is `unclear` (it may have been charged); `result: false` (E1066 balance etc.) is a plain refusal.
- `tracking_number` is the carrier's `trackingNumber`, else Geliver's `barcode`: some carriers give their number only at the branch, and the barcode is what the branch scans. `carrier` maps providerCode YURTICI/SURAT/ARAS/MNG/PTT; others stay '' (the shipping method decides).
- The label PDF is fetched from `labelURL` only over https on `geliver.io` hosts and without the token; a failed download does not fail create() (label() fetches it again).

## What do the other operations do?
- `test`: `GET /addresses/{sender}` or the first sender address; `account` = its short name.
- `label`: pdf only (Geliver reports ZPL in `labelFileType` but the reference has no way to ask for it). `cancel`: `DELETE /shipments/{id}`. `track`: `GET /shipments/{id}`; trackingStatusCode PRE_TRANSIT created, TRANSIT in_transit (sub `out_for_delivery` out_for_delivery), DELIVERED, RETURNED, FAILURE/CANCELED exception; without one the shipment `statusCode`; UNKNOWN is a plain failure.
- `track` is asked hourly by the tracking job (`shipment-tracking.md`); its time is `statusDate`, else the tracking status's `updatedAt`/`createdAt` (`pg_label_geliver_track_answer()`, shared with the webhook).
- No pickup endpoint (`shipment-label-pickup-drivers.md`).

## How is it tested?
- `tests/carrier_label_geliver_test.php` through `pg_label_call()` with `pg_label_geliver_transport()` set to a stand-in (no waiting while one is set) and answers in `tests/fixtures/geliver/` built from the reference's examples.
- Live with the owner's test key on dev (2026-11-09): token test, `POST /shipments`, offers read work; `POST /transactions` refuses an invalid recipient phone (`0000000000`). Unverified: a purchase, the label host (`labels3.geliver.io` in the reference), error statuses, `cityName` without `cityCode`.
