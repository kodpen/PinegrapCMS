---
name: shipment-api-fields
description: The parcels block of GET /orders/{id} shipments and of the order.shipped / order.delivered payloads (pg_shipment_api_parcel), and the carrier POST /orders/{id}/shipment keeps on the parcel
---

## What does the API give per parcel? (PR #2004)
- Each `shipments[]` entry of `GET /orders/{id}` keeps `tracking_numbers` (strings, for older clients) and adds `parcels`: `number`, `carrier` (key or null), `carrier_name`, `tracking_url`, `status`, `shipped_at`, `delivered_at` (UTC `Y-m-d\TH:i:sZ`, null when 0). Unknown values are null, never ''.
- Built by `pg_shipment_api_parcel($row, $method, $default)` (pure, `tests/shipping_test.php`) over `pg_shipment_view()`, so the carrier and link are the ones the order screen and the e-mails show (row's own, else method code, default, number shape). `pg_shipment_api_parcels($ship_to_id, $method = null, $number = null)` reads the address's numbered rows.
- Declared in `api_order_schema()` (`includes/api/resources/orders.php`); the `orders.get` description in `includes/api/schema.php` explains the fields.

## Which webhooks carry parcels?
- `order.shipped` from `update_order()` (panel, Workspace, API, CSV): every parcel of the address, beside `tracking_numbers`.
- `order.shipped` from the marketplace import (`mp_order_apply_tracking()`): only the parcel it announces (the `$number` filter), like its one-element `tracking_numbers`. An order imported with no address (`ship_to_id` 0) has an empty `parcels`.
- `order.delivered` (`pg_shipment_delivered()`): the address's parcels after they were stamped delivered.

## What does carrier do on POST /orders/{id}/shipment?
- Still written into an empty `ship_tos.shipping_method_code` (any text, 50 chars). Since #2004 a carrier `pg_shipping_method_carrier()` recognises (key or name, any capitals) also goes on every number of the call: `pg_shipment_numbers_with_carrier()` turns the list into `number`/`carrier` entries, which `update_order()` hands to `pg_shipment_record()` as a chosen carrier. Free text sends plain numbers, so each row keeps its own carrier.
- A call without `tracking_numbers` changes no row's carrier.
- Refusing an unknown carrier with 422 is step 7b of `plans/shipping-management.md`, waiting on the owner.
