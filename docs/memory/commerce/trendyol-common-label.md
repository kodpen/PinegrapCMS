---
name: trendyol-common-label
description: Trendyol common label (#3123, PR #3257): asked for by cargo number on the Imported orders card, ZPL one per box, kept as a shipment_labels row (driver trendyol) under data/shipping_labels/
---

## Which calls?
- `POST /integration/sellers/{sellerId}/common-label/{cargoTrackingNumber}`, body `{"format":"ZPL","boxQuantity":N}` (`volumetricHeight` optional, not sent); 200, no body. `GET` on the same path: `{"data":[{"label":"^XA…^XZ","format":"ZPL"}]}`, one label per box, joined into one `.zpl` (`mp_trendyol_common_label()`). Errors `{"error":{"status","errors":[{"code","type","title"}]}}`: 400 cargo number wrong, 404 no parcel. Docs read 2026-10-29 (modified 2026-02-16).
- Only parcels Trendyol Express or Aras Kargo carries on Trendyol's account; ZPL only; asked for once the package is Picking or Invoiced (their advice). The cargo number goes into the path only as 5–40 letters and digits (`mp_trendyol_cargo_number_fits()`).

## Where is it asked for?
- The Imported orders card (`marketplace_settings.php`, action `common_label`): a folded form with Boxes (1–99, our cap) under a package that is open (`mp_order_stage()`), of an account on Trendyol's carrier, whose first address's method code names Trendyol Express, TEX or Aras, or nothing (`mp_trendyol_common_label_carrier()`); a Yurtiçi or MNG package gets none. A kept label shows Print Label instead (`mp_label_offer()`). The card lists the last 40 packages.

## What does one press do?
- `mp_label_create()` (`includes/api/outbound/labels.php`), one run per package (lock): an order with an active `trendyol` label is answered without a call. The cargo number is the newest `source = marketplace` parcel of the first address, as the import recorded it. A label asked for before (here or in the seller panel) is read first and taken; else a `Created` package not accepted is accepted (Picking, as `mp_sync_ship_pending()`), the label is asked for and read up to three times, 2 s apart. Not made yet: nothing is written and the operator presses again (the next press reads first). A refusal is a screen warning, never `last_error`.

## How is it kept?
- File by `pg_shipment_label_store()`, row by `pg_shipment_label_row()` (`mp_label_row()`): driver = the provider key `trendyol`, reference and number = the cargo number, format zpl, no barcode. No file, no row. The parcel row is the import's, so `pg_shipment_record()` is not called.
- So the order screen lists it (Print Label: a ZPL download from `shipment_label.php`), refuses Create Label for that address, the bulk print names it among the labels not in the PDF, and the sweep (30 days after the parcel's delivery, 90 at most), erasure and order delete treat it as any label. GET /orders/{id} lists it with driver `trendyol`.
- No label driver has the key: no Cancel Label (`view_order.php` draws it only when `pg_label_can($driver, 'cancel')`), no tracking job (the order feed reports the package), no reprint once the sweep removed the file, and the order screen names it by its key.

## What is unverified?
- No live call; fixtures follow the docs (`tests/marketplace_trendyol_common_label_test.php`, `tests/marketplace_labels_test.php`; the press itself needs a database and was not run). Unknown: what GET answers before a request, how long the label takes, whether a second request is refused or makes a new barcode, whether a seller-paid or other carrier's package is refused with a readable title, what `volumetricHeight` is.

Related: docs/memory/commerce/shipment-labels.md, docs/memory/commerce/trendyol-orders.md, docs/memory/workflow/decisions-made-product-3.md
