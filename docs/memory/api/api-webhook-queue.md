---
name: api-webhook-queue
description: Outgoing webhook queue (includes/api/outbound/webhooks.php): which subscriptions get an event, held rows for a failing subscription, the retry ladder, retry and the atomic claim of a row
---

## Who gets an event?
- `api_webhook_enqueue()` reads subscriptions whose status is `active` or `failing` and whose application is active; `disabled` gets nothing and its queued rows are dropped at delivery. Narrowed and addressed events: `webhook_channel_group_test.php`, `workspace_app_mention_test.php`.
- For `failing` the row is written on hold (`api_webhook_queue_hold()`): `attempts = 0`, `next_attempt_at = 0`, which the dispatcher never picks up. Symptom (#914, PR #1077): failing subscriptions were left out of the queue, so every event while the address was down was lost and Retry answered `requeued: 0`.

## How is the queued body written?
- `api_webhook_body()` writes the envelope (`event`, `created_at`, `data`) with `pg_json_encode_answer()`: bytes that are not UTF-8 become U+FFFD; a payload with INF or NAN gives null and nothing is queued. Symptom: plain `json_encode()` answered false on a broken byte and the row went out signed with an empty body. Test: `webhook_queue_test.php`.

## How does a row leave the queue?
- Dispatcher (`api_webhook_dispatch()`, general job and `api_webhook_job.php`): picks a due row, claims it with `UPDATE … next_attempt_at = now + 120 WHERE id = … AND next_attempt_at = <the value it read>` and skips the row unless exactly one row changed, so two overlapping runs do not deliver one row twice.
- Delivered: deleted, the subscription goes `active`.
- Failed: backoff ladder, six attempts (`api_http_should_retry()`: only 0/408/429/5xx retry); out of attempts or a non-retryable answer gives `next_attempt_at = 0` with `attempts > 0` (given up) and the subscription becomes `failing`.
- The activity log line for a given-up delivery (`api_webhook_given_up_note()`) names the address as scheme and host only (`pg_ops_alert_mask_url()`): the log is read by managers and by API apps with `site_log:read`, and Slack, Discord and Zapier carry the secret in the path. It used to log the full URL. Test: `webhook_queue_test.php`.
- Retry (panel Events tab "Send again", `POST /webhooks/{id}/retry`): subscription `active`, every row back to `attempts = 0`, `next_attempt_at = now`, held and given-up alike.
- Rows at `next_attempt_at = 0` (held or given up) older than a week are swept.

## Do held rows go out on their own?
- No. Only Retry sends them. A successful delivery (a `webhook.test` to a fixed address, a leftover ladder row), Resume in the panel or `PATCH status=active` leaves them held, because a new address must not receive events meant for the old one unasked (the rule in the `api_webhooks_update()` comment and the `PATCH /webhooks/{id}` description).
- So the panel shows "Send again" while held rows exist (`held` count in the queue summary), not only while the subscription is failing or rows were given up. Otherwise Stop then Resume, `PATCH status=active` or an enqueue racing a Retry leaves rows that nobody can send and that are swept after a week.

## How are they counted?
- Held rows count as waiting: API `pending` (`next_attempt_at > 0 OR attempts = 0`) and the panel's waiting count; given up is `next_attempt_at = 0 AND attempts > 0`. Ops alert `webhook_stuck` counts both.
- The Events tab error line comes from the newest row with `attempts > 0`, as the API's `last_error` does. A held row carries no error, so reading the newest row of all hid the 401/404 reason as soon as one event was held.

## What are the `webhook_id = 0` rows?
- Slack, Teams and Telegram messages (`platform/notification-channels.md`, #5076): no subscription, the target in a sealed entry, the body sent as built. `api_webhook_deliver()` passes them to `pg_notify_deliver()` before the subscription lookup. Subscription paths filter by `webhook_id` and never touch them; System Status' given-up count reads `webhook_id > 0`.
