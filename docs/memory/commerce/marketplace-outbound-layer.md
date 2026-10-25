---
name: marketplace-outbound-layer
description: Design rules of the outbound marketplace layer (includes/api/outbound/connectors/): connector contract, three tables, queue, lease and rotation job.
---

## What is the connector contract?
- `includes/api/outbound/connectors/`: `base.php` = contract + shared plumbing; `n11.php` = first provider; `trendyol.php` = test, category tree, attributes, listing create and approval read (`listing_status`), stock and price, batch result, order pull, stage, Picking accept and order webhook (the `trendyol-*.md` files, `marketplace-webhook-receiver.md`).
- The contract is not an interface but a TABLE OF FUNCTION NAMES: `mp_connector('n11')` returns it, `mp_can()` asks, `mp_call()` calls. A connector that does not name a function it cannot do is valid.
- A new connector = `connectors/<key>.php` + its `mp_providers()` row (`file`, `fields`); every entry is optional but `test_credentials` (list in `base.php`'s header). `approve_lines($account, $line_ids, $package)` gets the imported package third: Trendyol needs its id and line quantities. A connector reports; it never writes the catalogue.
- `push_stock_price` may answer `data['refused']` (product id => reason): the queue fails those rows on their own reason at once and writes it on the mapping. `task_id` '' = nothing was sent, the rest close as done. A retryable error may carry `data['retry_after']` (seconds, capped at an hour), which `mp_sync_batch_failed()` waits instead of the backoff.
- Nothing is queued for what a connector cannot do: `mp_enqueue()` skips accounts whose provider lacks `push_stock_price`, and the screen hides "List on the marketplace" without `create_listings` (otherwise rows fail six times or wait for ever).
- The same for orders: without `pull_orders` the account screen draws neither the "Bring orders in" switch nor the "Imported orders" card, only "Orders are not brought in from {var:1} yet."; the auto-accept switch needs `approve_lines` too. A new account writes `pull_orders` from `mp_can()` (the column defaults to 1). Before, a Trendyol account showed import on and an empty card while the job skipped it silently (#981, PR #1240).

## How are a provider's credential fields declared?
- In `mp_providers()[...]['fields']`: marketplace-provider-fields.md.

## Which tables does it use?
- Three tables, ZERO columns added to existing tables: `marketplace_accounts` (one row per connection; a store may hold two accounts on the same provider; credentials are encrypted JSON via `encrypt_string_with_iv`, column `"<ciphertext>:<iv>"`), `marketplace_product_map` (`(account_id, product_id)` UNIQUE), `marketplace_sync_queue` (carries `remote_task_id`).
- A new provider = 0 schema changes. Paraşüt's pattern (a `config` column + identity on the record) is NOT repeated.

## What are the queue rules?
- A network call is NEVER made in the request flow. `pg_marketplace_product_changed($product_id, $priority = 5)` (`includes/fn/ecommerce.php`) only writes to the queue (`mp_enqueue()`; priority 1 goes on the next pass) and is one line at every place that writes stock or price: checkout, barcode screens, product screen and mass edit, file manager edits, local sale, API products/groups/inventory, Workspace changes, ERP stock and counts, marketplace import. A new stock or price writer adds that line too.
- The queue carries INSTRUCTIONS, not events: while a pending row exists for the same product a second one is not written; two changes seconds apart are a single send.
- Before the network call a 300 s lease (`run_after`) is taken and status stays `waiting`: overlapping cron runs skip, and if the process dies the lease expires. Same pattern as `api_webhook_queue`.
- `api_sync_job.php` runs in rotation (300 s), NOT inline. A webhook is a message, stock is a state; running rarely makes batches bigger and writes less against the per-minute rate limit.

Related: docs/memory/commerce/marketplace-order-import.md, docs/memory/commerce/marketplace-provider-fields.md
