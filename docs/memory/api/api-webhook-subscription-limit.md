---
name: api-webhook-subscription-limit
description: How many event subscriptions one application may hold (50), the one function that holds the number, the 409 too_many_webhooks text, and why 50 subscribers of one event drain
---

## How many subscriptions may an application hold?
- 50 (`api_webhook_subscription_limit()` in `includes/api/outbound/webhooks.php`). Architect (2026-11-17): a technical bound, raised from ten for automation platforms, which make one subscription per workflow trigger. PR #10128.
- `api_webhook_subscription_limit_reached($count)` answers true at 50 and above; `api_webhook_create_check()` takes the application's rows counted by `api_webhook_held_count()` (all statuses, stopped ones too) and refuses the next with 409 `too_many_webhooks`, "An application may hold {var:1} subscriptions." with the number from the function.
- The number is written nowhere else: the route description of `POST /webhooks` does not name it. `POST /webhooks` and the Events tab's "New subscription" both go through `api_webhook_create_check()` (`application-access-automation.md`). Test: `webhook_subscription_limit_test.php`.

## Do 50 subscribers of one event drain?
- Yes. One event writes one queue row per subscriber (`api_webhook_enqueue()`), so 50 rows. `api_webhook_dispatch()` is bounded by time, not rows: it claims and sends one row after another until its budget ends (15 s inside `job.php`, 20 s in `api_webhook_job.php`), and each send waits at most 10 s (5 s to connect). Fast receivers drain in one run; slow ones in the next runs, at least one row each run.
