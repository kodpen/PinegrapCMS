---
name: stock-locations
description: Plan (owner 2026-11-02): stock kept per location (warehouse, shop, consignment) with the product's total unchanged for every reader; transfers, pickup at a location, API and webhooks; steps SL1-SL7
---

## What is wrong now?
- A product's stock is one number: `products.inventory_quantity` (with `inventory`, `backorder`, `out_of_stock`), read by the catalog, the cart's low-stock line, order completion (`commerce/order-completion-stock-and-spots.md`), the barcode scan, the bulk API (`commerce/api-bulk-inventory-quantity.md`), the marketplace pushes (`commerce/trendyol-stock-price.md`) and the ERP. A business with a warehouse and a shop, two shops, or goods on consignment cannot say where the units are, which location an order ships from, or let a customer collect at one.
- The only location notion in the code is the marketplaces' own warehouse ids (`commerce/n11-warehouse.md`), which name the marketplace's warehouses, not the site's.

## What is proposed?
- `stock_locations` (name, address, enabled, default, order) and `product_stock` (product, location, quantity). `products.inventory_quantity` stays and is always the sum of a product's location rows, kept by one writer; every reader that exists keeps reading the total and needs no change. A site with no location row behaves exactly as today (the migration gives every product one row at an implicit default location only when the operator adds a second location).
- Product screen and bulk edit show the quantity per location; import and export carry one column per location; the panel keeps a stock history (every change with who, where, why) and transfers between locations.
- Order completion takes the units from the order's location: a location bound to the shipping method, the pickup location the customer chose, else the default; the barcode scan and the API name the location (the default when none). A shipping method of kind "Pickup at <location>" lets the customer collect; the order screen shows the location; `inventory.changed` and `stock.low` carry it.
- The API gains `/inventory/locations` and a location parameter on the inventory routes; webhooks carry the location; the marketplaces and the ERP keep the total (their location is a later question).

## Which decisions bind it?
- The sum rule is the contract: `products.inventory_quantity` is derived from the rows and never written directly once a product has rows; the writer recomputes it in the same transaction (`pg_stock_set()`), so the atomic decrement of the API and of order completion keeps its race-free shape per location.
- Legacy and express-order totals untouched; the cart's low-stock line, the out-of-stock flag and the back-in-stock notices read the total as today.
- Products that do not track inventory (`inventory = 0`) have no rows and no location columns on their screens.

## Which questions are decided? (#5042)
1. `stock_locations`, `product_stock`, `stock_moves`, `shipping_methods.stock_location_id`, `orders.stock_location_id`; the migration moves no data, the first location seeds the rows.
2. Owner: when the order's location lacks units the checkout refuses as the total would have; nothing is taken from another location.

## Where does it start?
- SL1 alone, then SL2, SL3 and SL4 together. Read `commerce/README.md` key rules, `commerce/order-completion-stock-and-spots.md`, `api-bulk-inventory-quantity.md`, `products-variant-model.md`, `barcode-stock-scan-checks.md`, `includes/api/resources/inventory.php`. Steps: `stock-locations-steps.md`, `-steps-2.md`.
