---
name: marketplace-outbound-layer
description: Design rules of the outbound marketplace layer (includes/api/outbound/connectors/): connector contract, three tables, queue, lease and rotation job.
---

## What is the connector contract?
- `includes/api/outbound/connectors/`: `base.php` = contract + shared plumbing; `n11.php` = first provider; `trendyol.php` = test, category tree, attributes, listing create, stock and price, batch result so far (`trendyol-api-facts.md`, `trendyol-listings.md`, `trendyol-stock-price.md`).
- The contract is not an interface but a TABLE OF FUNCTION NAMES: `mp_connector('n11')` returns it, `mp_can()` asks, `mp_call()` calls. A connector that does not name a function it cannot do is valid.
- `push_stock_price` may answer `data['refused']` (product id => reason): the queue fails those rows on their own reason at once and writes it on the mapping. `task_id` '' = nothing was sent, the rest close as done. A retryable error may carry `data['retry_after']` (seconds, capped at an hour), which `mp_sync_batch_failed()` waits instead of the backoff.
- Nothing is queued for what a connector cannot do: `mp_enqueue()` skips accounts whose provider lacks `push_stock_price`, and the screen hides "List on the marketplace" without `create_listings` (otherwise rows fail six times or wait for ever).

## How are a provider's credential fields declared?
- `mp_providers()[...]['fields']`: text box by default (`secret` → password); `'type' => 'select'` with `options` and `default` (Trendyol's live/test environment). `mp_provider_credentials()` reads a form and only ever stores one of a select's options; a select is never "required". Optional `help` is drawn under the fields; `shipment_template` shows the account's cargo template field; `key => 'barcode'` (`mp_provider_keys_by_barcode()`) makes the barcode the product's key. The new-account modal draws every provider's block and disables the hidden ones.
- Trendyol's functions take an optional `$transport` (signature of `api_http_request()`); `mp_call()` never passes it, tests pass a fixture recorder.

## Which tables does it use?
- Three tables, ZERO columns added to existing tables: `marketplace_accounts` (one row per connection; a store may hold two accounts on the same provider; credentials are encrypted JSON via `encrypt_string_with_iv`, column `"<ciphertext>:<iv>"`), `marketplace_product_map` (`(account_id, product_id)` UNIQUE), `marketplace_sync_queue` (carries `remote_task_id`).
- A new provider = 0 schema changes. Paraşüt's pattern (a `config` column + identity on the record) is NOT repeated.

## What are the queue rules?
- A network call is NEVER made in the request flow. `pg_marketplace_product_changed()` (in functions.php) is one line placed in the eight places that write stock/price and only writes to the queue. If a new stock/price writer is added, that line must be added there too.
- The queue carries INSTRUCTIONS, not events: while a pending row exists for the same product a second one is not written; two changes seconds apart are a single send.
- Before the network call a 300 s lease (`run_after`) is taken and status stays `waiting`: overlapping cron runs skip, and if the process dies the lease expires. Same pattern as `api_webhook_queue`.
- `api_sync_job.php` runs in rotation (300 s), NOT inline. A webhook is a message, stock is a state; running rarely makes batches bigger and writes less against the per-minute rate limit.

Related: docs/memory/commerce/marketplace-order-import.md
