---
name: workspace-api-changes-reach
description: Which proposed changes GET /workspace/changes, apply and dismiss reach: readable channels, the discussions the owner is in, tasks they see; the assistant requests read channels only
---

## Which changes does the API reach?
- `ws_api_changes_reach_sql()` (api_resources.php), ANDed by GET /workspace/changes and by `ws_api_change_or_404()`, which apply and dismiss call first: changes in the channels `ws_api_readable_channel_ids()` gives, in the discussions `ws_api_readable_thread_ids()` gives, and those waiting on the tasks the owner sees.
- `?channel_id=` takes a discussion's id too (`ws_api_channel_or_404()` already let it pass).

## Which discussions count?
- Those the owner is a member of, opened in a readable channel (`ws_api_thread_ids_sql()` over `ws_threads` and `ws_channel_members`), each then read with `ws_can_read_channel()`. For the Claude application, only where `ws_claude_channel_allowed()` (a discussion follows its channel).

## Why, and what was the symptom?
- `ws_timeline_channels()` leaves out kind `thread`, so a discussion's changes listed nothing and apply/dismiss answered 404, while the panel draws their cards in a discussion as in a channel. A team device client needed them (#10346).
- GET /workspace/assistant-requests keeps `ws_api_readable_channel_ids()` alone: the issue asked only for the changes. On an older site a discussion's list stays empty; no detection needed.
