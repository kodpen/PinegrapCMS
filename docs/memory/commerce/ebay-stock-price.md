---
name: ebay-stock-price
description: eBay stock and price through the queue: bulkUpdatePriceQuantity 25 SKUs a call, offer looked up first, price in the site currency to the cent, per-SKU verdicts
module: ebay
---

## How do stock and price reach eBay?
- `mp_ebay_push_stock_price()` (`connectors/ebay.php`, entry `push_stock_price`), called by `mp_sync_send_batch()` like n11's and Trendyol's. With the entry named, `mp_enqueue()` queues `stock_price` rows for eBay accounts.
- Each SKU's offer on the account's site is looked up first (`GET offer?sku=&marketplace_id=`), since the map keeps the listing id in `remote_id`, not the offer id, and a price is set on an offer. Then `POST /sell/inventory/v1/bulk_update_price_quantity`, at most 25 SKUs a call (`pg_ebay_listing_limits()['bulk']`).

## What does one SKU's request carry? (`pg_ebay_bulk_request()`, pure)
- Stock (account sends stock and the product tracks stock): `shipToLocationAvailability.quantity` and the offer's `availableQuantity`, both the shop quantity (never below 0). An untracked product sends no quantity: zero would end the listing.
- Price (account sends prices): the offer's `price` {value, currency of the site}, `pg_ebay_offer_price()` of the row's channel price (`price_major`) and today's rate (`mp_ebay_rate()`), rounded half away from zero to the cent, as the listing is.
- Neither: the SKU is left out and its row closes as done.

## What is refused, and when does it wait?
- Refused on the mapping (`data['refused']`): no offer on this site ("not listed on eBay yet"), no price, no rate to the site's currency, eBay's own message for a SKU answered with an error in the bulk answer (`pg_ebay_bulk_verdicts()`).
- No answer, 429 or 5xx: the rest of the batch is deferred (`data['deferred']`, `retry_after` 60 s for 429, else 30 s); when nothing went through, the batch fails as retryable. Products past the time budget (`PG_EBAY_PUSH_BUDGET`, 20 s) are deferred too.

## What is not verified?
- Built from eBay's Inventory API documentation; the answers in `tests/fixtures/ebay/bulk-*.json` were not recorded from a live account. Tests: `tests/ebay_stock_price_test.php`.
