---
name: shipment-label-returns
description: Customer return labels (#3178): Create Return Label on an approved request, one per request, code into carrier_code, a short mail (#3194); the customer's download (#3239, widget #3268)
module: shipment_labels
---

## Where is the button?
- `view_return.php`, card "Return Label": on an approved request while a label driver is on (`pg_shipment_label_drivers()`), or once the request has labels (listed with Print Label, `shipment_label.php?id=`). Pressed by staff (`action=label`, the screen's token and `status_was`); the label may cost money. Bulk approval makes none. Cancel stays on the order screen.
- No driver for the approval's carrier: the card says why, the approval goes on by hand (`pg_shipment_label_return_plan()`, pure; an empty carrier takes the first driver).

## How is one label per request kept?
- `shipment_labels.order_return_id` (step `upgrade_2026_11_0_shipment_label_returns()`, 11.0.126, `KEY idx_shipment_label_return`); 0 for order-screen labels, whose one-active-return-label check stays per address and now counts only rows with 0 (`pg_shipment_label_create()`). The column is written only by a request label (`pg_shipment_label_row()` with `$order_return_id`), so a label made before the upgrade still saves.
- `pg_shipment_label_create_for_return()` takes a per-request `GET_LOCK` (no wait: a second press is told to wait), reads the request again, refuses when the request has an active return label (the paid call never runs twice), then `pg_shipment_label_open()` (shared with the order screen). A label of another request on the same address does not stop it.
- The shipment carries the request's lines and quantities (`order_return_items`), not the whole address; `return` true.
- Never a delivery parcel: `pg_shipment_label_records_parcel()` is false for a return row, so its number never reaches `pg_shipment_record()` and `order.delivered` cannot fire from it.

## What reaches the customer?
- `pg_return_create_label()`: the code (`pg_shipment_label_return_code()`: barcode, else the tracking number) goes into `order_returns.carrier_code` (`pg_return_label_code_change()`; the service's carrier only when the approval named none), audited with `pg_return_audit_fields()`, one activity log line. The `__returns` widget shows it as the return code.
- The approval mail always went out before the button exists, so a short mail carries only the code (`pg_return_label_mail_body()`, `pg_return_send_label_mail()`, mail queue). The PDF is never attached (#3178 2(b)).

## How does the customer get the label document? (#3239)
- A "Download return label" link on the request in `pg_returns_list_html()` (the classic order view; `pg_return_present()` carries `label_url`, filled by `pg_return_customer_view()`). Offered while the request is `approved` or `shipped` and has an active label (`pg_shipment_label_customer_offered()`, ids from `pg_shipment_label_labelled_returns()`); a cancelled label never.
- The widget's returns part shows it too (#3268): row tokens `__return_label_url`, `__return_has_label`, empty unless offered ([[widget-default-parts-returns]]). A layout saved with the part earlier gets it only when the designer places it.
- `order_return.php` GET `action=label&order_id&return_id` (before the POST token check): `pg_shipment_label_customer_refusal()` (pure) refuses anybody but the signed-in owner on `orders.user_id` with one 403 answer, then the returns' hourly allowance (the same `return|<user>` bucket as the moves, 429), then a return of another order or no offered label (404). Then `pg_shipment_label_document()`: the kept file, else the service's copy through the driver; on failure the log keeps the driver's words, the customer is told to contact the shop (502).
- Headers `pg_shipment_label_customer_headers()`: PDF inline, ZPL a download, SVG only the sandboxed attachment (never the panel's print page).

## What is unverified?
- Never run live or on a database. Tests: `shipment_labels_test.php`, `returns_test.php`, `widget_default_parts_returns_test.php`.
