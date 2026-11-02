---
name: notification-channels-delivery
description: Slack, Teams and Telegram delivery: targets in the sealed notify_channels entry, api_webhook_queue rows with webhook_id 0, the retry ladder, secrets masked
module: notify_channels
---

## Where do the targets live?
- One sealed service entry `notify_channels` (`pg_connect_service_get()` / `_put()`, `connect_accounts` provider `service`): `{"targets": {id: {id, kind, label, url | token + chat_id, codes, events, failure?}}}`. No table or column (decided in #5076, option A). `pg_notify_targets()` reads it once per request; `pg_notify_target_clean()` (pure) keeps only known alert codes and events and refuses an address the service does not give out.
- `failure` = `{status, at, reason}`, noted when a queued row is given up, cleared by a sent row or a successful Test.

## How does a message go out?
- `pg_ops_alert()` (after its once-a-day claim) calls `pg_notify_ops_alert()`; `pg_announce()` calls `pg_notify_announced()`, which settles every event but three without a query: `workspace.app.mentioned` (public channels only, once per message however many apps it names), `workspace.flow.run.failed` (flow named only where the run's words may go out), `workspace.approval.requested` (announced for public channels only).
- `pg_notify_enqueue()` writes one `api_webhook_queue` row per target carrying the code: `webhook_id = 0`, `event` the code, `payload` `{"target": id, "body": built body}`. The URL and token stay sealed.
- `api_webhook_deliver()` hands `webhook_id = 0` rows to `pg_notify_deliver()`: target gone, row dropped; posted with `pg_curl_run()` + `check_address`, no envelope or signature; same ladder (`api_http_should_retry()`, `api_webhook_backoff()`, six attempts); given up, the failure goes on the target and the log names label and kind. Needs the Webhook delivery job (the card says so when it is off).

## How are secrets kept out?
- Every error passes `pg_notify_mask()`: the URL becomes scheme + host, a token `***`, any Slack/Teams path or `bot<token>` cut. `last_error`, the activity log and the card's failure line carry only the status and the provider's short reason (`pg_notify_reason()`). The card never draws an address or token back (`pg_notify_target_where()`); the add fields are password boxes.
- System Status' given-up count reads `webhook_id > 0`; the `webhook_failing` alert reads `api_webhooks` only. The weekly sweep takes chat rows too.

