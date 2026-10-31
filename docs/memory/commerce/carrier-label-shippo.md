---
name: carrier-label-shippo
description: Shippo label driver (2026.11.0, #3192), the first international one: rates then purchase, sender address in the account, PDF or ZPL II, refund as cancel, tracking status, European carriers
---

## What is the driver?
- `includes/shipping/shippo.php`, key `shippo` in `pg_label_builtin_drivers()`, decided in #3124 (`workflow/decisions-made-product-3.md`). Built from the public reference https://docs.goshippo.com: base `https://api.goshippo.com`, `Authorization: ShippoToken`, `SHIPPO-API-VERSION: 2018-02-08` pinned (the reference's examples). Contract: `carrier-label-drivers.md`.
- Carriers ups, fedex, usps, dhl, deutschepost, dpd, royalmail (`pg_label_shippo_carrier()` reads the rate's `provider`: "DHL Express", "DPD DE"…; Evri, Colissimo and others stay '' and the method decides). Formats pdf and zpl.
- Fields: `api_token` (secret; a `shippo_test_` token is test mode: free "SAMPLE - DO NOT MAIL" labels, tracking never moves), `label_file_type` (PDF_A6 default, PDF_4x6, ZPLII), `service` (a service level token; empty = cheapest), and the sender address `sender_name`, `sender_street1`, `sender_city`, `sender_zip`, `sender_country` (required) plus company, street2, state, phone, email: Shippo needs `address_from` on every shipment and keeps none for the API.

## What do the operations call?
- create: `POST /shipments/` (`async: false`, parcel in cm/kg, `extra.reference_1` = order number, `extra.is_return` for a return: scan-based, USPS/UPS/FedEx only, Shippo swaps the addresses) → rates; the asked service level's rate (shipment `service`, else the account's), else the lowest `amount_local`. `POST /transactions/ {rate, label_file_type, async: false}` buys it. Queued ones are asked again 3 times, 2 s apart.
- Reference = the transaction's `object_id`; tracking number and `tracking_url_provider` from it (the row's link). The label is fetched from `label_url` without the token, only over https on `goshippo.com` hosts or a `shippo-delivery*` S3 bucket (`pg_label_shippo_label_host()`); a failed download does not fail create.
- label: `GET /transactions/{id}`; refused when not SUCCESS (refunded) or when asked in another format than it was made (Shippo cannot redraw a label). cancel: `POST /refunds/` (void + refund; QUEUED/PENDING count as done, ERROR or a 4xx such as "already refunded" refuse). track: `GET /transactions/{id}` `tracking_status` (as the reference's tracking guide says): PRE_TRANSIT created, TRANSIT in_transit (substatus `out_for_delivery`), DELIVERED, RETURNED, FAILURE exception; UNKNOWN is a plain failure. Also by `track_updated` webhook (#3407, `carrier-webhook.md`). test: `GET /carrier_accounts?results=50`, account = the active carrier tokens.

## Which failures are unclear?
- Only the purchase and the refund: no answer after the request went out, a 5xx, or a transaction still QUEUED after the re-reads. A shipment costs nothing, so its failures are plain; a transaction with status ERROR (carrier refused) is plain and names Shippo's `messages`.

## Which carriers were added for it?
- `pg_shipping_carriers()`: `dhl` (dhl.de `verfolgen.html?piececode=`; the page's script reads `piececode`), `deutschepost` (deutschepost.de `sendungsverfolgung.html?piececode=`; its old query address redirects there), `dpd` (tracking.dpd.de `parcelstatus?query=…&locale=en_DE`; DPD's server forwards it to its parcel status), `royalmail` (royalmail.com `track-your-item#/tracking-results/`: refused cloud fetches with 403, not checked). No number patterns: their numbers overlap the plain-digit and S10 shapes.

## What is unverified?
- No live or test call: the owner's `shippo_test_` key makes the first (webhook included). Unverified: the exact error bodies, whether `amount_local` is always set, the label host of European carriers, the Royal Mail link. Customs declaration (#3449): `carrier-label-customs.md`. Pickup (`pickup_location` field): `shipment-label-pickup.md`. Tests: `tests/carrier_label_shippo_test.php` with `pg_label_shippo_transport()` and `tests/fixtures/shippo/`.
