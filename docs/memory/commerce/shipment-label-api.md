---
name: shipment-label-api
description: Carrier labels in the external API (2026.11.0, PR #3220): POST /orders/{id}/shipments/label with a dry run, labels per shipment in GET /orders/{id}, the document route, the create check split
module: shipment_labels
---

## How is a label created over the API?
- `POST /orders/{id}/shipments/label` (`orders.labels.create`, `orders:write`, 201 `OrderShipmentLabel`): `ship_to_id` (optional with one address, 422 with several, as `POST /orders/{id}/shipment`), `is_return`. It calls `pg_shipment_label_create()`, the panel's Create Label, with the key's owner as `user_id`.
- Refusals come from `pg_shipment_label_create_check()` with the panel's messages, mapped by `api_order_label_refusal()`: not_ready 503 `service_unavailable`, not_found 404, 409 `order_not_completed`, `label_exists`, `no_label_service`; after the service, 409 `label_refused` or `label_unclear` (create's `unclear`). An order with no address is 409 `no_shipping_address`.

## How is the check split?
- `pg_shipment_label_create_refusal($ready, $order, $active, $driver, $return)` (pure) holds the refusals in create's old order: table, order, completed, active label of the kind (return-request labels not counted), driver. `pg_shipment_label_create_check()` reads only (ship_to, order, active count, driver pick) and returns `ship_to`, `order`, `driver`; `pg_shipment_label_create()` runs it first and keeps no refusal of its own.
- The dry run stops right after the check (`api_dry_run_stop('created', 'shipment_label', …)` with `driver`), so no service is called and nothing is written. What only the service knows (its refusal) is not foreseen.

## What does GET /orders/{id} list?
- `shipments[].labels`, newest first, one query per order (`api_order_labels()`): `id`, `driver`, `number`, `reference`, `barcode`, `format`, `state`, `is_return`, `created_at`, `cancelled_at`, `file_kept` (a file not swept), `document_url`. The answer of `POST /orders/{id}/shipment` carries it too. Never the document or the stored file name: the document holds the recipient's address.

## How is the document handed out?
- `GET /orders/{id}/shipments/labels/{label_id}/document` (`orders.labels.document`, `orders:read`, `binary`, `mcp` false): the row only when it belongs to the order in the path (404 otherwise), `pg_shipment_label_document()` (kept file, else the service's copy; 409 `label_unavailable`), headers `pg_shipment_label_headers($format, $name, true)` through `api_order_label_headers()`: an SVG is always an attachment with `Content-Security-Policy: sandbox`, never the print page. Bytes are sent from memory, so no ETag or Range.
- The rights are the order routes' scopes: `orders:*` go to owners who manage e-commerce (`api_owner_scopes()`), the panel's `validate_ecommerce_access()`.

## How is it tested?
- `tests/shipment_label_api_test.php`: refusal codes and messages, create goes through the check, the check writes nothing, the rehearsal stops before the service, the list shape against its declaration, the headers, the routes' scopes.
