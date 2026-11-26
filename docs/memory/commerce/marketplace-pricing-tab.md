---
name: marketplace-pricing-tab
description: Marketplaces › Settings drawer › Pricing tab and the Products in this shop row's own price and strategy (repricer RP2): what is stored, when nothing is written, the one-time requeue by the general job
module: marketplace_pricing_settings
---

## What does the Pricing tab hold?
- A tab of the account's Settings drawer (`marketplace_settings.php`), beside Connection, under the drawer's one Save; drawn once `mp_pricing_ready()`.
- Channel price: percent, fixed amount, rounding (None, Whole, ,90, ,99) on the account's own `marketplace_price_rules` row. Rules by category or brand: one row each, scope picked from the categories (`c:<id>`) and the brands products carry (`b:<brand>`).
- Robot: Try first (`robot` 1, on by default; off is 2), step with its unit as segments (Amount: minor units, at least 1; Percent: 0.01 to 10.00), minimum margin; Grow while winning's limit (`grow_cap`) folded under Advanced, open when it is not 10% or was refused.
- Read by `mp_pricing_settings_parse()`; a figure out of range refuses the whole drawer's save, the drawer opens again on the Pricing tab.

## When is nothing written?
- Left at its defaults with no rule (`mp_pricing_settings_is_default()`), the tab keeps no row, or removes the rows it had: the account then sends the site price, as before any rule. Saving the drawer for its name alone creates no pricing row.

## When are the prices sent again?
- When a save moves a price: any rule's percent, fixed or rounding, a rule added or removed, the margin (the floor), or Try first switched to or from sending (`mp_pricing_settings_changed()`; #PRNUM); the robot's step and limit do not. Only for an active account that sends prices.
- The request only marks the account in `data/temp/mp_pricing_requeue.json`; the general job (`job.php`, `mp_pricing_requeue_run()`) queues its active listings 500 a statement, five seconds a tick, skipping one a not yet taken row waits for. The drawer says "Prices are sent again in the background."
- A second change during a pass starts the account over (`mp_pricing_requeue_step()` keeps a newer mark). The marketplace job (`api_sync_job.php`) is not touched.

## What does a product's row show? (Marketplace Products › Products in this shop)
- A matched row gains: Channel price (grey while the rules or the site price set it), the floor or "No floor: no cost is known", Own price (empty is by the rules) and Strategy, with a quiet Save (action `shop_pricing`). It queues the listing when what it sends changes (`mp_pricing_listing_moves()`).
- Strategies: Fixed everywhere; Win and Grow while winning on Trendyol only (`mp_pricing_strategies()`); a strategy the marketplace lacks is stored as Fixed. RP4's robot reads it; RP2 only stores it.
- A changed own price queues that product on this account (`mp_matches_send()`).

## How does an own price go out?
- `mp_pricing_product_price()` sends `marketplace_price_state.own_price` as typed, before any rule and without the floor; the account's own prices are read once a request (`mp_pricing_account_own_prices()`). `mp_product_row()` marks it `price_own`, and the price history row is source `panel` (`mp_sync_record_channel_prices()`).

## What is tested?
- `tests/marketplace_pricing_settings_test.php`: parsing and units, defaults, refusals, the price-moving check, the requeue state, strategies, own price, the step's unit and `mp_pricing_step()`.

## How is the step's unit kept? (architect, 2026-11-20, agent channel message 11005)
- `marketplace_price_rules.step_unit` TINYINT (step 11.0.261), read on the account's row: 0 `step` is minor units, 1 hundredths of a percent. Decision: `workflow/decisions-made-schema-39.md`.
- `mp_pricing_step($from, $step, $unit)` works the step out: a percent of the price it moves from (the buybox price for Win, the own price for Grow while winning), rounded, never under 1 minor unit. Before the step has run, the column is read as 0 and not written.

Related: docs/memory/commerce/marketplace-channel-price.md, docs/memory/commerce/marketplace-repricer.md
