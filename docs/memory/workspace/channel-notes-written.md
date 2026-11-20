---
name: channel-notes-written
description: A note written in a channel: the route, who may change it through the channel's share, the card touched on a save, the card's API fields
---

## How is a note written in a channel?
- `ws_note_channel_write()` (notes.php) writes a new note of the person's own and posts its card in one move; `ws_note_channel_write_check()` makes the same refusals first (may not post, empty, over `WS_NOTE_MAX`, the note count, the card's message). Panel action `ws_note_channel_write` (channel_id, title, body, channel_edit). A card that could not be posted deletes the new note (PR #10742).
- API: `POST /workspace/channels/{id}/notes` (`workspace.channels.notes.create`, `api_channel_notes.php`), dry run, 201 with message_id, note_id, revision. Its body is Markdown, converted by `ws_note_import_convert()`; a body over 60,000 characters is refused with the limit (422 on body), never cut.
- The card's message is written as `POST …/files` writes one: `ws_api_message_writer()` passed through `ws_note_share_channel()`'s `$options` (app_id, via_client_id).

## Who may change a note shared in a channel?
- A channel share row (`ws_note_shares.channel_id > 0`) with `can_edit` 1 gives `edit` to whoever may post in that channel (`ws_can_post_channel()`); one who only reads it gets `view`; without `can_edit` it is `view`, as before. The rule is the pure `ws_note_access_of()`; tests `tests/workspace_channel_notes_test.php`.
- Only the owner turns it on or off: panel action `ws_note_channel_edit` (note_id, channel_id, can_edit) → `ws_note_channel_edit_set()`. A locked note is refused; a share a note group wrote stays the group's (always `can_edit` 0).

## What happens to the card when the note changes?
- Every save that writes (`ws_note_save()`, the assistant's answer in `ws_note_claude_deliver()`) calls `ws_note_cards_touch()`: each card's message gets `touched_at`, so the open screen draws it again and `changed_since` returns it. No message, no wake, no inbox line. The switch touches its card too.

## What does a message's note_card carry over the API?
- `id`, `name`, `excerpt`, plus `revision` (the base of `POST /workspace/notes/{id}`), `updated_at` and `can_edit` (the reader's access is owner or edit); `ws_api_message_note_card()`.
