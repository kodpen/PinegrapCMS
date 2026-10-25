---
name: notes-import-export-sync
description: Plan (2026-10-19) for Workspace notes: export, Markdown/zip, .enex and Keep import, note revision done; N3b delete, N3c sources, HTML reader, later Notion/OneNote; slices, open questions
---

## What is this plan?
- Owner request (2026-10-19): bring notes in from and out to other note apps, and keep them in sync.

## What does the plan build on?
- Notes, their API and limits: docs/memory/workspace/workspace-notes.md. Precedents: the ERP import (upload → preview → result, staged in `data/temp`), the calendar token feed (`ws_calendar_feeds`), the OAuth 2.1 server (`oauth.php`).
- Outside apps: `docs/memory/plans/reverse-oauth-leftovers.md` lists Notion (MCP alias first) and OneNote (Graph); Google Keep, Evernote and Apple Notes have no usable API, file route only.

## What are the slices?
- N1 Export — done (PR #528, memory docs/memory/workspace/workspace-notes-export.md): `.md` with front matter, the zip vault, the print page.
- N2 Import, Markdown/zip — done (PR #553, memory docs/memory/workspace/workspace-notes-import.md): `.md`/`.txt` or a `.zip` (Obsidian, Notion, Joplin, Bear, our own vault), preview, batches, linked files, `[[wikilinks]]`, an export of ours updating its note.
- N2 Import, Evernote `.enex` — done (PR #644, memory docs/memory/workspace/workspace-notes-import-enex.md): read as a stream at upload and laid out in the stage as Markdown and attachment files, so the preview, batches and `finish` stayed as they were.
- N2 Import, Google Keep Takeout zip — done (PR #1656, memory docs/memory/workspace/workspace-notes-import-keep.md): the Keep notes of the zip laid out in the stage the same way.
- N2 Import, other formats: single `.html`, laid out in the stage the same way.
- N3 Sync API: paging and `created_at` done (issue #511). The owner approved the three schema changes on 2026-10-25 (`ws_notes.revision`, `ws_note_deletions`, `ws_note_sources`), cut into slices of one PR each:
  - N3a revision — done (PR #1663, schema 11.0.84): `ws_notes.revision`, moved on by every write, in the object, sent back on update for 409 `changed_meanwhile` (memory docs/memory/workspace/workspace-notes-revision.md).
  - N3b delete: `DELETE /workspace/notes/{id}` (owner, dry run, `ws_note_delete_check()` shared with the screen's delete), `ws_note_deletions` rows for the owner and the people it was shared with, kept about 30 days, and the deletions since `updated_since` in the list's answer.
  - N3c sources: `ws_note_sources` (user_id, provider, external_id, hash, note_id) written by the import, so a re-import of the same Keep, Evernote or Markdown note updates or skips its note instead of adding a second one.
- N4 Outside apps: Notion through the MCP alias ("save to my notes" target in `mcp_form.php`), OneNote through a Microsoft connect purpose; both wait on the reverse-oauth decisions.

## What is still open?
1. Headings and lists: imports flatten them (headings bold lines, every item `- `); whether `ws_render_body()` later draws them for notes only is still open.
2. Schema for N3: approved by the owner on 2026-10-25.
3. A read-only token feed like the calendar's (default: no; sync clients use OAuth 2.1 + REST).
4. Format priority (default: Markdown/zip → enex → Keep → HTML).

## Where to start?
- N3b next, then N3c (each after the previous slice's merge), then the HTML reader (`notes_enex.php` and `notes_keep.php` are the pattern), then N4.
