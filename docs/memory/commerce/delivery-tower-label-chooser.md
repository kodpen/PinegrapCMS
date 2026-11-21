---
name: delivery-tower-label-chooser
description: Create Label carrier chooser on the order screen (DT4b, #10931): when it shows, the rows' order and suggestion, the api.php action, the label path's one optional argument
module: shipment_label_chooser
---

## When does the chooser show?
- Order screen › an address › "Create Label", when its shipping method names no carrier (`pg_shipment_label_carrier()` is '') and links no driver (`label_driver` ''), while two or more drivers are ready (`pg_shipment_label_chooser_due()`). Otherwise the button keeps its confirm.
- Never for "Create Return Label", a marketplace's label, the bulk run, the automatic label or the API: they pass no driver and keep today's pick.

## What does it show?
- A drawer (`#shipment-label-chooser`, 480 px off the right edge, a bottom sheet under 576 px set by `assets/js/shipment_label_chooser.js`), title "Create label", one line, the drivers as `.pg-card-inner-row` labels wrapping a radio, a meta line "A suggestion from your own deliveries; the choice is yours.", foot Cancel quiet and "Create label" fill.
- Each row: the driver's name; a teal "Suggested" pill on the first when any driver has a figure; the grey line "On time 94% in Ankara" (province share), "On time 94% across the country", or "Not enough parcels yet", then " · 84,50 ₺" when a price answered.

## How are the rows ordered?
- `pg_shipment_label_chooser_rows()`: `pg_delivery_tower_carrier_order()` (DT4) on the nightly summary's on-time shares, then price. No share for any driver: the drivers as `pg_shipment_label_drivers()` gives them (today's pick first) and no pill.
- A driver's share is its one carrier's; a driver shipping with several (Geliver, Sendcloud…) lets its service pick, so it has no share.
- Prices: `pg_shipping_label_rate()` (its day-long cache, cheapest service, the address's whole parcel) per driver with `rate()`, for at most `PG_SHIPMENT_LABEL_CHOOSER_SECONDS` (10); a driver not reached, failing or without rate() shows no price.

## How are the rows read?
- api.php action `shipment_label_choose` (`includes/panel/commerce.php`, exempt, token): session, then the order screen's rule (roles 0-2 or `manage_ecommerce`) in JSON, then the token. It answers `due` and the rows' HTML; of the address only the province is in it.
- While it reads, the card shows "Reading prices…"; "Create label" stays disabled until the radios are there.

## What changed in the label path?
- `pg_shipment_label_create()`, `_create_locked()` and `_create_check()` take an optional `$chosen` driver; `shipment_label.php` posts `label_driver` for `create-<id>` only. `pg_shipment_label_chosen_driver()` uses it only when the method names no carrier, links no driver and the driver is ready; otherwise the method's pick stands. A return label never uses it.

Tests: `tests/shipment_label_chooser_test.php`.
