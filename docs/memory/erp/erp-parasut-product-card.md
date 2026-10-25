---
name: erp-parasut-product-card
description: The Paraşüt product card of a Pinegrap product (parasut_sync_product()): who opens it, what the create sends, the price's currency (#PR), why no stock_updates follows (#1517)
---

## Who opens a product card?
- `parasut_sync_product($product_id, $token, $taken_out)` (includes/fn/parasut.php), once per product: the id is kept in `products.parasut_product_id` and later calls return it without a request.
- Callers: the ERP driver's `erp_edoc_parasut_line_product()` (an invoice line with a product, also the legacy Create Invoice's lines) and E-Shipment (`parasut_create_shipment()`, a tracked line without an id; reads `error`).
- A card Paraşüt does not open refuses the invoice; the default product/service takes only lines without a product (erp-parasut-product-card-not-opened.md). A kept card Paraşüt no longer holds is forgotten and opened again (erp-parasut-stale-kept-ids.md).

## What does the create send? (#1517)
- `POST products` with `parasut_product_attributes()`: `name` (short description, else the product name), `list_price` (`price` / 100, two decimals), `currency`, `vat_rate` (`tax_rate`; none → 0, a card has no "zone decides"), `inventory_tracking` (`products.inventory`), and for a tracked product `initial_stock_count`: the stock before the document (erp-parasut-product-card-opening-stock.md).
- Before #1517 the price went as `unit_price`. A create with it on 2026-10-24 came back without `list_price`; cards the old code opened earlier do show one, so Paraşüt may have read `unit_price` once. `product_type` went too; v4 products have no such attribute.

## Which currency is the price in? (#PR)
- The store's base currency (`BASE_CURRENCY_CODE`, the one `price` is held in) in Paraşüt's code: `parasut_currency_code()`, TRY/TL → TRL, USD, EUR, GBP as they are. Paraşüt's swagger takes only these four for documents; a card's `currency` has no enum.
- Another base currency: no `list_price` and no `currency`, rather than a price in the wrong currency; invoice lines carry their own.
- Always `TRL` before #PR. Cards opened then keep it: nothing updates an existing card.

## Why is there no stock_updates request? (#1517)
- `initial_stock_count` in the create already sets the stock (demo: 3 → `stock_count` 3.0). The `POST stock_updates` that followed was answered 422 "Güncellenen stok miktarı bulunamadı." every time and only reached the error log. The warehouse lookup before it (`parasut_ensure_default_warehouse()`, which also writes `config.parasut_default_warehouse_id`) went with it; the helper stays for E-Shipment.
- `/stock_updates` cannot seed stock from zero either: an inflow shipment document seeds short stock (erp-parasut-legacy-shipment-stock.md).

## How was it checked live? (2026-10-24, demo company 830533)
- `parasut_sync_product()` on dev product 43 (79.95, tracked, 9986 in stock): one request (POST products, counted through a logging proxy); the card read `list_price` "79.95", `currency` "TRL", `stock_count` "9986.0", no movements; the config warehouse id stayed empty. No `code` or `unit` needed.
- Card deleted (204), product 43's id cleared, demo at its baseline. A tracked card opened with 0 in stock: 201.
- #PR's currency: unit tests only (demo key expired 2026-10-25); a card in EUR, USD or GBP, or without `currency`, never ran live.

## Tests
- `tests/parasut_product_test.php`: the attributes (tracked, untracked, below zero) and, from the source, no `stock_updates`, warehouse lookup, `unit_price` or `product_type` in `parasut_sync_product()`.
- `tests/parasut_default_product_test.php`: the card currency (and erp-parasut-product-card-not-opened.md).
