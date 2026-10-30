---
name: trendyol-common-label
description: Trendyol common label (#3123, PRs #3257, #3281, #3594): by cargo number, ZPL per box, card, order screen and bulk, kept as a label row, read again after the sweep; marketplace_carried
---

## Which calls?
- `POST /integration/sellers/{sellerId}/common-label/{cargoTrackingNumber}`, body `{"format":"ZPL","boxQuantity":N}` (`volumetricHeight` not sent); 200, no body. `GET` on the same path: `{"data":[{"label":"^XA…^XZ","format":"ZPL"}]}`, one label per box, joined into one `.zpl`. Errors `{"error":{"errors":[{"code","type","title"}]}}`: 400 cargo number wrong, 404 no parcel. Docs read 2026-10-29.
- Only parcels Trendyol Express or Aras Kargo carries on Trendyol's account; ZPL only; asked once the package is Picking or Invoiced (their advice). The cargo number goes into the path only as 5–40 letters and digits.

## Where is it asked for?
- A package qualifies (`mp_label_offer()` 'create') when it is open, its account ships with Trendyol's carrier and its first address's method code names Trendyol Express, TEX or Aras, or nothing (`mp_trendyol_common_label_carrier()`).
- The Imported orders card (`marketplace_settings.php`, action `common_label`, last 40 packages), with Boxes 1–99 (our cap).
- The order screen (`view_order.php`): "Get Trendyol label" with a box count in place of Create Label (`label_request` marketplace-<ship_to_id>, `shipment_label.php`). The create check refuses a carrier label service's label for it (`marketplace_label`), and for a package of an account not shipping itself that the label does not cover, another carrier or no longer open (`marketplace_carried`, `mp_label_carried()`, decided #3568): a second shipment. Order screen, bulk summary and API (409 label_refused) go through that check.
- The orders list's bulk Create Labels: one box, read once without waiting (`mp_label_create_for_ship_to()`). The bulk buttons show with a carrier label service or an active account that makes labels and does not ship itself (`pg_shipment_label_marketplace_offers()`, #3594).

## What does one request do?
- `mp_label_create()` (`includes/api/outbound/labels.php`), one run per package (lock): an order with an active `trendyol` label is answered without a call. The cargo number is the newest `source = marketplace` parcel of the first address. A label asked for before (here or in the seller panel) is read first and taken; else a `Created` package not accepted is accepted (Picking), the label asked for and read up to three times 2 s apart. Not made yet: nothing written, ask again. A refusal never writes `last_error`.
- `includes/fn/shipment_labels.php` loads the marketplace layer only for an order in `marketplace_order_map` (`pg_shipment_label_marketplace()`).

## How is it kept?
- File by `pg_shipment_label_store()`, row by `pg_shipment_label_row()`: driver `trendyol`, reference and number = the cargo number (the import's parcel, whose delivery the sweep reads), format zpl, no barcode. No file, no row.
- So: Print Label (a ZPL download), the bulk "Download ZPL Labels" file takes it, sweep, erasure and order delete as any label; GET /orders/{id} lists it. The order screen names it Trendyol (`pg_shipment_label_driver_name()`). No Cancel Label, no tracking job (no label driver has the key). After the sweep `pg_shipment_label_document()` reads it from Trendyol again (`mp_label_document()`), not written back.

## What is unverified or left?
- No live call; fixtures follow the docs (`tests/marketplace_trendyol_common_label_test.php`, `tests/marketplace_labels_test.php`). Unknown: GET before a request, how long the label takes, whether a second request is refused, a seller-paid or other carrier's answer, `volumetricHeight`, whether GET still answers after delivery.

Related: docs/memory/commerce/shipment-labels.md, docs/memory/workflow/decisions-made-product-3.md
