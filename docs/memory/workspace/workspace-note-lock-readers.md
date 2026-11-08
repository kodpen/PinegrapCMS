---
name: workspace-note-lock-readers
description: Notes lock beyond the notes screen: API and MCP (locked, 423 note_locked), assistants, get_file.php, export, blocks, record history, table import; the readers guard test
---

## What does the external API and MCP get?
- `ws_api_note_present()` carries `locked` (the note is behind a closed lock; the API never holds the session's open lock), and then `text` and `body` are null and `excerpt` empty; the title stays. Search hits and lists come from the same presenter (`workspace-note-lock-gate.md`).
- `ws_api_note_locked_stop()` answers 423 `note_locked` (in `api_error_catalogue()`) before a change, a delete, a place in a group (`api_note_groups.php`) and the versions list or one version (`api_threads.php`). MCP tools run the same handlers.
- `/workspace/sync` carries no notes; nothing to gate there.

## Which other readers are gated, and how?
- Assistants: `ws_note_claude_ask()` refuses a note behind its owner's lock, open or not (`ws_note_locked_by_owner()`); Pinegrap AI (`ai.php`) fails a request whose note was locked since, and Claude's request (`api_claude.php`) gets neither the note nor the line.
- `get_file.php`: a file in a note's folder is served 404 when the note is behind the reader's closed lock (`ws_note_lock_folder_closed()`); the workspace bootstrap loads only when the folder is a note's and some person has a PIN.
- Export: one note or its print page needs the lock open (403 otherwise, `workspace_notes_export.php`); Export all leaves every note behind the person's lock out, open or not (`ws_note_lock_leave_out()`), and the zip carries `Locked notes.txt` with the count.
- Block pull and block search (`blocks.php`), a note's table into a workflow table (`wtable_import.php`), record history of a note (`ws_ref_notes_open()` in `refs.php`: body emptied, `lock_closed`), a canvas saved into a note (`workspace_file_save.php`) and `ws_meeting_transfer` close behind a closed lock.

## How is a new reader kept from leaking?
- `tests/workspace_note_lock_readers_test.php` lists every product file matching `ws_notes`, `ws_note(` or `ws_note_for(` with the gate string it must contain, or `none:` and the reason. A new reader fails the test until it is listed.
