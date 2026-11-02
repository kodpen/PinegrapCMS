---
name: notification-channels
description: Chat notification channels (includes/fn/notify_channels.php): Slack, Teams and Telegram targets, the message shape, one pure body builder per kind, target checks; delivery path waiting on a decision
module: notify_channels
---

## What is it?
- Slack incoming webhooks, Microsoft Teams incoming webhooks or Workflows, and a Telegram bot as targets of operations alerts (`pg_ops_alert_codes()`) and chosen Workspace notifications (a mention of the site's app, a flow run failed, an approval requested). Plan step SF8 of `plans/small-features-2026-11.md`.

## What is built?
- `pg_notify_message()` (pure): title on one line, text, an http(s) address or '', level `error`/`warning`/`info`, site name. `pg_notify_alert_message()` makes one from an alert code (error type: `error`, else `warning`).
- One builder per kind, pure: `pg_notify_slack_body()` (text fallback plus Block Kit: section, context, a button; mrkdwn `&`, `<`, `>` escaped), `pg_notify_teams_body()` (a `message` carrying one Adaptive Card 1.4, plain text, an Open action), `pg_notify_telegram_body()` (sendMessage in HTML mode, `<`, `>`, `&` escaped; over 4096 characters it is cut as plain text so no tag stays open).
- `pg_notify_target_valid()`: Slack only `https://hooks.slack.com/services/…`; Teams only hosts ending `.webhook.office.com`, `.logic.azure.com`, `.api.powerplatform.com`; no user part, no port; Telegram a bot token `<digits>:<key>` and a chat id (number or `@name`). `pg_notify_request()` answers the address, JSON body and headers, null for a target that fails the check, so nothing is sent to an address that is not the service's.
- Tests: `tests/notify_channels_test.php` (every alert code through every kind, escaping, target checks).

## What is still open?
- How a message is delivered and retried: the outbound queue (`api/api-webhook-queue.md`) only knows `api_webhooks` rows (an application, a plain URL, the signed Pinegrap envelope), while a chat target needs its own body and a sealed URL or token. Asked as an architect question from the SF8 PR; the settings card, the Test button and the senders wait for its answer.
