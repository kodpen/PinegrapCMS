---
name: carrier-label-overview
description: Start file for the carrier-label-* files: label drivers, their decisions, the Carrier Labels block of the Shipping card, Create Label, which file holds each driver, adding one
module: carrier_labels
---

## What is it?
- A carrier label driver buys a shipment through a shipping service's own account and brings back its label (PDF, ZPL or SVG), tracking number and status. One driver per service; several may be on at once.
- Built in 2026.11.0: Geliver, Basit Kargo, Kargonomi, Aras Kargo (Turkey), Shippo, Sendcloud (abroad). The rules every driver keeps, the order they came in and what waits for documentation: `carrier-label-roadmap.md`.

## Which decisions bind it?
- Which services come, in which order (#3073): `workflow/decisions-made-product-2.md`.
- Label formats (#3123) and the first international driver (#3124): `decisions-made-product-3.md`.
- Return labels (#3178): `decisions-made-product-4.md`; customs data (#3408) and pickups (#3415): `decisions-made-product-7.md`.
- A Trendyol-carried package (#3568): `decisions-made-product-8.md`; labels at checkout and the automatic label: `decisions-made-product-39.md`.

## What does a person see?
- Site Settings › Ecommerce › Shipping, block "Carrier Labels": per driver a switch, its fields (secrets never drawn back) and "Save and test the connection"; the customs fields where the driver declares customs; "Create the label automatically once the order is paid" (`commerce/shipment-label-auto.md`). Code: `pg_label_settings_rows()`, `carrier-label-drivers.md`.
- The order screen's Shipping Labels card (Create Label, Print Label, Cancel Label, Create Return Label), the returns screen's Create Return Label, the orders list's Create Labels: `shipment-labels.md`, `shipment-label-bulk.md`, `shipment-label-returns.md`. Pickups: `shipment-label-pickup.md`.
- The order screen's notice "X refused the label: …" carries the service's own text, cut on a whole letter (`carrier-label-text-cuts.md`).

## Where is each part?
- The contract (`pg_label_<key>_` operations, `pg_label_call()`, answers, masking, settings in `connect_apps`, what reaches the shipment row): `carrier-label-drivers.md`.
- Price quotes for checkout (`rate`): `carrier-label-rate.md`; their use at checkout: `shipping-label-checkout-rate.md`.
- Customs, carrier-neutral lines and Shippo's declaration: `carrier-label-customs.md`; Sendcloud's: `carrier-label-sendcloud-customs.md`.
- One file per driver, `includes/shipping/<key>.php`:
  - Turkey: `carrier-label-geliver.md`, `carrier-label-basitkargo.md` (and SVG labels), `carrier-label-kargonomi.md`, `carrier-label-aras.md`; the district they send: `tr-district-carriers.md`.
  - Abroad: `carrier-label-shippo.md`, `carrier-label-sendcloud.md`, its customer returns `carrier-label-sendcloud-returns.md`.
- Status: the hourly poll `shipment-tracking.md`, webhooks `carrier-webhook.md`; pickups per driver `shipment-label-pickup-drivers.md`.

## How is a new driver added?
- A file `includes/shipping/<key>.php` with `pg_label_<key>_info()` (carriers, formats) and the operations the service has, a row in `pg_label_builtin_drivers()` (an add-on uses `pg_label_driver_register()`). It never reads the database or writes orders: the caller does through `pg_label_call()` (`carrier-label-drivers.md`).
- Requests go through `pg_label_<key>_transport()`, so `tests/carrier_label_<key>_test.php` runs on fixtures in `tests/fixtures/<key>/`; load the driver at the test's top. Cut texts with `mb_substr()` (`carrier-label-text-cuts.md`).
- A new driver's file `carrier-label-<key>.md` follows the existing ones: what it is, what each operation calls, which failures are unclear, what is unverified. Add it to this file and to `carrier-label-roadmap.md`.
