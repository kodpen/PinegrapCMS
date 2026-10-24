---
name: trendyol-stock-price
description: Trendyol stock and price push: price-and-inventory batch by barcode, what is left out before the call, the 15-minute repeat refusal, status-less batch result; webhooks carry no stock.
---

## Which call sends stock and price?
- `POST /integration/inventory/sellers/{sellerId}/products/price-and-inventory`, marked "V1-V2", body `{"items":[{barcode, quantity?, salePrice?, listPrice?}]}`, at most 1000 items, answers `batchRequestId`. Read with the same batch-requests endpoint as listings (results kept 4 hours). Docs read 2026-10-20 (page modified 2026-08-07).
- Only `barcode` is required: stock alone or prices alone may be sent, so an account with "send prices" off sends quantities and the Trendyol panel prices stand.

## What does the connector send, and what does it leave out?
- `mp_trendyol_stock_body()`: barcode = the mapping's `remote_code`; `quantity` only for a stock-tracking product, capped at their maximum of 20,000 (never refused for more); both prices = the checkout's tax-inclusive price (`mp_product_price()`, `marketplace-price.md`), rounded to 2 decimals (the shop has one price; compare-at price is not used, the same as listing create).
- Left out and answered in `data['refused']` (product id => reason): a barcode against their rules, a second product under a barcode already in the batch, a product without a price. The queue fails those rows on their own reason and writes it on the mapping row (the mapping stays active).
- A product with nothing to say (untracked, prices off) is not in the request. When nothing is left, `task_id` is '' and the queue closes the rest as done.
- A shop not in TRY cannot send prices (the request has no currency field): the batch is refused with a hint to turn prices off.

## What is the 15-minute rule?
- An identical body within 15 minutes is refused with "15 dakika boyunca aynı isteği tekrarlı olarak atamazsınız!" (HTTP status not documented). It happens when a batch is resent after a timeout their side accepted. `mp_trendyol_is_repeat_refusal()` matches it; the connector answers retry with `data['retry_after'] = 900` and `mp_sync_batch_failed()` waits that long instead of the 2-minute backoff (which would spend all six attempts inside the window).

## How is the batch result read?
- A stock and price batch has no top-level `status` ("Batch status alanı tarafınıza dönmeyecektir"); it is finished when every line has `SUCCESS`/`FAILED` and, when `itemCount` is given, as many lines are listed as it says. A batch id alone does not mean success (their FAQ).
- `failureReasons` texts are not documented (examples are all empty); stored as given.

## What limits apply?
- Inventory & price write: 350–2000 requests/minute by listing-limit tier, one shared pool. Price changes: 30 per minute per barcode, refused per line in the batch result. The 5-minute queue with one waiting row per product stays far below both.

## What is still unknown?
- Whether a stock line for a listing still awaiting approval is refused. If it is, the line's refusal puts the mapping in `error` and it stops syncing until re-matched (n11 rule, `mp_sync_apply_results()`); step 2b's approval read is where it would be set active again.

## What do webhooks carry?
- Order package changes only, no stock, price or product events, so they change nothing here. The receiver and Trendyol's webhook facts: `marketplace-webhook-receiver.md`.

Related: docs/memory/commerce/trendyol-listings.md, docs/memory/commerce/marketplace-outbound-layer.md
