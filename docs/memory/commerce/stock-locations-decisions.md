---
name: stock-locations-decisions
description: Stock per location as a whole: why it exists, the sum contract, what stays on the total, and where each part is written down
---

## Why does it exist?
- One `products.inventory_quantity` could not say where units are (a warehouse and a shop, two shops, consignment), which location an order ships from, or let a customer collect. Locations (`stock_locations`) hold the units; the product keeps one total. Owner plan 2026-11-02, questions in #5042 (`workflow/decisions-made-schema-8.md`, `decisions-made-product-19.md`).

## What is the contract?
- Once a product has `product_stock` rows, `products.inventory_quantity` is their sum, written only by `pg_stock_change()` / `pg_stock_set()` in the same transaction (`stock-locations-model.md`). Every reader of the total (catalogue, cart low-stock line, out-of-stock flag, back-in-stock notices, legacy and express-order totals) needed no change and must keep reading the total.
- A site without locations behaves as before: no rows, no moves, the old statements (`pg_stock_has_locations()`, one cached read). Rows appear when the first location is added.
- Untracked products (`inventory = 0`) have no rows and no location fields.
- An order's location lacking units is refused as the total would be; nothing is taken from another location (#5042 question 2).

## What stays on the total?
- Marketplace pushes and imports, ERP documents and stock counts, Workspace and product import change or send the total; their location is a later question. A transfer leaves the total as it is, so it announces nothing and queues no marketplace.

## Where is each part?
- Tables, writer, seeding: `stock-locations-model.md`. Product screens, bulk edit, import/export: `stock-locations-screens.md`. Sales, scans, the API's `location`, event keys: `stock-locations-completion.md`. Settings card, history, transfers: `stock-locations-moves.md`. Pickup at a location: `stock-locations-pickup.md`. API locations, levels and transfer, MCP: `api/api-inventory-locations.md`.
