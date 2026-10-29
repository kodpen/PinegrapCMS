---
name: carrier-label-sendcloud
description: Sendcloud label driver (2026.11.0, #3226), Europe: API v3 announce with the label inline, test mode as an unstamped letter, sender address id, no customs (EU or domestic only), cancel, tracking phases
---

## What is the driver?
- `includes/shipping/sendcloud.php`, key `sendcloud` in `pg_label_builtin_drivers()`, the second international driver after Shippo (#3124, `carrier-label-shippo.md`). Built from the public reference https://sendcloud.dev, API v3 only (v2 is closed to new users): base `https://panel.sendcloud.sc/api/v3`, HTTP Basic with the public and secret keys. Contract: `carrier-label-drivers.md`.
- Fields: `public_key`, `secret_key` (both secret, required), `mode` (test default, live), `sender_address` (a sender address id; empty = the first of `GET /addresses/sender-addresses`), `service` (a shipping option code such as `postnl:standard`; empty = cheapest), `label_format` (pdf, zpl).
- Test mode: no sandbox exists; every parcel ships as `sendcloud:letter` ("Unstamped letter"), a free real label no carrier takes. The service is then ignored.

## What do the operations call?
- create: reads the sender address (its country), refuses a route that needs customs (`pg_label_sendcloud_route_error()`: sender in AT BE DE ES FR GB IT NL; recipient in the same country or both in the EU customs union), then without a code `POST /shipping-options` (`calculate_quotes`) and takes the cheapest option needing no service point and no export documents, never the letter (`pg_label_sendcloud_option_pick()`). `POST /shipments/announce` (synchronous; `from_address.sender_address_id`, parcel cm/kg, dimensions only when all three sides are known, `order_number`, `total_order_price`, `label_details` PDF 72 dpi or ZPL 203).
- The answer: reference = shipment `id`, tracking number and `tracking_url` (Sendcloud's tracking page, the row's link) of the first parcel, the label from `parcels[0].label_file` (base64; left out of the log), else `GET /parcels/{id}/documents/label`; a failed label does not fail create. An `ANNOUNCEMENT_FAILED` answer (HTTP 200 with `errors`) is a plain failure: Sendcloud does not charge it.
- label: `GET /shipments/{id}` for the parcel id, then the document with `Accept: application/pdf|zpl` (A6). A native carrier ZPL cannot be had as PDF later; the check of the document refuses it. ZPL is converted by Sendcloud itself, never by an outside service.
- cancel: `POST /shipments/{id}/cancel`; 200 cancelled and 202 queued (watched 14 days) are done; 409 (already cancelled, delivered, 42 days) refuses.
- track: `GET /shipments/{id}` for the number, then `GET /parcels/tracking/{number}`; the first event's `phase` (`pg_label_sendcloud_status()`; in_delivery is out_for_delivery, delivery_forced delivered) or, with phase `none`, its `exception` (returned_sender returned, delays in_transit, the rest exception, unknown a plain failure).
- test: the sender address list; account = the address's company, city and country.
- Returns are refused: the Shipments API makes outgoing shipments only (the Returns API is a later step).

## Which failures are unclear?
- Only the announce and the cancel: no answer after the request went out, or a 5xx. Pricing and reads are plain.

## Which carriers reach the row?
- `pg_label_sendcloud_carrier()`: dpd and dpd_* dpd, dhl_de and dhl_express dhl, deutsche_post, ups, fedex, royal_mail. Sendcloud's plain `dhl` (DHL Parcel Benelux), PostNL, bpost, GLS, Colissimo and others stay '' (the method decides); the row still gets Sendcloud's tracking link. No carrier was added to `pg_shipping_carriers()`: their sites refused cloud fetches, so no tracking address could be checked.

## What is unverified?
- No live call: the owner's keys in test mode make the first. Unverified: the exact error bodies, the tracking answer's wrapping, the sender address order (the first is taken as the default). Tests: `tests/carrier_label_sendcloud_test.php` with `pg_label_sendcloud_transport()` and `tests/fixtures/sendcloud/`.
