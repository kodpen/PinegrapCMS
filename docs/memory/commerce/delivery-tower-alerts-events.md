---
name: delivery-tower-alerts-events
description: Delivery tower alerts and events: the shipping ops alert codes, shipment.problem and shipment.problem_resolved, payload, flow kind filter, quiet first walk
module: delivery_tower_tell
---

## Which alerts are sent? (T9, DT2 #10889)
- Codes in `pg_ops_alert_codes()`, `group => 'shipping'`: `parcel_stuck`, `parcel_not_handed_over`, `parcel_problem` (delivery problem and returned together, `pg_delivery_tower_alert_codes()`). Bell link `view_orders.php` until the problem desk (DT3).
- The hourly pass counts each row it opens into the pass record's `told` (count, first 5 order numbers), kept across ticks; at the walk's end `pg_delivery_tower_alerts_send()` sends one `pg_ops_alert()` per code with something in it, so the once-a-day rule per code holds. Sentence: `pg_delivery_tower_alert_detail()`.
- Settings › Alerts: a Slack, Teams or Telegram target lists them under "Deliveries".

## Which events are announced? (T10)
- `shipment.problem`: each row the pass opens. `shipment.problem_resolved`: each row a later status closes (`reason` delivered, scanned or status_changed). Through `pg_announce()` (`pg_delivery_tower_announce()`), so webhooks and the Workspace both hear them.
- Not announced: a row closed because its order was cancelled or deleted or its parcel removed; a row closed on the desk (DT3 decides that); rows opened by a site's first walk.
- No built-in customer e-mail: a flow on the event tells the customer.

## What does the payload carry?
- `pg_delivery_tower_event_payload()` (pure): `id` (order), `order_number`, `order_type` (`orders.type`; `marketplace` means never write to that buyer), `problem_id`, `shipment_id`, `number`, `carrier`, `kind`, `province` (state, else city; null), `days_in_transit` (calendar days since shipped; null), `usual_days` (the figure's median, one decimal; null). Never a street address or name.
- Fields and sample in `webhook_payloads.php`; the API reference blocks come from them. Area "Orders" (`api_app_event_area()`).

## Why is the first walk quiet?
- On a site's first walk every parcel already in trouble in the last 90 days gets a row (24k on the 100k test copy). Announcing them would start a customer flow for old parcels at once. The record's `quiet` is set when no walk ever started (`at` 0) and cleared when that walk ends. Its alerts are sent.

## How do flows start on it?
- `ws_events_catalog()` once `pg_delivery_tower_ready()`: record the order, right `ecommerce`, filter `problem_kind` (`ws_events_problem_kinds()`; `ws_events_filter_value()` reads `kind`). Outputs in `ws_flow_event_outputs()`; the Orders area of quick setup.

## Tests?
- `tests/delivery_tower_tell_test.php`; `webhook_event_payloads_test.php` (builder); the parity table's lines.
