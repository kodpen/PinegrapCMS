---
name: erp-parasut-legacy-shipment-stock
description: E-Shipment's stock step: every line's product card opened first, then each Paraşüt product made ready once for the order's total; one movement per order line (#PR)
---

## How does E-Shipment check the stock? (#PR)
- `parasut_create_shipment()` first opens the product card of every stock-tracked line (opening stock: erp-parasut-product-card-opening-stock.md). It then adds the lines' quantities up per Paraşüt product (`parasut_shipment_stock_needs()`) and calls `parasut_ensure_product_stock_tracking()` once per product with the total: tracking switched on if off, a card without movements and short of the total seeded by an inflow document for the shortfall.
- Before #PR each line opened its card and checked its own quantity. One product on two lines (two recipients, commerce/order-completion-stock-and-spots.md): a card at 3 passed 2 and 2, nothing was seeded and the document took 4; a seed for the first line covered that line only.
- When the step fails for one product, the cards of later lines are already open (with the right opening stock). No seed is written ahead of a card that then fails to open.
- A card with movements is not seeded. The code assumes Paraşüt lets its stock go below zero (a code comment; not checked live).

## What does the document carry?
- One stock movement per order line (`parasut_shipment_stock_movement()`), the shape checked live (#1458); the lines of one product are not merged. Two movements of one product in one document never ran live.

## How is it tested?
- `parasut_ensure_product_stock_tracking()` takes an injectable `$request` (a path after `_parasut_prefix()`; null sends it as before).
- `tests/parasut_product_stock_test.php`: the needs, the seed for the total on a simulated card with the shape recorded on 2026-10-24 (`stock_count` "9986.0", `has_stock_movements` false), the order of the step. Unit tests only: the demo key expired on 2026-10-25.
