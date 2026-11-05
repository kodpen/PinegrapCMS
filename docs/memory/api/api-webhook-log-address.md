---
name: api-webhook-log-address
description: Site Log lines about an event subscription name its address as scheme and host only (pg_ops_alert_mask_url()): API change and secret renewal, panel stop/resume and removal, given-up delivery
---

## How does a Site Log line name a subscription's address?
- Always through `pg_ops_alert_mask_url()`: `https://hooks.slack.com/…`, never the stored URL. The Site Log is read by every manager and by API applications holding `site_log:read`, and Slack, Discord and Zapier addresses carry their secret in the path or query.
- Lines covered: the API's `api_webhooks_update()` and `api_webhooks_rotate_secret()` (`includes/api/resources/webhooks.php`), the panel's stop/resume and removal (`api_settings.php`), and the given-up delivery (`api_webhook_given_up_note()`, `api-webhook-queue.md`).
- The full address stays where its owner reads it: the application's own `GET /webhooks` and the notices on the Application Access screen (roles 0-1).

## What was the symptom?
- A change, a secret renewal, a stop/resume or a removal wrote the whole address, path token included, into the Site Log, while only the given-up line was masked.

## Which test guards it?
- `tests/webhook_log_address_test.php`: every `log_activity()` naming an API event subscription in those two files passes the address through the mask.
