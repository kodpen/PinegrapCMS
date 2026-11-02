---
name: stock-locations-steps-2
description: Steps SL4-SL7 of plans/stock-locations.md: transfers and stock history, pickup at a location, API and webhooks, close-out; claim as [plan:stock-locations] SL<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `stock-locations-steps.md`; SL4 runs beside SL2 and SL3.

- SL4 (M) Transfers and history. After SL1.
  - Settings › Ecommerce › Stock locations (add, edit, order, default, enable); `view_stock_moves.php` (filters by product, location, reason, period, server paged) and a "Transfer" dialog (from, to, quantity, note: two `pg_stock_change()` calls in one transaction); the product screen shows its last moves. Memory: `commerce/stock-locations-moves.md`.

- SL5 (S) Pickup at a location. After SL3 and question 2.
  - A shipping method kind "Pickup at a location" (`shipping_methods.stock_location_id`, no rate), shown at checkout with the location's address and only when that location has the cart's units; the order's location set from it; the order screen, receipt and mails name the pickup place; the Ready-for-pickup status mail is the shipped mail's text until a later plan. Memory: `commerce/stock-locations-pickup.md`.

- SL6 (S) API and webhooks. After SL3 and SL4.
  - `GET /inventory/locations`, `GET /inventory?location=`, `POST /inventory/transfer` with scopes and `dry_run` (`api/api-endpoint-rules.md`), the location on `inventory.changed`; MCP tools follow; the marketplace and ERP pushes keep the total. Memory: `api/api-inventory-locations.md`.

- SL7 (S) Close-out. After SL1-SL6.
  - PR body names `[NEW] Added stock per location: warehouses and shops with transfers, stock history and pickup at a location`; decisions to `commerce/`; the two plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
