---
name: connections-chat-notify-page
description: Slack's, Microsoft Teams' and Telegram's pages on Connections (CX11j2): each lists its chat notification targets and adds one; the Chat notifications card only leads there
module: notify_channels_page, connections_screen
---

## Why did the targets move?
- Architect (2026-11-23, agent channel message 12293): "They move. Slack, Teams and Telegram are registry services already and poll 146 makes Connections the one place where an outside service is set up. Each gets a service page that lists its targets, several a service, the way the Mailboxes and SMS pages list several accounts."
- "A target's whole block moves with it (name, address or token, alert codes, Workspace events, Delete, 'Save and send a test', Add a target). The Chat notifications card becomes the line that leads to Connections. Storage, pg_notify_test() and the queue do not change. No new kind of page." Step CX11j2 of `plans/connections-merges-build.md`, #11215.

## What does a chat service's page show?
- `connections.php?service=slack`, `teams`, `telegram`. "Connection details" (`pg_notify_page_details()`): the card's intro, the Webhook delivery job warning while it is off, one block per target of that service, then Add a target.
- A block is its own form (`pg_notify_page_target_form()`): Name, the service and where it sends (scheme and host, never the address or token), the last failure, the alert codes in Operations alerts, Sales watch, Deliveries (`pg_notify_alert_groups()`), Workspace events; "Save" (filled), "Save and send a test", "Delete" (asks first).
- Add a target (`pg_notify_page_add_form()`): no Service select, the page is the service. Slack and Teams take "Webhook address", Telegram "Bot token" and "Chat ID" (password boxes); every alert code ticked.
- A site that cannot seal gets the encryption note and no form. "Where it is used": the Alerts card and the Chat notifications card, Connected while the service has a target.

## What does a write do?
- Actions `notify_save`, `notify_add`, `notify_delete` (`connections_return` = the service). `pg_notify_page_apply()` (pure, tested) touches only a target of that service; another service's id is ignored. A new address or token the service does not give out is marked on `notify_new_url` / `notify_new_token`, nothing written.
- Only a change is written (`pg_notify_targets_save()`). "Save and send a test" saves, then `pg_notify_test()` at once outside the queue; its answer is a notice or an error.
- Gate: who may open Site Settings › Updates and maintenance (`connections_chat_notify_may_manage()`), card `details` with `$can['chat_notify']` of `pg_connections_screen_page_cards()`.

## What does the Chat notifications card show now?
- `pg_notify_settings_card_html()`: the intro, a line per service with "1 target" / "{n} targets" or Not connected and "Manage in Connections" / "Connect in Connections", then where the targets are set. No field: `maintenance.save.php` writes no target. Card id `pgset-chat-notify` stays.

## What did not change?
- The sealed `notify_channels` entry, the queue rows, the masking (`platform/notification-channels-delivery.md`). Registry rows: `place` `connections.php`, `found_at` `#settings/maintenance/pgset-chat-notify`, group SMS and messaging; no service added. No data step, table or column.

Tests: `tests/connections_chat_notify_test.php`, `tests/notify_channels_test.php`.
