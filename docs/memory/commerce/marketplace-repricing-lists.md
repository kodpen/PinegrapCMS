---
name: marketplace-repricing-lists
description: The Repricing view's cards and lists (RP3b): what Winning, Losing, At the floor and No cost count, how each filters in SQL, the products list's price and columns, the price log's columns
module: marketplace_repricing
---

## What do the four figure cards count? (`mp_repricing_state()`)
- Winning: the last buybox read gives rank 1. Losing: rank over 1. A listing never read (rank 0) is neither.
- At the floor: the price going out is not above the floor; an own price typed under it counts. No cost: no floor (no cost known and none typed). The cards overlap.
- In SQL Winning and Losing read the rank in `buybox_ours`; At the floor and No cost need the floor, so the source first works every listing out in PHP and filters `map.product_id IN (…)` (`mp_repricing_view_condition()`).

## Which price does the products list show?
- `mp_repricing_listings($account, $ids)` works listings out in four statements (listings with cost, commission and state; groups only with a category rule; the last log row each; the rules once): own price, else the robot's price (`mp_repricer_send_price()`, `by` `robot`), else the rule's price with the floor, else the site price. It mirrors `mp_pricing_product_price()` without its query per product: a change to what `mp_product_row()` sends is made in both.
- Until #10953 the robot's price was missing here, so a sending robot's listing showed the rules' price.
- Columns: product (link to its row in Products in this shop), our price with "by the robot" / "by the rules" / "Own price" / "the site price", buybox price, rank, strategy, floor price, last change (old → new, reason pill, relative time). Sorts by product, rank and last change.
- Price log: time, product (a deleted one by its number), old and new price, reason pill, "Sent" or "Try first" pill; newest first.

Related: docs/memory/commerce/marketplace-repricing-screen.md, docs/memory/commerce/marketplace-channel-price.md
