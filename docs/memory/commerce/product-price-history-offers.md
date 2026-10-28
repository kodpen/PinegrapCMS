---
name: product-price-history-offers
description: Offer rows of product_price_history (2026.11.0, PR #681): the hourly general-job record of what open offers charge a visitor with no code or account; why recorded, marker, lock, limits
---

## What is an offer row?
- A `product_price_history` row of source `offer`: the price the open offers gave a visitor with no offer code, no account and an empty cart, as the storefront works it out (`get_discounted_product_prices(false)`, the guest view `pg_offer_guest_view()`), rounded to whole minor units like the cart, never above `products.price` (`pg_price_history_offer_prices()`).
- In: order-scope offers without a code whose conditions such a visitor meets (no rule; `new customer` in its default `no_orders` mode, since a guest has no orders; a usage limit not reached shop-wide), product and group actions. Out: coded and key-code offers, recipient scope, rules and conditions that need a cart, ERP member prices: they reach some buyers only.
- `source` names the writer, not a discount: when an offer ends the pass writes the stored price as an `offer` row. Read in order, the newest row at or before a moment is what such a visitor paid then.

## Who writes them and when?
- `pg_price_history_offer_pass_run($seconds)` (`includes/fn/price_history.php`), from `job.php` when `pg_price_history_offer_pass_due()` (e-commerce on, history ready, marker due): every `PG_PRICE_HISTORY_OFFER_EVERY` (3600 s), `PG_PRICE_HISTORY_OFFER_BUDGET` (10 s) a tick, products walked by id in chunks of `PG_PRICE_HISTORY_CHUNK`; a pass out of time goes on from `after_id` next tick.
- Each chunk compares with the newest row of any source and writes the differences, so an offer's start, end and change of amount are rows; a quiet hour writes nothing.
- Marker `data/temp/price_history_offers.json` (`pg_price_history_offer_state()`): `after_id`, `done_at`, `offers` (a discount was seen in the last pass), `seen` (in the pass under way). A lost file costs one full pass. Idle (`pg_price_history_offer_pass_idle()`): no discount now, none in the last pass, none under way: only `done_at` moves, so a shop without offers reads its offers once an hour, and an ended offer still gets the full pass that writes its end.
- Named lock `pg_price_history_offers.<database>` (GET_LOCK, no wait). A run with a signed-in user or a shopping session (a manager opening `job.php`) returns `skipped`; the next background tick does it.

## How do the writers treat offer rows?
- `pg_price_history_record()` compares the stored price with the newest row not of source `offer` (`pg_price_history_newest_sql($ids, $channel, 'offer')`); against an offer row every save during an offer would write a row. A price set during an offer gets its writer row, then the new offer price within the hour.

## Why recorded rather than computed?
- Offers, their actions, conditions and product groups are edited and deleted afterwards; a window worked out from them later would show prices nobody was charged. A row stays as written.
- Offers run by the day (`CURRENT_DATE()`), so an hour's lag never loses one that ran its dates.

## What are the known limits?
- A row is stamped when the pass saw the price: up to an hour after the offer began or ended.
- An offer switched on and off by hand within one hour can leave no row.
- No general job, no offer rows (ops alerts report a stopped job).

## How was it verified?
- `tests/price_history_test.php` (offer prices, marker, timing, source left out). On MariaDB with `php -S`: a 10 % offer wrote its row; a rerun was not due; the offer's end wrote the stored price; the next pass was idle; a same-price API save during the offer wrote nothing; a raised price wrote an `api` row, then the pass the new offer price; a manager's web run skipped; a cut pass resumed from `after_id`; the API listed the rows.

Related: docs/memory/commerce/product-price-history.md, docs/memory/commerce/product-reference-price-decisions.md
