---
name: shipment-label-returns
description: Return label for a customer return on the returns screen (2026.11.0, 11.0.126, decided in #3178): Create Return Label on an approved request, one per request, code into carrier_code and a short mail
module: shipment_labels
---

## Where is the button?
- `view_return.php`, card "Return Label": on an approved request while a label driver is on (`pg_shipment_label_drivers()`), or once the request has labels (listed with Print Label, `shipment_label.php?id=`). Pressed by staff (`action=label`, the screen's token and `status_was`); the label may cost money. Bulk approval makes none. Cancel stays on the order screen.
- When no driver ships with the approval's carrier, the card says why and the approval goes on with the carrier and code given by hand (`pg_shipment_label_return_plan()`, pure: approved only, the driver from `pg_shipment_label_driver_pick()`; an empty carrier takes the first driver and the service picks).

## How is one label per request kept?
- `shipment_labels.order_return_id` (step `upgrade_2026_11_0_shipment_label_returns()`, 11.0.126, `KEY idx_shipment_label_return`); 0 for order-screen labels, whose one-active-return-label check stays per address and now counts only rows with 0 (`pg_shipment_label_create()`). The column is written only by a request label (`pg_shipment_label_row()` with `$order_return_id`), so a label made before the upgrade still saves.
- `pg_shipment_label_create_for_return()` takes a per-request `GET_LOCK` (no wait: a second press is told to wait), reads the request again, refuses when the request has an active return label (the paid call never runs twice), then `pg_shipment_label_open()` (shared with the order screen). A label of another request on the same address does not stop it.
- The shipment carries the request's lines and quantities (`order_return_items`), not the whole address; `return` true.
- Never a delivery parcel: `pg_shipment_label_records_parcel()` is false for a return row, so its number never reaches `pg_shipment_record()` and `order.delivered` cannot fire from it.

## What reaches the customer?
- `pg_return_create_label()`: the code (`pg_shipment_label_return_code()`: barcode, else the tracking number) goes into `order_returns.carrier_code` (`pg_return_label_code_change()`; the service's carrier only when the approval named none), audited with `pg_return_audit_fields()`, one activity log line. The `__returns` widget shows it as the return code.
- The approval mail always went out before the button exists, so a short mail carries only the code (`pg_return_label_mail_body()`, `pg_return_send_label_mail()`, mail queue). The PDF is never attached (#3178 2(b)).

## What is next?
- 2(c): the customer downloads the label on their order page, built with the first driver whose label the customer must print (Shippo): `order_return.php`'s owner check, the returns rate limit, the kept file or the service's copy.
- Not run against a live service or a database. Tests: `tests/shipment_labels_test.php` (plan, code, never a parcel), `tests/returns_test.php` (code change, mail body).
