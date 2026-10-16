---
name: workspace-channel-group-events
description: Webhook events about Workspace channel groups themselves (workspace.channel_group.created/updated/access_changed/deleted): payload, who hears them, what stays quiet
---

## Which events are there?
Since PR #495, four workspace events in `ws_webhook_events()`:
- `workspace.channel_group.created`: a group made in the channel list or with `POST /workspace/channel-groups`. Adds `created_by`.
- `workspace.channel_group.updated`: renamed, new colour, or moved inside another group. `changed` lists `name`, `parent_id`, `color` (that order); `previous` is the group as it was (name, parent_id, path, color). Adds `updated_by`.
- `workspace.channel_group.access_changed`: access on the group given (`added`) or taken back (`removed`), user ids. Adds `changed_by`.
- `workspace.channel_group.deleted`: group taken away. `channel_ids` and `group_ids` moved up to `parent_id`, `access_ended` lists whose access ended. Adds `deleted_by`.

Every payload starts with the group as `GET /workspace/channel-groups` gives it: `id`, `name`, `parent_id` (null at the top), `path`, `color` (hex or null), from `ws_group_event_payload()`. The path is built from the group above, so a removed group keeps the one it had.

## Who hears them?
- Whole-site subscriptions: all of them.
- A subscription narrowed to a group (`channel_group_id`): events of that group and of the groups inside it (`ws_webhook_group_event_groups()`: the group, the chain above `parent_id`). A moved group is also heard by the groups it left (`previous.parent_id`); a removed group also by the groups that moved up out of it (`group_ids`).
- Subscriptions narrowed to the removed group itself are switched off first (`ws_webhook_group_removed()`), so they do not hear its `deleted`.

## What is not announced?
- Saving the form with nothing changed, and a new place among the groups of the same level (`before_id` only): the order is not part of the group object.
- Granting access to someone who already holds it, or revoking from someone who does not.
- The channel memberships access brings or ends, a group above being renamed (only the group itself changed), and a channel moved into or out of a group (`ws_channel_move_group()`): no channel event exists yet.

## Where is the code?
- `includes/workspace/groups.php`, part "Events about the groups themselves": `ws_group_announce()`, `ws_group_announce_update()`, `ws_group_event_changes()`. Called from `ws_group_save()`, `ws_group_move()`, `ws_group_remove()`, `ws_group_access_grant()`, `ws_group_access_revoke()`, which the panel (`actions.php`) and the API (`api_resources.php`) both use, so each change is announced once. An API PATCH that saves and reorders announces once: the save moves the group, the reorder then finds no change.
- Dry runs (`X-Dry-Run`) stop before these functions, so they announce nothing.
- No schema, no scope, no MCP tool, no tr.json string. Tests: `tests/webhook_channel_group_test.php`.

**Why:** the #486 leftover. A subscriber that maps channels to groups had no way to learn that the groups changed.
