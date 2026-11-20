---
name: marketplace-repricing-screen
description: Marketplace Products › Repricing view (RP3b): the four figure cards, Products and Log, the Try first line, rank and last change on a product row
module: marketplace_repricing
---

## Where is the Repricing view?
- A fourth view of Marketplace Products (`marketplace_products.php?account=<id>&mode=repricing`), beside Catalogue, Products in this shop and Listings, on every marketplace once `mp_pricing_ready()`. Marketplaces' Matched products card leads there with a quiet "Repricing" button.
- Two parts as link segments: Products (default) and Log (`&part=log`); 40 rows a page.

## What do the four figure cards count? (`mp_repricing_state()`)
- Winning: the last buybox read gives rank 1. Losing: rank over 1. A listing never read (rank 0) is neither.
- At the floor: the price going out is at or under `mp_pricing_round_up(floor, rounding)`, the lowest price the rule's ending can reach from the floor (a typed own price may sit under it). No cost: no floor (no cost known and none typed).
- The cards overlap (a losing listing can be at the floor). Each card is a link (`&view=winning|losing|floor|no_cost`); the chosen one is shown by the filter row under them (All and the four, with counts). The search box finds a title or the code on the marketplace.

## Which price does the Products list show?
- `mp_repricing_listings()` works out every matched listing in four statements (listings with cost, commission and state; groups only with a category rule; the last log row each; the rules once): own price, else the rule's price with the floor (`mp_pricing_pick_rule()`, `mp_pricing_channel_price()`), else the site price. It mirrors `mp_pricing_product_price()` without its query per product: a change to what `mp_product_row()` sends (the robot's price, RP4) is made in both.
- Columns: product (link to its row in Products in this shop), our price with "by the rules" / "Own price" / "the site price", buybox price, rank with the read's time, strategy, floor, last change.

## What does the Log show?
- `marketplace_price_log` newest first: time, product (a deleted one by its number), old and new price, the reason as a pill, and "Sent" or "Try first".
- Reason codes (`MP_REPRICING_REASON_*`, at most 20 characters): `lost` Buybox lost (amber), `floor` Floor reached (rose), `raised` Raised while winning (teal), `back` Stepped back (blue). A code this version does not know shows as stored, grey.

## When does the Try first line show?
- Above both parts while `mp_repricing_try_first()`: the account's Pricing row has Try first on (`robot` 1) or the account has no Pricing row yet (the tab starts with Try first on). Its Pricing button opens `marketplace_settings.php?account=<id>&settings=pricing`, the Settings drawer open on the Pricing tab.

## What does a product's marketplace row gain? (Products in this shop)
- Beside channel price and floor: "Buybox: Ours / Rank N / Not read yet" with the buybox price when not ours, only where the connector names `buybox`; and "Last change: old → new", the reason pill, "Try first" when not sent, and the time.

## Which word names the floor?
- `lang('Floor price')` (Taban fiyat), in the list and on the product's row: `lang('Floor')` is a building's floor (Kat) in the coworking templates.

## What is tested?
- `tests/marketplace_repricing_test.php`: rank and others from `buybox_ours`, the floor with and without an ending, no cost, the counts, the card filter and search, reason names, the Try first line, the rank's words.

Related: docs/memory/commerce/marketplace-pricing-tab.md, docs/memory/commerce/marketplace-buybox-read.md, docs/memory/ui/look-marketplace-ranks.md
