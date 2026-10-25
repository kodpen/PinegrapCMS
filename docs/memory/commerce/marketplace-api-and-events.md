---
name: marketplace-api-and-events
description: GET /products/{id}/marketplaces (manager role only, never credentials) and the marketplace.order.imported, .cancelled and .shipped webhook events: where each is announced and what it carries (#1667)
---

## What does GET /products/{id}/marketplaces answer?
- One row per `marketplace_product_map` row of the product, joined to its account: `account_id`, `account_name`, `provider`, `account_status`, `remote_code`, `remote_id` (null while empty), `status` (active, paused, error), `last_error` (null while empty), `last_pushed_at`, `created_at`. One page, ordered by account id; a product with no mapping answers an empty list, an unknown product 404.
- Handler `api_products_marketplaces()`, presenter `api_product_marketplace_present()` and `api_product_marketplace_schema()` in `includes/api/resources/products.php`; object `ProductMarketplace`; route `products.marketplaces`.
- Only id, name, provider and status are selected from `marketplace_accounts`; the credentials blob is never read. A test checks the handler body for it.

## Who may call it?
- Scope `products:read`, and the application's owner must be below role 3 (`api_product_marketplaces_allowed()`): the marketplace screen is gated to the manager role, and `products:read` also reaches a user-role owner with commerce rights. Such an owner gets 403 `forbidden`.

## Which events are announced, and where?
- `marketplace.order.imported`: in `mp_import_claimed()` (`includes/api/outbound/orders.php`) once the order is written and the map row points at it, before the remote status is applied. One per package. A package cancelled before its first import writes no order and is not announced.
- `marketplace.order.cancelled`: in `mp_order_cancel()` only when `process_order_cancellation()` succeeded now; that function sends `order.cancelled` as well. An order already shipped is not cancelled here and gets no event.
- `marketplace.order.shipped`: in `mp_sync_ship_pending()` (`sync.php`) after the marketplace accepted the own-carrier report, with `tracking_url`. A parcel of the marketplace's own carrier is not this event; it arrives as `order.shipped` from the panel.
- The names are in `api_webhook_events()` beside the other order events (`api/api-webhook-events.md`).

## What does an event carry?
- `mp_order_event_payload()`: `id` (order id), `order_number`, `account_id`, `provider`, `remote_order_id`, `remote_package_id`, `remote_status` (the status the caller just saw, else the map row's), plus extras (`tracking_url`).
- `mp_order_announce()` reads the map row again by id and says nothing when it has no order yet, or when `pg_announce()` is not loaded.

## Tests?
- `tests/api_product_marketplaces_test.php`, `tests/marketplace_order_events_test.php`.
