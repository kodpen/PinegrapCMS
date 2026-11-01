---
name: merchant-center-push
description: Per-product push to Google Merchant Center on the outbound marketplace layer (#4389, #4423): managed provider, mapping from the feed, queue, supplemental source
module: merchant_center_push
---

## Why the marketplace layer and not a table of its own? (#4389)
- Decided (architect, 2026-11-01): the outbound layer (`marketplace-outbound-layer.md`) already has accounts, one mapping row per product, a queue with lease and backoff, refused rows written on the mapping, and `pg_marketplace_product_changed()` at every stock or price writer. A `merchant_product_sync` table would copy those; columns on `products` would widen the hot table.
- The credentials JSON of `marketplace_accounts` is opaque per provider: `{connection_id, account, country}` points at the connected Google account (purpose `merchant_center`); `pg_connect_api()` renews the token.

## How is the account row made? (#4423)
- `mp_providers()['merchant_center']`: no `fields`, `'managed' => true`, asked with `mp_provider_is_managed()`. `pg_merchant_register()` calls `pg_merchant_account_write()` once the feed and the supplemental source are registered: the row is inserted `active` with push_stock / push_price on and pull_orders off, or updated; a different Merchant Center account than the row's clears its mapping and queue first. "Stop using Merchant Center" on the card calls `pg_merchant_account_remove()` (row, mapping, queue).
- Marketplaces screen: the provider is not in "add account" (create refuses it), its drawer shows a note linking to `settings_commerce.php#pgset-feeds` instead of credentials and Remove; a posted delete is refused with that note. Name, pause and the two push switches stay editable.

## Which products are mapped?
- `pg_merchant_map_refresh()`: one row per product `pg_product_feed_products()` yields and `pg_product_feed_google_row()` accepts; `remote_code` = product id (the feed's offerId). Run on account write and by `product_feed_job.php` daily; a failed catalogue read changes nothing; a product that left the feed loses its row and queue rows.

## How is a change sent?
- Connector `connectors/merchant_center.php`: `push_stock_price` posts `productInputs:insert?dataSource=<supplement>` one product at a time with the feed's values (`pg_merchant_push_item()`: stored price, offer price only when lower, feed availability; switched off or flagged out of stock = out of stock), only the attributes the account's switches allow (`pg_merchant_push_input()`). Not `mp_product_row()`'s tax-inclusive price: Google holds the feed's.
- Synchronous answers, so no task id: the batch answers `pushed` (sync writes `last_pushed` on the mapping), `refused` (4xx but 401/408/429, `pg_merchant_push_verdict()`), and `deferred` (not sent after a failure or the 15 s budget, `PG_MERCHANT_PUSH_BUDGET`): sync.php puts those back to waiting after `retry_after` (60 s on 429, 30 s otherwise) without counting an attempt. A failure on the first product is a retryable batch error (counted, backoff).
- `listing_status`: `products.get` on `{lang}~{country}~{offerId}`; disapproving issues = `rejected` (row in error, not pushed, read again until approved), found = `approved` with the key as `remote_id`, 404 = unknown (`pg_merchant_listing_state()`). Approved rows are not read again; the card's account issues cover them.

## What does the product screen do?
- A "Google Merchant Center" card in edit mode once an account row exists (`pg_merchant_product_card()`): last push time, queued, last error, or "not in the Google feed". "Send to Merchant Center" posts a form drawn outside the product form; `edit_product.php` calls `pg_merchant_product_push()` = `mp_enqueue($id, 'stock_price', 1)`, no network call, and saves nothing else.

## What is not verified?
- Tests: `tests/merchant_center_push_test.php` (pure parts). Live Merchant API calls (insert, products.get, rate limits) need a Merchant Center account; not run.

Related: docs/memory/commerce/merchant-center.md, docs/memory/commerce/marketplace-outbound-layer.md
