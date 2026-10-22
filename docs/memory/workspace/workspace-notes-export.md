---
name: workspace-notes-export
description: Notes leaving the Workspace as files (workspace_notes_export.php, notes_io.php): one note as Markdown with front matter, a print page, "Export all" as a zip vault; what the tags and attachments become
---

## What leaves, and from where?
- `workspace_notes_export.php` (PR #528), for the person signed in and a workspace member: `?note=N` sends the note as `<title>.md` (`text/markdown`), `?note=N&print=1` draws it as a small page of its own that calls `window.print()`, `?all=1` sends `notes-<date>.zip`. A note the person may not view answers 404 (`ws_note_for(…, 'view')`). GET with the session: a read of the person's own data, no state changes.
- Buttons: the note menu (`noteMenu()` in workspace.js: "Download as Markdown", "Print") and the toolbar ("Export all"); the URL is `CFG.urls.notes_export`.

## What does a Markdown file hold?
- Built by `ws_note_export_markdown()` (`includes/workspace/notes_io.php`): a YAML front matter (`title` quoted by `ws_note_export_yaml()`, `created` and `updated` as ISO 8601 in the site's time zone, `author`, `source` = the channel the note was kept from when the reader may still read it, `shared_by` for a shared note, `pinegrap_note` = the note's id for a later import), a blank line, then the body as the note keeps it with LF endings.
- Tags are written out for a reader outside the workspace (`ws_note_export_tokens()`): a record the reader may open becomes `[label](absolute url)` (a link from the site root gets the scheme and host), one they may not see its generic label, a person, department or application `@name`; an unknown tag goes away. `::: Title` lines, checklists, tables and calculation fences stay as text — other apps show them as they are.
- The file name is the title with `\ / : * ? " < > |` turned into ` - ` (`ws_note_export_name()`, 80 characters, the id added only when two titles collide).

## What is in the zip?
- `ws_note_export_vault()` (ZipArchive; without the extension the screen says to export notes one by one): the person's own notes at the top, notes shared with them under `Shared/`, every note's files (its `Workspace/Notes/Note N` folder) under `attachments/<note id>/`, and the `[name](url)` links in the text pointed there (`ws_note_export_attachment_links()`; `../attachments/…` from `Shared/`). The folder opens as an Obsidian vault and imports into Joplin and Notion. The rows come from `ws_note_export_rows()`, not from `ws_notes_list()`, so the screen's 500-note cap (issue #511) does not cut an export.

## What if there is nothing to export?
- libzip writes no file for an archive without entries: `close()` deletes it. A person with no notes (none shared either) got a 0-byte `notes-<date>.zip` and `filesize()`/`readfile()` warnings (issue #777, PR #1265). Now `ws_note_export_vault()` reads the rows first and answers "There are no notes to export." without opening a zip, treats a failed `close()` or a missing file as "The file could not be saved.", and `ws_notes_export_send()` shows an error page instead of streaming a file that is not there.

## What does the print page do?
- Its own HTML (no panel shell: nothing to hide at print time): title, a meta line (author, last changed by, date), the body from `ws_render_body()` with a stylesheet for chips, checklists, tables and code, a Print button, `window.print()` on load. Tests: `tests/workspace_notes_io_test.php`.
