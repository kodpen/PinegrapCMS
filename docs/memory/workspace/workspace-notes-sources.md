---
name: workspace-notes-sources
description: Where an imported note came from (ws_note_sources, schema 11.0.86): the id per provider, how the import plan turns a known file into "same" or "update", when rows are written and dropped
---

## What does ws_note_sources keep?
- One row a note file a person brought in (2026.11.0, step `upgrade_2026_11_0_workspace_note_sources()`, label 11.0.86; owner approved 2026-10-25): `user_id`, `provider` (keep, enex, markdown, html), `external_id` (VARCHAR(191)), `hash` (sha1 of the file's title and text, `ws_note_source_hash()`), `note_id`. Unique key user_id+provider+external_id; key on note_id. In `get_tables()`.
- Probe `ws_note_sources_ready()` (`pg_schema_has()`): before the step runs nothing is read or written and the import behaves as before.

## Which id is a note known by?
- `ws_note_source_id()` (notes_io.php, pure): Keep: the JSON entry name in the Takeout zip; Evernote: `<guid>` when the export carries one, else title + created (ISO, UTC), else the title; Markdown: front matter `id`, else the path in the upload (a loose file: its name); HTML: the canonical address (else og:url, as `ws_note_html_meta()` reads it), else the file name. An id past 191 characters keeps its start and `#` + sha1 of the whole.
- Keep, Evernote and HTML are laid out in the stage as Markdown files; the stager writes the id into their front matter as `id:` (`ws_note_enex_file()`), so `ws_note_import_entry()` reads every provider one way: front matter `id`, else path. The stage keeps `provider` (old stages without it count as markdown).

## How does the plan use it?
- An export of ours (`pinegrap_note`) is matched first, as before. Otherwise `ws_note_source_find()` looks the file up; a row whose note the person may still read makes it that note: `ws_note_import_source_kind()` says "same" when the hash is the stored one, "update" when it changed and they may edit, else "new". The merge and the write are the export match's (`ws_note_import_merge()` without file maps).
- `ws_note_import_write()` saves the row (insert or update) after added, updated and same; "Add them as new notes" points the row at the new note. `ws_note_delete()` drops the note's rows, so the same file comes in again as new.

## Where are the tests?
- tests/workspace_notes_sources_test.php (ids, long ids, hash, the decision, the id in front matter, schema step, call sites); the stage tests of keep, enex and html check the id each writes. No database test: the lookup and write are not unit-tested.
