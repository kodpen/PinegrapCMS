---
name: channel-memory
description: A channel's memory (ws_channel_memory) and the decision mark by a person or an agent (ws_messages.marked_by_kind): rows, routes, the index in the brief, the Memory tab
---

## What holds a channel's memory?
- `ws_channel_memory` (step 11.0.173, `upgrade_2026_11_0_ws_channel_memory()`): title (150), text (4000), tags (comma list, lower case, at most 10 of 32 characters: `ws_memory_tags()`), pinned, `expires_at` (0 never), `author_kind`/`author_id` and `updated_by_kind`/`updated_by_id` (`user` or `app`, the same pair as `ws_messages.sender_kind`/`sender_id`). Every channel has one, an agent channel or not; a discussion's rows go with it in `ws_threads_purge()`.
- Read with `ws_memory_ready()` first. Checks: `ws_memory_check()` (panel and API share it; fields left out keep their value; an unchanged past expiry is not refused again) over the pure `ws_memory_entry_fault()`. Writes `ws_memory_save()`, `ws_memory_delete()`. Whoever may post in the channel writes them.
- Architect (2026-11-26, agent channel request 3063): an update that changes nothing writes nothing; `updated_at`/`updated_by_*` stay and the route answers 200 with the entry as it stands, no "unchanged" field (`channel-memory-note.md`).
- `ws_memory_sweep()` (`workspace_recurring_job.php`) deletes entries expired 30 days ago and those of gone channels.

## What does an agent's brief carry?
- The index only, never texts (owner 2026-11-08, #8188): `ws_memory_digest()`: "- #id title [tags]" lines, pinned first then the newest, expired left out; in an agent channel after the general index (`channel-memory-general.md`).
- A memory of more than `WS_MEMORY_DIGEST_LINES` (40) live records is told by its projects: `channel-memory-brief-projects.md`.
- Then "Decisions in this channel": the newest `WS_MEMORY_BRIEF_DECISIONS` (10) and a count of the older ones; `ws_memory_brief()` reads ten and counts the rest (#8756). An agent channel's are every agent channel's (`agent-channel-decisions.md`).
- The brief's read-more line names `?limit=<n>` (`agent_queue.php`; `$more` in `ws_ai_briefing()`).
- All within `WS_MEMORY_DIGEST_MAX`, the closing count too (#11790): a line that does not fit is left out with the lines after it. One closing count ("(N more not shown: …)") holds them and older decisions.
- `ws_ai_memory_parts()` puts it in a `channel memory` data block of Claude's brief (`ws_ai_briefing()`), the app agent's brief and the preamble (`GET /workspace/messages/{id}/preamble`): one digest for all. Pinegrap AI gets none (no tool reads an entry).

## Who marked a message?
- `ws_messages.marked_by_kind`, `ws_message_mark()`, `ws_mark_by_name()`: `channel-memory-mark.md`.

## Which routes?
- `api_memory.php` (`ws_memory_api_routes()`): `workspace.messages.mark` (`POST /workspace/messages/{id}/mark`, kind decision, note or message), `workspace.memory.list` (`GET /workspace/channels/{id}/memory`, no texts; `expired`, `tag`), `.get`, `.create` (201), `.update` (POST with PATCH), `.delete` with its `delete_post` twin. All rehearse, all are MCP tools. `no_expiry` takes an expiry off.

## Where is the Memory tab?
- `assets/js/workspace_memory.js` (`PGWsMemory.mount()`), panel actions `ws_memory_list|save|delete|move`; rows under project headings: `workspace-memory-tab.md`; the general memory: `channel-memory-general.md`. The tab shows when the channel has an allowed agent row (`ws_memory_tab_shown()`, `channel.memory.tab` in `ws_channel_detail()`); otherwise the channel settings drawer's Channel memory button opens it (owner 2026-11-08).
