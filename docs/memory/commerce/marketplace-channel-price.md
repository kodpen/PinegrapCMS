---
name: marketplace-channel-price
description: A marketplace account's own price by rules (2026.11.0, 11.0.256): rule pick, rounding endings, the profit floor, the three repricer tables, history rows on marketplace:<account id>
module: marketplace_pricing
---

## What does an account send as its price?
- `mp_product_row()` (`connectors/base.php`) puts `mp_pricing_product_price()` in `price`: the listing's own price as typed when it has one (`price_own`, `marketplace-pricing-tab.md`), else the site price with VAT (`mp_product_price()`, kept as `site_price`) moved by the account's rule, never under the floor. `price_rule` says a rule set it.
- No rule row for the account (every site after the upgrade): the site price as before. The drivers send `price` as both `salePrice` and `listPrice`: no struck-through price.

## Which rule does a product take?
- `marketplace_price_rules`, one row per account and scope: 0 the account, 1 a category (`scope_id` = `product_groups.id`), 2 a brand (`scope_key` = `products.brand`, compared without case or outer spaces).
- The deepest category the product sits in (walked up `product_groups.parent_id`; a tie at one depth: the lower id), else the brand, else the account's row (`mp_pricing_pick_rule()`).
- The rules are read once per account a request (`mp_pricing_account_rules()`); the product's groups only when a category rule exists.

## How is the rule's price worked out?
- Site price + `percent` (signed hundredths of a percent, `pg_percent_of()`) + `fixed` (signed minor units), never under 0, then the ending: `none`, `whole`, `90`, `99`, the nearest price with that ending, a tie upwards (`mp_pricing_round()`).
- Under the floor: the first price with the rule's ending at or over the floor (`mp_pricing_round_up()`).

## What is the floor?
- `mp_pricing_floor()`: the lowest price with VAT at which net (price less VAT) less commission and fee (their percent of the price plus fixed), shipping and cost leaves `min_margin` (hundredths of a percent of the net, 10% by default) on the account's row.
- Cost: `erp_product_costs.avg_cost`, only with Accounting on; no cost, no worked-out floor. Commission: `marketplace_product_map.commission` when above 0, else the account's typed `erp_cost_rates` marketplace rate (percent and fixed), read through `erp_order_profit_rates()`.
- No payment fee (the marketplace collects the money, as in `erp_order_profit_compute()`); no shipping figure exists for a marketplace sale, so the account rate's fixed part stands for its per-sale deductions.
- `marketplace_price_state.own_floor` (typed on the listing) only raises it (`mp_pricing_floor_final()`). Tax off: the VAT taken out is the product's own rate, else the zone's.

## What does the price history get?
- After a batch is handed over, a row per product whose channel price changed: channel `marketplace:<account id>`, source `rule`, or `panel` for an own price (`mp_sync_record_channel_prices()` in `sync.php`, `mp_pricing_history_record()`). Only for an account with `push_price` and at least one rule or own price; refused or deferred products are left out.
- Source `repricer` is reserved for the robot's prices (RP4).

## Where is the buybox read?
- `commerce/marketplace-buybox-read.md`: the connector ability, the hourly pass, `buybox_price`, `buybox_ours` (rank and other sellers), `buybox_at`.

## Which tables are there?
- `marketplace_price_rules` (unique `account_id, scope, scope_id, scope_key`; `robot`, `step` default 100, `min_margin` default 1000, `grow_cap` default 1000 read on the account row), `marketplace_price_state` (primary `account_id, product_id`, the listing; key `account_id, buybox_at`), `marketplace_price_log` (keys `account_id, product_id, at`; `account_id, at`; `at`). All NOT NULL with defaults; money minor units.

## What is tested?
- `tests/marketplace_pricing_test.php`: rule order, rounding endings, a floor with and without a listing commission, no cost, the site price unchanged.

Related: docs/memory/commerce/marketplace-price.md, docs/memory/commerce/product-price-history.md, docs/memory/plans/marketplace-repricer.md
