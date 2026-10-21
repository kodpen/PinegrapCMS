---
name: workspace-webhook-channel-groups
description: A webhook subscription narrowed to one group of Workspace channels (api_webhooks.channel_group_id, schema 10.76): what it hears, the checks, what removing the group does
---

## What does a narrowed subscription do?
- `POST /webhooks`, `PATCH /webhooks/{id}` and `POST /webhooks/{id}/update` take `channel_group_id` since PR #486. 0, null or left out is the whole site, as every subscription was before. A group's id narrows the subscription to the workspace events of the channels in that group and in the groups inside it (any depth).
- Body, signature (`X-Pinegrap-Signature`), queue, retries, test and secret rotation are the ordinary ones: the filter only decides which subscriptions an event is queued for. The answer (`Webhook`) carries `channel_group_id` (null when not narrowed).
- The channel's group is read when the event happens (`ws_webhook_event_groups()`), so a channel moved into the group is heard from then on, one moved out is not.
- Events about the groups themselves (`workspace.channel_group.*`, PR #495) are matched by the group, not a channel: see `workspace-channel-group-events.md`.
- Which channel: `channel_id` in the event, or `task.channel_id` for `workspace.task.note_added`; for an event of a discussion (`thread.channel_id`, `workspace.app.mentioned`) the channel the discussion is about, since a discussion is in no group (PR #809, `ws_webhook_event_channel_id()`). A task with no channel is heard only by whole-site subscriptions.
- Privacy is unchanged: a private channel's message is never announced as `workspace.message.created`; task events of private channels go out as before; a mention reaches only the application mentioned (workspace-app-mentioned-webhook.md).

## What is refused?
- 422 `channel_group_id`: a group the application owner does not see in the channel list (`ws_groups_for()`; non-staff see only groups holding a channel they see), answered like a missing one. Also with the workspace off.
- 422 `events`: any non-workspace event (`order.created` etc.) on a narrowed subscription, on create and on every change of events or group.
- 503: the column is missing (upgrade not run). An array value is 422 like any int field.

## What happens when the group is removed?
- `ws_group_remove()` switches the subscriptions narrowed to it off (`status = disabled`) and writes a Site Log line with the count. They are not moved to the parent group: channels move up on removal, and a subscription for one department would start hearing the whole company.
- Switching one back on (`status: active`) checks the group again: 422 until it names a group that exists or 0.
- The panel's API screen (event subscriptions card) shows a "Channel group: <name>" chip, or "a group that was removed".

## Where is the code?
- Column: `includes/migrations/2026.4.10.php`, `upgrade_2026_4_10_webhook_channel_group()` (10.76). Code probes it with `api_webhook_channel_groups_ready()` (`pg_schema_has`).
- Filter: `api_webhook_enqueue()` in `includes/api/outbound/webhooks.php`; checks: `api_webhook_clean_channel_group()` in `includes/api/resources/webhooks.php`; workspace side in the "Event subscriptions narrowed to a group" part of `includes/workspace/groups.php`.
- No new scope, no MCP tool. Tests: `tests/webhook_channel_group_test.php`.

**Why:** queue item q13 (channel-group webhooks). Before this a subscriber got every workspace event of the site and had to map channels to groups itself.
