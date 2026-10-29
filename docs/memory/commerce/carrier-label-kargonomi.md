---
name: carrier-label-kargonomi
description: Kargonomi label driver (2026.11.0, #3286), Turkish aggregator, no test mode: draft, prices, confirmation as the charge, carrier number read after it, PDF label, cancel in 36 h, unverified points
---

## What is the driver?
- `includes/shipping/kargonomi.php`, key `kargonomi` in `pg_label_builtin_drivers()`, from the public reference www.kargonomi.com.tr/help/api-dokumantasyonu/kargonomi-api/ (base `https://app.kargonomi.com.tr/api/v1`, `Authorization: Bearer`; a 422 is `{message, errors}`). Contract: `carrier-label-drivers.md`.
- No test mode, like Basit Kargo: built only on `tests/fixtures/kargonomi/`; the token's help says confirming the price buys a real, charged label; the owner makes the first live call. Taken before MNG ApiZone, whose reference pages cannot be read from outside.
- Fields: `api_token` (secret), `warehouse_id` (required: the sender is a warehouse of the account; the reference lists none), `provider` (a carrier code such as aras, surat, ptt; empty = cheapest). Carriers aras, surat, ptt (yurtici, mng mapped too); Kolay Gelsin and HepsiJet stay ''. PDF only.

## What does create() call?
- Refused before a call: a return, an address abroad, a name of one word or under five letters, an address under ten characters, no district or province, a phone that is not ten digits starting 2-5 or 8 (0, 90, 0090 dropped).
- `GET /states` and `GET /cities/{state}`, once a request per token: province (`state`) and district (`city`) by name, Turkish letters folded. An unknown one fails before any draft.
- `POST /shipments` `{shipment: {warehouse_id, buyer_*, packages: [{content, desi}]}}`: a free draft; desi = the larger of cm³/3000 and kg, at least 1. `GET /shipment-price-comparison/{id}`: the asked carrier's priced offer (shipment `service`, else the account's), else -1 (Kargonomi's cheapest) while any carrier priced it; "Hizmet Dışı Bölge" is no price. No offer: the draft is deleted, plain failure.
- `POST /confirm-shipping-price {shipment_id, shipping_provider_id}` is the charge: no answer or a 5xx is unclear; a refusal (422, balance) is plain and deletes the draft. Then `GET /shipments/{id}` up to five times, 2 s apart (none with a stand-in transport), until `shipping_webservice_tracking_code`, else `_barcode`, has a parcel's shape; `webservice_order_failed` is plain; still none is unclear and names the shipment. The package barcode (`JRJ3PA_1`) is no parcel number.
- Label: `GET /shipments/{id}/barcode?format=pdf`; the reference shows no answer, so a PDF body, a base64 body or any JSON text that decodes to a PDF is taken. A failed label does not fail create.

## What do the other operations call?
- label: the same download. cancel: `POST /shipments/cancel {shipment_id}`, carried out by Kargonomi within 36 hours; no answer or a 5xx is unclear. test: `GET /user/credit`, the balance logged.
- track: `GET /shipments/{id}`. draft, ready, webservice_order_creating/created, webservice_checking_shipment: created; webservice_shipment_started: in_transit at `delivery_date_to_shipment_office`; webservice_shipment_delivered: delivered at `shipping_provider_customer_delivery_date`; webservice_shipment_returning: returned; not_delivered, missing, order_failed, cancelled, request_for_cancellation: exception; any other a plain failure. The `shipment.updated` webhook is not used yet.

## What is unverified?
- No call was made. Unverified: the confirmation's answer (the reference repeats the price comparison's) and how long the carrier's number takes; the label answer; the /states and /cities answers (a `data` list of id and name assumed); JSON instead of its example's form fields; error bodies besides 401. Tests: `tests/carrier_label_kargonomi_test.php` with `pg_label_kargonomi_transport()`.
