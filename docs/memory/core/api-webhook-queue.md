---
name: api-webhook-queue
description: Outgoing webhook queue (includes/api/outbound/webhooks.php): which subscriptions get an event, held rows for a failing subscription, the retry ladder, retry and the atomic claim of a row
---

## Who gets an event?
- `api_webhook_enqueue()` reads subscriptions whose status is `active` or `failing` and whose application is active; `disabled` gets nothing and its queued rows are dropped at delivery. Narrowed and addressed events: `webhook_channel_group_test.php`, `workspace_app_mention_test.php`.
- For `failing` the row is written on hold (`api_webhook_queue_hold()`): `attempts = 0`, `next_attempt_at = 0`, which the dispatcher never picks up. Symptom (#914): failing subscriptions were left out of the queue, so every event while the address was down was lost and Retry answered `requeued: 0`.

## How does a row leave the queue?
- Dispatcher (`api_webhook_dispatch()`, general job and `api_webhook_job.php`): picks a due row, claims it with `UPDATE … next_attempt_at = now + 120 WHERE id = … AND next_attempt_at = <the value it read>` and skips the row unless exactly one row changed, so two overlapping runs do not deliver one row twice.
- Delivered: deleted, the subscription goes `active`, and if it was `failing` its held rows are released (`next_attempt_at = now`).
- Failed: backoff ladder, six attempts (`api_http_should_retry()`: only 0/408/429/5xx retry); out of attempts or a non-retryable answer gives `next_attempt_at = 0` with `attempts > 0` (given up) and the subscription becomes `failing`.
- Retry (panel Events tab, `POST /webhooks/{id}/retry`): subscription `active`, every row back to `attempts = 0`, `next_attempt_at = now`, held and given-up alike.
- Rows at `next_attempt_at = 0` (held or given up) older than a week are swept.

## How are they counted?
- Held rows count as waiting: API `pending` (`next_attempt_at > 0 OR attempts = 0`) and the panel's waiting count; given up is `next_attempt_at = 0 AND attempts > 0`. Ops alert `webhook_stuck` counts both.
