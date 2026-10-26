---
name: workspace-notes-revision
description: A note's revision (ws_notes.revision, schema 11.0.84, PR #1663): which writes move it, the API's revision field and 409, the panel's revision, why code checks for the column, base kept
---

## What is the revision for?
- A sync client keeps notes on a phone or another app and writes them back. `base` (the `updated_at` it read) refuses only a later change by somebody else, to the second: two changes within one second, or the person's own change on another device, were written over. The revision is a number every change moves on, so any change since the read is caught.

## Which writes move it?
- Every `UPDATE ws_notes` of the title, text or pin: `ws_note_save()` (with the fields it writes, a pin alone included), the assistant's answer under a line (`ws_note_claude_deliver()`), and the notes import's link rewrites (`notes_import.php`). Each adds `ws_note_revision_sql()`; `tests/workspace_notes_revision_test.php` reads the sources and fails for an UPDATE of those fields without it.
- `channel_id` and `folder_id` writes (the note's channel and attachment folder) do not move it: they are not the note's content. A new note starts at 1, as every note did when the column was added.

## How does the API use it?
- The note object carries `revision` (null on a site whose schema step has not run). `POST|PATCH /workspace/notes/{id}` takes `revision`: when the stored one differs, 409 `changed_meanwhile` with field `revision`; the message names who changed it, or says the person changed it on another screen or device. Without `revision`, `base` works as before (`ws_note_stale()` decides both). A dry run meets the same refusal.

## What does the panel send?
- The notes screen sends `revision` beside `base` (`payload()` in `assets/js/workspace.js`, the `ws_note_save` action passes both). A save takes the new revision back; a pin made on the screen takes it too, or the text's next save would be refused; the 15-second poll reloads a note whose revision moved (a pin, an import's link rewrite). "Keep mine" sends neither. Before the schema step `revision` is null and `base` decides.
- So the same person typing in two tabs, or typing while an assistant's answer is written in, now gets the conflict box ("Load" / "Keep mine") instead of one text silently written over the other.

## Why does the code check for the column?
- The files of an update land before its schema step runs, and a statement naming a missing column fails as a whole (a failed query returns false, silently): every note save would have been lost until the step ran. `ws_note_revision_ready()` (`pg_schema_has()`, false without a connection) gates the list columns, the presented field and the UPDATE part.
