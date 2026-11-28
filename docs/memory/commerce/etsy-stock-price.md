---
name: etsy-stock-price
description: Etsy stock and price through the queue: inventories read in bulk, one call a listing with its whole products array, price in the shop's currency, paced by limit headers
module: etsy
---

## How do stock and price reach Etsy?
- `mp_etsy_push_stock_price()` (`connectors/etsy.php`, entry `push_stock_price`), called by `mp_sync_send_batch()`. With the entry named, `mp_enqueue()` queues `stock_price` rows for Etsy accounts.
- A product's listing is its mapping's `remote_id`, which `create_listings` writes (`etsy-listings.md`). A product without one is refused, "not listed on Etsy yet".
- The listings are read with their inventory, 25 a call (`GET /listings/batch?listing_ids=…&includes=Inventory`, `pg_etsy_inventory_read_max()`); a listing the answer carries without `inventory` is read on its own (`GET /listings/{id}/inventory`; 404 refuses: "not on Etsy any more").
- Then one `PUT /listings/{id}/inventory` a listing whose values changed. Etsy replaces the whole products array, so the products not in the batch go back as Etsy has them (`pg_etsy_inventory_update()`, pure): no ids, deleted products and offerings left out, price as a decimal, the `…_on_property` lists kept.

## What changes on a product?
- Stock (account sends stock and the product tracks stock): the offering's quantity, capped at 999, `is_enabled` only with stock, as a new listing's.
- Price (account sends prices): `pg_etsy_price()` of the row's `price_major` and the rate to the listing's currency (its offerings' `currency_code`, the shop's), to the cent.
- Values Etsy already has: no call, the products count as pushed. A SKU the listing does not have is refused: "Send the article again from Listings."

## How is it paced?
- Every Etsy call asks for its answer's headers (`response_headers` in `mp_etsy_api_call()`). `pg_etsy_pace()`: `x-remaining-this-second` 0 sleeps a second before the next call; a 429 stops the batch for its `retry-after` (else 60 s); `x-remaining-today` 0 stops it for an hour (the queue's longest wait).
- Stopped, 5xx or no answer: that listing and the rest are deferred (`data['deferred']`, `retry_after` as above, 30 s for a fault). With nothing pushed and nothing refused, the batch fails as retryable with that `retry_after`. Past `PG_ETSY_PUSH_BUDGET` (20 s) the rest is deferred.
- Another error answer refuses that listing's products with Etsy's message.

## What is not verified?
- Built in ET4 of `plans/sales-channels-global.md` from Etsy's documentation; fixtures `tests/fixtures/etsy/listings-batch-inventory.json`, `error-rate-limit.json`; tests `tests/etsy_stock_price_test.php`. Not settled: inventory included by `getListingsByListingIds` (the single read covers it), header names as documented.
