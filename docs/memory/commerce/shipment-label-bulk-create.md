---
name: shipment-label-bulk-create
description: Create Labels on the orders list: which addresses a run takes, the 20-call cap, refusals before the service, the notices (2026.11.0, #3972)
---

## Which addresses does Create Labels take?
- `pg_shipment_label_bulk_create()` (from `shipment_label.php`, `label_request` bulk_create): every address with active lines and no active outbound label of the selected orders whose status is not `incomplete` or `cancelled`, by `ship_to_id`.
- A package whose marketplace makes its label (`offer` create) gets that one, one box, read once without waiting (`mp_label_create_for_ship_to()`, `trendyol-common-label.md`); any other address goes through `pg_shipment_label_create()` with the driver its carrier takes.

## What does the 20-per-run cap count?
- Only service calls (each is one; Geliver waits for offers). The loop is the pure `pg_shipment_label_bulk_run($addresses, $limit, $check, $create)`: `$check` is `pg_shipment_label_create_check()` (no call), and an address it refuses (no driver for its carrier, label already there, a marketplace's parcel) is reported as a failure on every run but takes no place of the cap and is not "left".
- "Left" counts only addresses past the cap that pass the check, so "run Create Labels again" always reaches new addresses.
- Why (#3972): refusals used the cap, and with 20 refused addresses first in `ship_to_id` order every run made 0 labels and never reached the rest.

## What does the operator see?
- Back on the orders list: "N shipping label(s) were created.", "N address(es) are left…" when the cap stopped the run, and each distinct failure once.
- Tests: `tests/shipment_labels_test.php` (`test_shipment_label_bulk_run_cap`).
