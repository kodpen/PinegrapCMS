---
name: marketplace-sync-queue-lease
description: How a stock and price batch claims its queue rows (lease token in remote_task_id), why a change during a push gets a row of its own, and how a product is sent once per batch.
---

## How does a pass claim its rows?
- `mp_sync_send_batch()` picks up to 500 due ids, then claims them with one `UPDATE … SET run_after = now + 300, remote_task_id = 'lease:<token>' WHERE id IN (…) AND status = 'waiting' AND run_after <= now`. It reads back only the rows carrying its own token (`mp_sync_lease_token()`), and only then reads the products.
- Status stays `waiting`: a pass that dies leaves the lease to expire, and the next pass claims the rows again with its own token.
- An overlapping cron run finds `run_after` moved and claims none of them, so a batch is never sent twice.

## What happens to a change while a batch is out?
- `mp_enqueue()` folds a change only into a waiting row with `remote_task_id = ''` (`mp_enqueue_absorbs()`). A leased row read the product before the change, so the change gets a new waiting row of its own.
- A retryable failure (`mp_sync_batch_failed()`) and a deferral clear the token: those rows wait to be read again and take new changes. `sent` rows hold the marketplace's task id instead, `done` rows hold ''.
- Two rows of one product in a batch (a dead pass's row and a later one) send the product once and close together (`mp_sync_first_per_product()`).

## What did it fix?
- Before, the lease was a plain `UPDATE` of `run_after` after the rows and products had been read. A sale during a push was folded into the row in flight, which was then marked sent with the old stock: the marketplace kept the old quantity until the next change to the product and could oversell. Two overlapping runs could also both read the same rows before either leased them.
- Tests: `tests/marketplace_sync_lease_test.php` (pure parts); the queries themselves were not run against a database.

Related: docs/memory/commerce/marketplace-outbound-layer.md
