---
name: shipment-label-pickup-drivers
description: The label drivers' pickup operation (#3415): Shippo POST /pickups/ (#3475), Sendcloud Pickups API v3 (#3496), Geliver, Aras, Basit Kargo, Kargonomi without one, unverified points
module: shipment_labels
---

## Which drivers book a pickup?
- Shippo (`carrier-label-shippo.md`): `GET /transactions/{id}` per label (must be SUCCESS), `GET /rates/{rate}` for its `carrier_account`; labels of two carrier accounts are refused (one Shippo pickup is one carrier account). `POST /pickups/` with `carrier_account`, `location` (`building_location_type` from the account's `pickup_location` field, default Front Door; the sender address), `transactions`, `requested_start_time`/`requested_end_time` in UTC. CONFIRMED or PENDING succeed, reference = `confirmation_code`, else Shippo's pickup `object_id`; ERROR/CANCELLED are plain failures naming `messages`; no answer, a 5xx or an unreadable 2xx are unclear (a pickup may be charged).
- Sendcloud (#3496, `carrier-label-sendcloud.md`): Pickups API v3 `POST /pickups` (v2 is in maintenance). `GET /shipments/{id}` per label: `carrier.code` (else the shipping option's prefix), `ship_with.properties.contract_id`, the parcels' weight; one carrier and contract per request. Bookable: brt, correos_express, dhl, dhl_de, dhl_express, dhl_parcel_iberia, dpd, dpd_at, gls_it, hermes_de, poste_it_delivery (`pg_label_sendcloud_pickup_carriers()`); correos, dhl_parcel_gb, fedex, ups need fields the store does not keep and are refused naming the panel; any other carrier (PostNL, the test letter) has no pickup. A return, a cancelled shipment or a parcel without weight is refused before the booking. Body: the account's sender address, one UTC time slot, one `items` entry (count, total kg), as the reference's examples. ANNOUNCING/CREATED succeed, reference = the pickup id; FAILED/CANCELLED plain; no answer, a 5xx or a 2xx without an id unclear.

## Which drivers have none?
- No public pickup endpoint, so not offered (checked 2026-10-30): Geliver (docs.geliver.io lists only addresses, shipments, transactions, webhooks; pickup_* are tracking events), Basit Kargo (basitkargo.com/api: `pickupAddress` of an offer is information only), Kargonomi (no pickup path in its reference), Aras (the WSDL's `Recall`/`CancelRecall` take one code, no date or window, and have no published meaning).

## What is unverified?
- No live Shippo or Sendcloud call: the field names are unverified against a test key. Sendcloud also: whether a shipment answer carries the parcel weight, and whether `items` alone is accepted where the schema names `quantity`/`total_weight` (its examples send only `items`).
- Tests: `tests/carrier_label_shippo_test.php` (`tests/fixtures/shippo/pickup.json`, `rate.json`), `tests/carrier_label_sendcloud_test.php` (`tests/fixtures/sendcloud/pickup.json`, `pickup_shipment.json`).

Related: docs/memory/commerce/shipment-label-pickup.md
