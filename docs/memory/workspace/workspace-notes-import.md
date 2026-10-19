---
name: workspace-notes-import
description: Notes brought in from other apps (workspace_notes_import.php, notes_import.php): Markdown/text or a zip staged, previewed, written in batches; how an export of ours updates its own note
---

## How does an import run?
- "Import" on My Notes (`openImport()` in workspace.js) talks to `workspace_notes_import.php` (PR #553). `upload` (multipart) stages one .zip, loose .md/.markdown/.txt, or Evernote .enex files (laid out as such files: workspace-notes-import-enex.md) in `data/temp/ws_notes_import_<token>/` (stage.json, bound to session and user, swept after a day) and answers a row per note file: new / update / same / error, linked files, warnings. Nothing is written yet. `import` (JSON) writes batches of 8 s; mode and unticked rows are fixed by the first batch, a row is never written twice. `finish` points the links between notes, removes the stage; `discard` drops it.
- Limits: 100 MB or the server's upload limit; loose files up to max_file_uploads (PHP drops the rest silently, so the screen checks); 50000 zip entries, 5000 note files; a note file is read no further than 1 MB and a linked file no further than ws_upload_max_bytes(), so a zip bomb unpacks no further; 512 MB of linked files an import. WS_NOTE_LIMIT: rows past it start unticked, every write checks it again.

## How is a file read (notes_io.php, pure)?
- Text: UTF-8 (BOM dropped), UTF-16, else Windows-1254. Front matter: title, created/date, updated/modified, tags (a `#tag` line at the end), pinegrap_note. Title: front matter, else a first `# heading` (taken out, also when it repeats the title), else the file name minus Notion's 32-hex id. An empty file named only by its name is refused.
- `ws_note_import_convert()`: headings → bold lines, `*`/`+` items → `- `, `*x*` → `_x_`, `__x__` and `==x==` → `**x**`, `![a](f)` → `[a](f)`, `~~~` → ```` ``` ````, ```` ```math ```` → ```` ```latex ```` (math is a calculation here). A .txt and a file of ours stay as written. Text shaped like `<@user:12>` loses its brackets: a file tags nobody.
- Links: relative paths are pointed from the top of the upload; `[[Name]]`, `![[file]]` by name, the one beside the note first (as Obsidian). A linked file in the upload goes to the note's folder (`ws_note_file_store_bytes()`, channel refusals apply); one missing or refused becomes its words. In `finish`, `[[Note]]` and `[x](Other.md)` become a link to the note it became (absolute `workspace_notes.php?note=N`: the renderer draws http links only), else to an own note of that title, else words.

## What does a file exported from here do?
- It is matched only when `pinegrap_note` names a note the person may read and its `created` equals that note's created_at, so another site's note 12 is never taken.
- `ws_note_import_merge()` lays the file over the note: a line still as the export wrote it is the stored line again (tags, file links), a changed line gets back the tags whose written form it holds (`[label](url)`, `@name`; a tag that went out as a bare word stays a word). Same text and title → "same", skipped. Changed, with owner or edit access → `ws_note_save()` (a version kept, others told). Read-only, or "Add them as new notes" → a new note, files uploaded again.
- A new note keeps the file's dates (else the zip entry's time, else now).

## Anything fixed alongside?
- The export wrote people as `@@name` (labels already carry `@`); fixed with this PR (issue #549). Tests: tests/workspace_notes_io_test.php.
