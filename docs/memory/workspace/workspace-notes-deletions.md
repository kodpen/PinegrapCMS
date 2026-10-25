---
name: workspace-notes-deletions
description: Deleting a note over the API (PR #1674) and ws_note_deletions (schema 11.0.85): who may, whom a deletion is kept for, how long, deleted in the changes listing, no MCP tool
---

## How is a note deleted over the API?
- `DELETE /workspace/notes/{id}`, or `POST /workspace/notes/{id}/delete` for servers that refuse DELETE: `ws_api_notes_delete()` runs `ws_note_delete_check()` (the owner only, 403 otherwise; the screen's delete meets the same check), then `api_dry_run_stop()`, then `ws_note_delete()`, the screen's own delete (shares, versions, inbox lines, the card in a channel; the files stay in the file manager). It answers `{id, deleted: true}`.
- Application keys and OAuth connections may delete (a sync client has to), but the routes carry `'mcp' => false`: a deletion cannot be undone, so it is not an assistant's tool.

## Whom is a deletion kept for, and how long?
- `ws_note_delete()` reads the note's shares before they go and writes `ws_note_deletions` rows (`ws_note_deletion_rows()`): its owner, each person it was shared with (user_id), each channel it was shared in (channel_id, user_id 0), each once. Rows older than 30 days (`WS_NOTE_DELETIONS_KEEP`) go at each deletion, 500 at a time.
- Written only once the table exists (`ws_note_deletions_ready()`): files land before the schema step runs.

## How does a sync client learn of it?
- `GET /workspace/notes?updated_since=` answers `deleted` (id, deleted_at) on its first page (no cursor): the notes deleted since that the reader kept, their own, shared with them or shared in a channel `ws_can_read_channel()` lets them read; the oldest first, at most 1000 (`WS_NOTE_DELETIONS_MAX`), never older than 30 days. An app away longer reads the whole list again. Without `updated_since`, `deleted` is empty.
- A share taken back, or a channel the reader left, is not a deletion: such a note just stops coming in the list.

## Where are the tests?
- tests/workspace_notes_delete_test.php: the rows a deletion keeps, the schema step and `get_tables()`, the record written before the shares go, the routes (dry run, no MCP) and the handler's order.
