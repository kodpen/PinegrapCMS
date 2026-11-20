---
name: marketplace-repricer-features
description: Plan marketplace-repricer, features R1 to R13: channel price rules, own channel price, the floor, buybox reads, the strategies, limits, try-first mode, the log, the report, the API
---

## How is a channel price worked out?
- (R1) Per marketplace account: the site price with VAT × (1 + percent) + fixed, rules per category or brand, the most specific first, else the account's own; rounding: none, whole, ends in ,90 or ,99; never under the floor (an ending under it moves to the next ending up). No rule: the site price as today.
- (R2) A listed product may carry its own fixed channel price, which wins over the rules.
- (R3) The channel price is what `mp_product_row()` sends; the account's rules are read once a pass, never a query per product. A rule change requeues the affected products by a job in batches, not in the request.

## What is the floor?
- (R4) Accounting on: the lowest price with VAT at which, after VAT, commission (the listing's, else the account's rate), payment fee and shipping, the cost (`erp_product_costs`) leaves the minimum margin (10% by default); rates from the order-profit plan's reader, no second SQL.
  - Accounting off: only a floor typed on the listing. The robot's floor is the higher of the two, rounded up; a typed floor only raises it. No floor: no robot.
  - The floor shows to the marketplace manager, its breakdown only with ERP access. A supplier buy price joins once that plan gives the product's buy price in the home currency.

## How is the buybox read?
- (R5) Hourly per Trendyol account, only listed products with stock: 10 barcodes a call, under the service's limit; rank, buybox price, other sellers and the read time kept per listing.

## What does the robot do?
- (R6) Strategies per listed product: Fixed (rules only, the default), Win (when another seller holds the buybox: its price minus one step, never under the floor), Grow while winning (probing: one step up a day at most, at most 10% over the rules' price, an account setting; a lost buybox steps back and its price is not tried again for 7 days). n11: Fixed only.
- (R7) The step: an amount or a percent per account (1 TL by default). A price changes at most 4 times a day and at most 10% a day per product; a pass works after the buybox read.
- (R8) Try-first: per account, the robot writes what it would send to the log and sends nothing; on by default for a new account.
- (R9) The site price changing moves the rules' price; the robot's price is never above the rules' price but by Grow while winning's 10%.

## What is kept and shown?
- (R10) Each robot change in `marketplace_price_log`: product, account, old and new price, the reason (lost buybox, floor reached, raised), sent or not, the time; 90 days.
- (R11) Marketplaces › account › Repricing: products winning, losing, held at the floor, with no cost; filters; the log. The product's marketplace row shows strategy, floor, rank and the last change.
- (R12) An operations alert (codes in `pg_ops_alert_codes()`) when more than half the Win products lose the buybox in one read, or the service fails three reads in a row.

## Who can read it from outside?
- (R13) `GET /marketplaces/{account}/repricing` (state per listed product, floor, rank, last change) and `PATCH` of a product's strategy and own price (scopes `products:read`, `products:write`), their types and MCP tools by the route rule.
