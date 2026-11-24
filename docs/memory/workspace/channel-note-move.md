---
name: channel-note-move
description: Move to this channel (ws_note_move(), POST /workspace/notes/{id}/move) makes a person's note the channel's; Copy to my notes (ws_note_copy_mine()) is the way back
---

## Why?
- Owner (2026-11-23): notes written in a channel before stayed in the owner's own notes; the card's move takes them to the channel, the copy brings one back (`workflow/decisions-made-workspace-49.md`, `channel-note-owned.md`).

## How is a note moved?
- "Move to this channel" on the card of the owner's own note shared there (the channel's own share, not a note group's), after a question; panel action `ws_note_move` (note_id, channel_id) → `ws_note_move()`. API: `POST /workspace/notes/{id}/move` with `channel_id` (`workspace.notes.move`, `api_channel_notes.php`), dry run, an MCP tool, so an agent moves the plans it wrote one call each.
- `ws_note_move_check()`: the owner's own note only (403 over the API), not locked, a channel they may post in, under its limit, and a card the channel takes when none is there (422 on channel_id).
- `ws_note_move()`: `owner_channel_id` set, pinned 0, revision moved on; the channel's own share stays as the card (can_edit 0), a card is posted when there is none; every other share goes and its card is retired; group places and inbox lines go. The pure `ws_note_move_plan()` picks the card, the shares that go and the `ws_note_deletions` rows (owner, people, other channels) so sync clients drop it.

## How is it copied back?
- "Copy to my notes" on a channel's note (its card and the Notes tab's row menu), for whoever reads it: `ws_note_copy_mine()` writes a new note of the reader's with its title and text, in their `WS_NOTE_LIMIT`; files stay in the channel note's folder. No API route: a client reads the note and writes it with `POST /workspace/notes`.

## Where are the tests?
- `tests/workspace_channel_notes_test.php` (the plan, the checks), `tests/workspace_channel_notes_js_test.php` (the menus, the actions).

Related: docs/memory/workspace/channel-note-owned.md, docs/memory/workspace/channel-notes-tab.md
