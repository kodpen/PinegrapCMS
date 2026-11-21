---
name: channel-notes-written
description: A document in a channel is a note written there: Write a note, POST /workspace/channels/{id}/notes, edit through the channel's share, the card touched on a save, note_card's fields
---

## What is it, and why a note?
- Owner (2026-11-20): a plan or a subject an agent explains is written in the channel drawn as Markdown and changed in place when it changes; ordinary messages stay as they are (agent channel messages 10569 and 10572, poll 179; `workflow/decisions-made-workspace-42.md`).
- Architect (2026-11-20): the document is a note, not a file and not a new record kind: drawing, versions, the refusal of a stale write, export, the lock and the MCP tools were there. The product's word stays "note". Messages keep `#` and `---` as words (`workspace-text-headings.md`).
- Architect (2026-11-20): a note an agent writes belongs to the person its key acts for and counts in that person's note limit (`workspace-notes.md`). An edit writes no message, wakes nobody and writes no inbox line: the card says who changed it and when; whoever wants it announced writes a message.

## How is a note written in a channel?
- Panel: "Write a note" in the composer's attach (paperclip) menu, for who may post (`channel-note-card.md`). Action `ws_note_channel_write` (channel_id, title, body, channel_edit).
- `ws_note_channel_write()` (notes.php) writes a new note of the person's own and posts its card in one move; `ws_note_channel_write_check()` makes the same refusals first (may not post, empty, over `WS_NOTE_MAX`, the note count, the card's message). A card that could not be posted deletes the new note (PR #10742).
- API: `POST /workspace/channels/{id}/notes` (`workspace.channels.notes.create`, `api_channel_notes.php`, an MCP tool), dry run, Idempotency-Key, 201 with message_id, note_id, revision. Its body is Markdown, converted by `ws_note_import_convert()`; a body over `WS_NOTE_MAX` is refused with the limit (422 on body), never cut.
- The card's message is written as `POST …/files` writes one: `ws_api_message_writer()` passed through `ws_note_share_channel()`'s `$options` (app_id, via_client_id).

## Who may change a note shared in a channel?
- A channel share row (`ws_note_shares.channel_id > 0`) with `can_edit` 1 gives `edit` to whoever may post in that channel (`ws_can_post_channel()`); one who only reads it gets `view`; without `can_edit` it is `view`. The rule is the pure `ws_note_access_of()`; tests `tests/workspace_channel_notes_test.php`.
- Only the owner turns it on or off ("People who may post here can edit", off unless chosen): panel action `ws_note_channel_edit` → `ws_note_channel_edit_set()`. A locked note is refused; a share a note group wrote stays the group's (always `can_edit` 0).

## What happens to the card when the note changes?
- Every save that writes (`ws_note_save()`, the assistant's answer in `ws_note_claude_deliver()`) calls `ws_note_cards_touch()`: each card's message gets `touched_at`, so the open screen draws it again and `changed_since` returns it. The switch touches its card too.
- The API's update is the note's own (`base`, 409 `changed_meanwhile`, a version by today's rule: `workspace-notes-revision.md`).

## What does a message's note_card carry over the API?
- `id`, `name`, `excerpt`, `revision` (the base of `POST /workspace/notes/{id}`), `updated_at`, `can_edit` (the reader's access is owner or edit) and `changed_by` (`workspace-note-changed-by-app.md`); `ws_api_message_note_card()`.

## Where is the rest?
- The card, its fold, foot, menu and windows: `channel-note-card.md`. The agent's name on a change: `workspace-note-changed-by-app.md`. The agents' instructions: `agent-instructions-note-lines.md`; this repository's sessions: `workflow/agent-channel-note-tool.md`.
- Left out, each a later yes: headings in messages, a `.md` file drawn in the conversation, two people writing at once in one box, comments on a line, a document kind of its own, an edit announced by the site.
