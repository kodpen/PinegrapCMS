---
name: marketplace-repricer
description: Plan (owner 2026-11-20, poll 173): a price per marketplace account by rules, a profit floor per product, Trendyol buybox reads and a repricer that never goes under the floor; RP1 to RP6
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 173, message 10469): "Evet, plana girsin": channel price rules, the profit floor, buybox watching, the repricer with its strategies and limits, the report, the log and a try-first mode.

## What is there today?
- A marketplace gets the site's price with VAT (`mp_product_price()` in `includes/api/outbound/connectors/base.php`, `commerce/marketplace-price.md`), built into the queue row by `mp_product_row()`; Trendyol refuses the same price and stock again within 15 minutes (`commerce/trendyol-stock-price.md`).
- `product_price_history.channel` keeps `marketplace:<code>` reserved for a price kept per channel; `source` is VARCHAR (`commerce/product-price-history.md`).
- Costs: `erp_product_costs.avg_cost`, `erp_cost_rates` (typed payment, shipping and marketplace rates) and `marketplace_product_map.commission` (the listing's rate, often NULL) (`plans/order-profit.md`, `commerce/marketplace-commission-and-invoice-columns.md`).
- No buybox read. Trendyol's buybox service (developers.trendyol.com, "Product Buybox Check"): POST with up to 10 barcodes and a `storeFrontCode` header; answers our rank, the buybox price and whether other sellers sell it; 1000 requests a minute. It does not give the second seller's price.

## What binds it?
- Prices leave only through the marketplace queue; the channel price is worked out in `mp_product_row()`, so every send carries it.
- The robot never sets a price under the floor; a product with no known cost is never repriced.
- A robot price carries no struck-through price: `listPrice` equals `salePrice`, since the price before a discount must be the lowest of the last 30 days (price label regulation).
- Each channel price written goes into `product_price_history` under `marketplace:<code>`, so the sales watch's price shield sees it (`plans/sales-watch.md`).
- The site's own price is never changed by the robot.

## What does the architect decide?
- Schema (the owner's, through the architect): a `marketplace_price_rules` table (account, category, brand or product, percent, fixed, rounding) and per listed product the strategy, own fixed price, own floor, last buybox rank, price and time, and the robot's price: columns on `marketplace_product_map` or a table of their own?
- The floor: cost from `erp_product_costs` (and the supplier feed's buy price when that plan lands); the listing's commission, else the account's typed rate; the shipping rate; a minimum margin. With Accounting off: only a floor typed on the product?
- "Grow profit while winning": the service gives no second price, so the robot raises one step at most once a period and steps back when the buybox is lost. Allowed, or "win" and "fixed" only?
- A new `source` label (`repricer`) for the robot's rows?
- n11 gets rules and the floor, no robot (no buybox service read); Hepsiburada when its connector lands?

## What is built?
- Features: `marketplace-repricer-features.md` (R1 to R13). Steps: `marketplace-repricer-steps.md`. Claim as `[plan:marketplace-repricer] <step> — <topic>`.

## What is left out?
- Reading competitors' pages, repricing the site from marketplace prices, ad bids: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the account's Pricing part and the report are seen on dev after RP2 and RP3.
