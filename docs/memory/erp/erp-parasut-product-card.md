---
name: erp-parasut-product-card
description: The Paraşüt product card a Pinegrap product gets (parasut_sync_product()): who opens it, what the create sends (list_price, opening stock), why no stock_updates follows (#1517)
---

## Who opens a product card?
- `parasut_sync_product($product_id, $token, $taken_out)` (includes/fn/parasut.php), once per product: the id is kept in `products.parasut_product_id` and later calls return it without a request.
- Callers: the ERP driver's `erp_edoc_parasut_line_product()` (an invoice line with a product, also the legacy Create Invoice's lines; it reads `success` and `parasut_product_id` and falls back to the default product) and E-Shipment (`parasut_create_shipment()`, a tracked line without an id; reads `error` too).

## What does the create send? (#1517)
- `POST products` with `parasut_product_attributes()`: `name` (short description, else the product name), `list_price` (`price` / 100, two decimals), `currency` `TRL`, `vat_rate` (`tax_rate`; none → 0, a card has no "zone decides"), `inventory_tracking` (`products.inventory`), and for a tracked product `initial_stock_count`: the stock before the document the card is opened for (erp-parasut-product-card-opening-stock.md).
- Before #1517 the price went as `unit_price`. A create with it on 2026-10-24 came back without `list_price`; cards the old code opened earlier do show one, so Paraşüt may have read `unit_price` once. `list_price` is the attribute it takes now. `product_type` (physical/service) went too; v4 products have no such attribute and ignore it.

## Why is there no stock_updates request? (#1517)
- `initial_stock_count` in the create already sets the stock (demo: 3 → `stock_count` 3.0). The `POST stock_updates` that followed was answered 422 "Güncellenen stok miktarı bulunamadı." every time and only reached the error log. The warehouse lookup before it (`parasut_ensure_default_warehouse()`, which also writes `config.parasut_default_warehouse_id`) went with it; the helper stays for E-Shipment.
- `/stock_updates` cannot seed stock from zero either: short stock is seeded by an inflow shipment document (erp-parasut-legacy-shipment-stock.md).

## How was it checked live? (2026-10-24, demo company 830533)
- `parasut_sync_product()` on dev product 43 (79.95, tracked, 9986 in stock): one request (POST products, counted through a logging proxy); the card read `list_price` "79.95", `stock_count` "9986.0", `has_stock_movements` false; the config warehouse id stayed empty. The card takes `list_price` without `code` or `unit`.
- Card deleted (204), product 43's id cleared, demo products equal to the baseline.
- A tracked product with 0 in stock opens too: dev product 45 (`inventory` 1, `inventory_quantity` 0) got card 1079494466, read back with `inventory_tracking` true (the same day, the driver's resend after a stale card: erp-parasut-stale-kept-ids.md).

## What if Paraşüt no longer holds a kept card?
- It is forgotten on every product holding it and the create sent once more: erp-parasut-stale-kept-ids.md.

## Tests
- `tests/parasut_product_test.php`: the attributes (tracked, untracked, below zero) and, from the source, no `stock_updates`, warehouse lookup, `unit_price` or `product_type` in `parasut_sync_product()`.
