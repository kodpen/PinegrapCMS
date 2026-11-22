---
name: marketplace-repricer
description: The marketplace repricer as a whole (2026.11.0, RP1 to RP5): what binds it, the architect's choices, which PR carries each feature R1 to R13, what is not built, where each part is written up
---

## What is it?
- A price per marketplace account by rules, a profit floor per listing, an hourly Trendyol buybox read and a robot that moves the price by strategy, never under the floor. Owner (2026-11-20): poll 173, channel message 10469 (`workflow/decisions-made-product-89.md`); tables: poll 186 (`workflow/decisions-made-schema-39.md`).

## What binds it?
- Prices leave only through the marketplace queue; the channel price is worked out in `mp_product_row()`, so every send carries it.
- The robot never sets a price under the floor; a listing with no floor (no cost and none typed) is never repriced. The site's own price is never changed.
- `listPrice` equals `salePrice`: the price before a discount must be the lowest of the last 30 days (price label regulation), so no struck-through price.
- Each channel price queued goes into `product_price_history` on `marketplace:<account id>`, so the sales watch's price shield sees it; none in Try first.
- An upgrade changes no running site: no rule row sends the site price as before, and the robot is off until an account gets its Pricing row.

## What did the architect choose? (2026-11-20, channel message 10697)
- Three tables, no column on `marketplace_product_map` or `marketplace_accounts`: `marketplace_price_rules`, `marketplace_price_state`, `marketplace_price_log` (columns: message 10721; `step_unit`: message 11005).
- A buybox read is a connector's ability (`buybox`): Hepsiburada joins without touching the robot. n11: rules and floor, Fixed only.
- History source `rule` for a rule or site price, `repricer` for the robot's, `panel` / `api` for a hand price.

## Which PR carries each feature?
- R1 rules, rounding, floor kept (#10790); R2 own price (#10849); R3 sent by `mp_product_row()`, rules read once, requeue by the job (#10790, #10849); R4 floor (#10790).
- R5 hourly buybox read (#10840); R6 Fixed, Win, Grow while winning, R7 step and day limits, R9 the site price moves the rules' price, R10 the log, 90 days, R12 the two alerts (#10901).
- R8 Try first: the switch #10849, the robot's dry run #10901. R11 Repricing view and the product row (#10902; the robot's price in the lists #10953). R13 API routes and MCP tools (#10953).
- Checked against the code at the close-out (2026-11-22).

## What is not built?
- R4's floor breakdown: the floor shows as one figure (or "No floor: no cost is known") to Manager and above; no breakdown of VAT, commission and cost for ERP users exists. A later step if wanted.
- R4's supplier buy price: waits until the supplier plan gives a product's buy price in the home currency; the floor reads only `erp_product_costs.avg_cost`.
- R4 payment fee: none, the marketplace collects the money (`marketplace-channel-price.md`).
- Left out of the plan: reading competitors' pages, repricing the site from marketplace prices, ad bids; each a later yes.

## Where is each part written up?
- Rules, floor, tables, history: `marketplace-channel-price.md`. Pricing tab and own price: `marketplace-pricing-tab.md`.
- Buybox read: `marketplace-buybox-read.md`. Robot, limits, alerts: `marketplace-repricer-robot.md`.
- Repricing view: `marketplace-repricing-screen.md`, `marketplace-repricing-lists.md`. API: `api/api-marketplace-repricing.md`.

Related: docs/memory/commerce/marketplace-price.md, docs/memory/commerce/marketplace-channel-price.md, docs/memory/commerce/marketplace-repricer-robot.md
