---
name: workspace-app-mention-unread-line
description: A person who mentions an application that may not read the channel gets a line only they see; the app learns nothing (PR #8494)
---

## What does the writer see?
- A line under the conversation that only they see (`ws-ephemeral`, `mentionNotice()` in workspace.js), from the `notice` of the `ws_send` / `ws_edit` answer: "@Acme cannot read this channel, so it was not told about your message." Several applications: one line listing them. Nothing is posted (PR #8494). Before it the writer got no sign that the mention reached nobody.
- An edit says it only for the applications it newly mentions, as the event itself (workspace-app-mentioned-webhook.md).

## Which applications are listed?
- One that hears mentions (`ws_app_hears_mentions()`) but whose owner does not read the channel (`ws_app_reads_channel()`).
- Not one that hears no mentions at all (switched off, expired, no workspace permission), nor a registry agent the channel allows (reached through the registry, agent_queue.php), nor Claude's or Pinegrap AI's application.
- The @ picker already offers only applications a mention reaches; the line is for tags typed by hand or whose reach was lost later.

## What stays inside?
- The application is not told anything: no event, no name of the channel. The line names the applications only as the message draws them (`ws_app_tag()`), to the person who wrote the tag.

## Where is the code?
- `includes/workspace/messages.php`: the pure `ws_app_mention_unread()` decides; `ws_message_tell_apps()` returns `told` and `unread` (tags); `ws_message_send()` / `ws_message_edit()` pass it on as `apps_unread`; `ws_app_mention_unread_line()` words it. `includes/workspace/actions.php` puts it in `notice`.
- Tests: `tests/workspace_app_mention_test.php` (`test_workspace_app_mention_unread`).
