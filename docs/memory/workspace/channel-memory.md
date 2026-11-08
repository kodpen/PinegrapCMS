---
name: channel-memory
description: A channel's memory (ws_channel_memory) and the decision mark by a person or an agent (ws_messages.marked_by_kind): rows, routes, the index in the brief, the Memory tab
---

## What holds a channel's memory?
- `ws_channel_memory` (step 11.0.173, `upgrade_2026_11_0_ws_channel_memory()`): title (150), text (4000), tags (comma list, lower case, at most 10 of 32 characters: `ws_memory_tags()`), pinned, `expires_at` (0 never), `author_kind`/`author_id` and `updated_by_kind`/`updated_by_id` (`user` or `app`, the same pair as `ws_messages.sender_kind`/`sender_id`). Every channel has one, an agent channel or not; a discussion's rows go with it in `ws_threads_purge()`.
- Read with `ws_memory_ready()` first (files land before the upgrade). Checks: `ws_memory_check()` (panel and API share it; fields left out keep their value; an unchanged past expiry is not refused again) over the pure `ws_memory_entry_fault()`. Writes `ws_memory_save()`, `ws_memory_delete()`. Whoever may post in the channel writes and deletes entries.
- `ws_memory_sweep()` in `workspace_recurring_job.php` deletes entries expired more than 30 days ago and those of channels that are gone.

## What does an agent's brief carry?
- The index only, never texts (owner 2026-11-08, #8188): `ws_memory_digest()` gives "- #id title [tags]" lines, pinned first then the most recently changed, expired left out, then the channel's decision lines, within `WS_MEMORY_DIGEST_MAX` characters; a line that does not fit is left out whole with every line after it, and a count closes it. The agent reads entries with `workspace.memory.get`.
- `ws_memory_brief()` builds it from the rows; `ws_ai_memory_parts()` puts it in a `channel memory` data block. It goes into Claude's brief (`ws_ai_briefing()`, channel and task requests) and the application preamble (`GET /workspace/messages/{id}/preamble`). Pinegrap AI gets none: it has no tool to read an entry.

## Who marked a message?
- `ws_messages.marked_by_kind` (`user` default, `app`): an application key or an OAuth connection marks as the application (`marked_by` = application id), a team devices session as the person (`ws_message_mark($viewer, $message, $kind, $app_id)`). An agent's mark never reads as a person's, so it is never an owner decision. Without the column an application's mark is refused (503 / "The database has to be updated first.").
- Any non-system message is markable: `sender_kind` `user` or `app` (`ws_message_mark_check()`, `can_mark` in the payload).
- Name the marker only with `ws_mark_by_name()` and collect people with `ws_mark_people_ids()`: a bare `marked_by` lookup names the person whose id the application has. `GET /workspace/decisions` gives `marked_by` (`user_id` null for an application) and `marked_by_kind`; the person filter counts only people's marks. A discussion's copy keeps the kind (`ws_thread_copy_sync()`).

## Which routes?
- `api_memory.php` (`ws_memory_api_routes()`): `workspace.messages.mark` (`POST /workspace/messages/{id}/mark`, kind decision, note or message), `workspace.memory.list` (`GET /workspace/channels/{id}/memory`, no texts; `expired`, `tag`), `.get`, `.create` (201), `.update` (POST with PATCH), `.delete` with its `delete_post` twin. All rehearse, all are MCP tools. `no_expiry` takes an expiry off.

## Where is the Memory tab?
- `assets/js/workspace_memory.js` (`window.PGWsMemory.mount()`, in `workspace.min.js`), panel actions `ws_memory_list|save|delete`. The tab shows when the channel has an allowed agent row (`ws_memory_tab_shown()`, `channel.memory.tab` in `ws_channel_detail()`); otherwise the channel settings drawer has a Channel memory button that opens it (owner 2026-11-08).
