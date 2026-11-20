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
- The robot never sets a price under the floor; a listing with no floor (no cost and none typed) is never repriced.
- A robot price carries no struck-through price: `listPrice` equals `salePrice`, since the price before a discount must be the lowest of the last 30 days (price label regulation).
- Each channel price queued goes into `product_price_history` on the account's channel, so the sales watch's price shield sees it (`plans/sales-watch.md`).
- The site's own price is never changed by the robot.

## What did the architect decide? (2026-11-20, agent channel message 10697)
- Schema: three tables, no column on `marketplace_product_map` or `marketplace_accounts`: `marketplace_price_rules` (account, category or brand rows: percent, fixed, rounding; the account row holds the robot's step, minimum margin, try-first), `marketplace_price_state` (per listing: strategy, own price, own floor, last buybox read, robot price, state), `marketplace_price_log` (each robot change, reason, sent or not; 90 days).
  - The owner said yes in poll 186 (message 10696): "Evet, üç tablo eklensin"; the columns: architect, channel message 10721, kept in the schema decisions list.
- Floor: R4. Grow while winning allowed by probing (R6). n11: rules and floor, Fixed only; a buybox read is a connector's ability, so Hepsiburada joins without touching the robot.
- Price history: source `rule` for a channel row from rules or the site price, `repricer` for the robot's; a hand price keeps `panel` / `api`. A row only when a price enters the queue, none in try-first. The channel names the account (two stores on one marketplace sell at their own prices); RP1 fixes `commerce/product-price-history.md` if its `<code>` names the provider.
- An upgrade changes no running site: no rule sends today's price; the robot stays off until switched on per account.

## What is built?
- Features: `marketplace-repricer-features.md` (R1 to R13). Steps: `marketplace-repricer-steps.md`. Claim as `[plan:marketplace-repricer] <step> — <topic>`.

## What is left out?
- Reading competitors' pages, repricing the site from marketplace prices, ad bids: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the account's Pricing part and the report are seen on dev after RP2 and RP3.
