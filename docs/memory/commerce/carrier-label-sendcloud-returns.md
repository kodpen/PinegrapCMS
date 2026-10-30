---
name: carrier-label-sendcloud-returns
description: Sendcloud customer return labels (#3409): Returns API announce from the customer to the sender address, live mode and EU only, r-<return>-<parcel> reference, cancel request
---

## How is a customer return made?
- `pg_label_sendcloud_return_create()` (create() with `return`): live mode only, refused before any call in test mode (the letter is outgoing only; a return label is paid). Route check swapped (`pg_label_sendcloud_route_error($customer, $shop, true)`): the shop's country must be a Sendcloud sender country, customer and shop in one country or both in the EU customs union; else refused ("A return from CH to NL needs a customs declaration…"): no `parcel_items` or customs are sent.
- Option: the shipment's `service` as given (the store sends ''), never the account's (an outgoing code); else `POST /shipping-options` from the customer to the shop with `functionalities.returns` true, and `pg_label_sendcloud_option_pick($options, true)` takes only options marked `functionalities.returns`.
- `POST /returns/announce-synchronously` (`pg_label_sendcloud_return_body()`): `from_address` = the recipient, `to_address` = the full sender address (the Returns API takes no `sender_address_id`), `ship_with` flat `{type, shipping_option_code}`, `weight` / `dimensions` as numbers, `delivery_option` drop_off_point, `order_number`. Answers 201 `return_id`, `parcel_id`; a 400 is `{error: {message}}` (read by `pg_label_sendcloud_error_texts()`); unclear as the announce.
- Then `GET /returns/{id}` (answer wrapped in `data` or not) for `tracking_number`, `tracking_url`, `shipping_option.code` (carrier = the part before `:`), asked again up to 3 times 2 s apart while the number is missing (`pg_label_sendcloud_return_read()`, waits recorded under the test transport); then the label from `GET /parcels/{parcel_id}/documents/label`.

## What does the reference do?
- Reference `r-<return id>-<parcel id>` (`pg_label_sendcloud_return_ids()`): label() downloads that parcel's document without a read; cancel() is `PATCH /returns/{id}/cancel`: 202 is a cancellation request passed to the carrier and counts as done, 409 refuses with Sendcloud's message (`errors[].message`). track() has no return branch: `pg_shipment_tracking_open()` leaves return labels out.

## What is unverified?
- No live call: the owner's keys make the first. Unverified: whether `functionalities.returns` filters the options, the wrapping of `GET /returns/{id}`, the 400 body. Tests: `tests/carrier_label_sendcloud_test.php` (`return_*`), fixtures `shipping_options_return.json`, `return.json`, `return_pending.json`.
