---
name: channel-memory-note
description: The system note when a channel's or the general memory changes (#10546): text, writer, the burst rule, the write path, no agent woken
---

## When does a channel get a memory note?
- Every write of an entry (added, changed, deleted; a move to the general memory counts as a change of the general memory) by the Memory tab, the API, MCP or /memory: `ws_memory_note()` is called from `ws_memory_save()`, `ws_memory_delete()` and `ws_memory_move_general()` (`includes/workspace/channel_memory.php`), the one write path they share.
- A channel's entry: a note in that channel, head "Channel memory updated". A general entry (channel_id 0): a note in every unarchived agent channel (`ws_memory_note_channels()`, at most 200), head "General memory updated".

## What does it say?
- `ws_message_system()` text in lines: the head, one line per entry "#<id> <title> - added|changed|deleted" (`ws_memory_note_entry_line()`, the title on one line), the last line "by <writer>" (`ws_memory_note_by_line()`): a person as `<@user:N>`, an application by its name. All through `lang()`; Turkish "Kanal belleği güncellendi", "Genel bellek güncellendi". Drawn as `ws-msg-system`.
- `ws_memory_delete($viewer, $row, $writer)`: the API passes `ws_api_memory_writer()`, so an agent's delete names the agent; the panel passes none (the person).

## How is a flood kept off?
- `ws_memory_note_merge()` (pure): the channel's last message (`ws_channels.last_message_id`) is extended with the new line when it is a system note with the same head and the same "by" line, not deleted, written within `WS_MEMORY_NOTE_BURST` (300 s). The same line again changes nothing. Over `WS_MEMORY_NOTE_MAX` (3000 characters), or any message in between, a new note.
- The extended note's body is rewritten and `ws_message_touch()` makes open screens read it again; `created_at` stays, so a burst never runs past five minutes from its first change.

## Does it wake an agent?
- No: `ws_message_system()` calls no `ws_message_send()`, `ws_agent_after_send()` or notify, makes no `ws_ai_requests` row and has no `parent_id`, so the loop guard (`ws_agent_brake()`, the chain and the last hour's requests) never counts it. Pinned by tests/workspace_memory_note_test.php.
