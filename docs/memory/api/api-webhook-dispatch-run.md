---
name: api-webhook-dispatch-run
description: One run of the webhook dispatcher: a receiver that failed is left for the rest of the run, and a delivery's answer never switches a disabled subscription back on
---

## What does one run do with a receiver that fails?
- `api_webhook_dispatch()` picks due rows one by one (`api_webhook_due_row_sql()`). After a failed attempt to a subscription (retry scheduled or given up), its other rows are left out of the run's next picks (`webhook_id NOT IN (…)`); they stay due and go on a later run. `api_webhook_deliver($row, $reached)` reports the failure.
- Why: a receiver that times out costs 10 s a row, and the run has 15 s (general job) or 20 s (`api_webhook_job.php`). Before, one dead address with many waiting rows used the whole run on every tick and every other subscription's events waited behind it.
- Chat rows (`webhook_id = 0`, Slack, Teams, Telegram) are never left out: one id stands for every target.
- Still so: each run spends one timeout per dead subscription, since its oldest row is due first again. Two dead receivers can still fill the general job's 15 s.

## May a delivery's answer change a disabled subscription?
- No. The status written after a delivery (`active` with `last_success`, or `failing` after the last attempt) goes through `api_webhook_status_after_delivery_sql()`, whose `WHERE` keeps `status <> 'disabled'`.
- Symptom it fixed: Stop on the Events tab (or a channel group taken away, `includes/workspace/groups.php`) pressed while a delivery was on its way was undone by that delivery's answer, and the subscription went on receiving events.
- Test: `tests/webhook_queue_test.php`.
