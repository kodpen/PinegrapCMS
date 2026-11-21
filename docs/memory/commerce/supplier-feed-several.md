---
name: supplier-feed-several
description: Supplier feeds (SF5): one product in several feeds follows the one with stock and the lowest buy price, shown on the product; prices worked out again after Update Exchange Rates, no pull
module: supplier_feed_choice
---

## Which feed does a product in several feeds follow? (F9)
- `includes/fn/supplier_feed_choice.php`, loaded by `supplier_feed_sync.php`. `pg_supplier_feed_choose()` runs in each pull's apply and close steps, before the records write; the choice is `supplier_feed_items.active`, one item per product.
- `pg_supplier_feed_pick_item()` (pure): of the items with stock, the lowest buy price in the home currency (`pg_supplier_feed_buy_home()`, the store's rate); no buy price comes last; a tie keeps the item in use, then the oldest. No stock anywhere: the item in use stays, so its feed closes the product.
- This pull's items count with this pull's stock and buy price (net of VAT, a held price not taken); other feeds' items with what their last pull kept. Only feeds that are on take part; a paused or off feed's item loses its place at the next pull of another feed.

## What happens when the choice moves?
- To this pull's item: it writes the product as usual (its price by its own feed's rule).
- To another feed's item: the product gets that item's kept stock at once (closing or reopening as that feed would, `pg_supplier_feed_follow_other()`), and that feed's `next_run_at` is brought to now so its price follows from a fresh buy price.
- The item that closed the product hands `closed_by_feed` to the new one, so the new feed may reopen it; a person's off still stays off.

## How is the choice shown?
- The product's "Supplier feed" card (`pg_supplier_feed_product_card_html()`): with two feeds or more, the one in use first with the green "In use" pill, and one line on how it is chosen.

## What follows a rate? (F10)
- `pg_store_currency_rates_refresh()` (Update Exchange Rates button, its job, `POST /store/currencies/rates`) passes each changed rate to `pg_supplier_feed_rates_note()`: feeds that are on and priced in that currency go into `data/temp/supplier_feed_rates.json` with the old rate. A hand edit on Edit Currency does not.
- `pg_supplier_feed_job_tick()` calls `pg_supplier_feed_reprice_tick()`: under the feed's lock, never during its pull, 200 items a step, the followed items with "Price from the feed" on and a buy price get the price through `api_products_set_price()` (source `supplier`). A lost file only means the next pull does it.
- `pg_supplier_feed_reprice()` (pure): the item keeps no category or brand, so when the feed's rules disagree the rule is the one that gave the product's price at the old rate; none or several: the price waits for the next pull.

Tests: `tests/supplier_feed_choice_test.php`.
