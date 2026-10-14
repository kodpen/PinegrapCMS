---
name: marketplace-outbound-layer
description: Design rules of the outbound marketplace layer (includes/api/outbound/connectors/): connector contract, three tables, queue, lease and rotation job.
---

## What is the connector contract?
- `includes/api/outbound/connectors/`: `base.php` = contract + shared plumbing; `n11.php` = first provider.
- The contract is not an interface but a TABLE OF FUNCTION NAMES: `mp_connector('n11')` returns it, `mp_can()` asks, `mp_call()` calls. A connector that does not name a function it cannot do is valid.

## Which tables does it use?
- Three tables, ZERO columns added to existing tables: `marketplace_accounts` (one row per connection; a store may hold two accounts on the same provider; credentials are encrypted JSON via `encrypt_string_with_iv`, column `"<ciphertext>:<iv>"`), `marketplace_product_map` (`(account_id, product_id)` UNIQUE), `marketplace_sync_queue` (carries `remote_task_id`).
- A new provider = 0 schema changes. Paraşüt's pattern (a `config` column + identity on the record) is NOT repeated.

## What are the queue rules?
- A network call is NEVER made in the request flow. `pg_marketplace_product_changed()` (in functions.php) is one line placed in the eight places that write stock/price and only writes to the queue. If a new stock/price writer is added, that line must be added there too.
- The queue carries INSTRUCTIONS, not events: while a pending row exists for the same product a second one is not written; two changes seconds apart are a single send.
- Before the network call a 300 s lease (`run_after`) is taken and status stays `waiting`: overlapping cron runs skip, and if the process dies the lease expires. Same pattern as `api_webhook_queue`.
- `api_sync_job.php` runs in rotation (300 s), NOT inline. A webhook is a message, stock is a state; running rarely makes batches bigger and writes less against the per-minute rate limit.

Related: docs/memory/commerce/marketplace-order-import.md
