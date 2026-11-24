---
name: channel-note-owned
description: A channel's note (ws_notes.owner_channel_id): access from the channel, off My Notes and its limit (ws_note_own_sql(), guard test), WS_NOTE_CHANNEL_LIMIT, who deletes it, deleted with its channel
---

## What is a channel's note?
- Owner (2026-11-23): a note written in a channel belongs to the channel (`workflow/decisions-made-workspace-49.md`, schema `workflow/decisions-made-schema-48.md`). Column `ws_notes.owner_channel_id` (INT UNSIGNED, 0 = a person's note, index `idx_owner_channel`), step 11.0.273 `upgrade_2026_11_0_ws_note_channel()`, PR #11198.
- `user_id` stays the writer (an agent's key: the person it acts for); `updated_app_id` names the agent as before.
- Readiness: `ws_note_channel_owner_ready()`; `ws_note_channel_of($note)` gives the channel or 0 (pure, 0 when the column is missing).

## Who reaches it?
- `ws_note_access_of()`: for a channel's note only the channel counts, its writer included: may post (`ws_can_post_channel()`, so not in an archived channel) gives `edit`, may read gives `view`, else nothing. Never `owner`, and no share row is read.
- So the owner's rights never reach it: lock (`ws_note_lock_note()` refuses), a note group (`ws_note_place_check()` refuses), a share with a person or another channel (`ws_note_share_people()`, `ws_note_share_channel()`: "A channel's note is not shared elsewhere."), the card's edit switch, pinning.
- Deleting: its writer while they read the channel, and whoever manages it (`ws_can_manage_channel()`): `ws_note_delete_check()` asks the pure `ws_note_channel_may_delete()` (PR #11226). The panel's `ws_note_delete` reads the note at `view` and leaves the decision to the check.
- Where it is listed: `channel-notes-tab.md`.

## How is it kept off a person's notes?
- `ws_note_own_sql($alias)` adds `owner_channel_id = 0` (nothing before the step) to every query of a person's own notes: My Notes and its search (`ws_notes_list()`), the dashboard card (`ws_notes_latest()`), the API's pages (`ws_notes_page_rows()`), the deletions a sync client keeps, Export all (`notes_io.php`), import capacity and title links (`notes_import.php`), a template's notes count (`templates.php`), group places (`note_groups.php`), the `WS_NOTE_LIMIT` count.
- Guard: `tests/workspace_note_channel_readers_test.php` scans every `FROM ws_notes … WHERE … user_id = '` statement; each adds `ws_note_own_sql(` or is listed as exempt with its reason (the message copy, the locked notes, the meeting sort).

## How is it written, and how many?
- Both writers ("Write a note", `POST /workspace/channels/{id}/notes`) go through `ws_note_channel_write()`, which passes the channel to `ws_note_save($viewer, $data, $owner_channel_id)`; the third argument is never taken from a request.
- `WS_NOTE_CHANNEL_LIMIT` 2000 per channel: "A channel can keep at most {var:1} notes." It does not count in the writer's `WS_NOTE_LIMIT`.
- Its card still hangs on a `ws_note_shares` row (channel, message_id, `can_edit` 0): the message has no other link to the note. The row gives no access; `channel_edit` of the write is ignored, and Write a note no longer offers the switch. Deleting the card's message leaves the note in the Notes tab.

## When is it deleted?
- With its channel: `ws_threads_purge()` (a discussion is the only channel deleted) calls `ws_note_channel_purge()`, which runs `ws_note_delete_rows()` for each, as `ws_note_delete()` does after its check.

Related: docs/memory/plans/channel-notes-own.md, docs/memory/workspace/channel-notes-written.md
