---
name: notes-import-export-sync
description: Plan (2026-10-19) for Workspace notes: export, Markdown/zip and Evernote .enex import done; Keep, HTML readers, API gaps a sync client needs, later Notion/OneNote; slices N1-N4, open questions
---

## What is this plan?
- Owner request (2026-10-19): bring notes in from and out to other note apps, and keep them in sync.

## What exists today (2026-10-19)?
- `ws_notes` (user_id, title, body, pinned, source_*, channel_id, folder_id, updated_by, created_at, updated_at), shares in `ws_note_shares`, 30 versions in `ws_note_versions`, attachments in a `Workspace/Notes/Note N` folder linked as `[name](url)`. `WS_NOTE_MAX` 60000 chars, `WS_NOTE_LIMIT` 2000 notes a person. Hard delete; no notebooks, tags or external ids.
- Body is a Markdown subset drawn only by `ws_render_body()` (bold, `_italic_`, strike, code, links, GFM tables with `=` formulas, checklists, fences, `::: Title` blocks); headings, lists, quotes and inline images are not drawn. No Markdown library in the code base.
- Screen `startNotes()` (workspace.js): export, print and import buttons (N1, N2); no templates.
- API: `GET/POST /workspace/notes`, `GET/POST|PATCH /workspace/notes/{id}` (dry run, Idempotency-Key, `base` → 409), versions routes; generic `workspace:read/write` scopes; the list is paged with `created_at` in the object (issue #511, docs/memory/workspace/workspace-notes.md); no delete, no deletion records. All note routes are MCP tools.
- Precedents: ERP import (`erp_accounts_import.php`: upload → map/preview → result, staged in `data/temp`, 5 MB), calendar token feed (`ws_calendar_feeds`), OAuth 2.1 server (`oauth.php`, PKCE).
- Outside apps: `docs/memory/plans/reverse-oauth-leftovers.md` lists Notion (MCP alias first) and OneNote (Graph); Google Keep, Evernote and Apple Notes have no usable API, file route only.

## What are the slices?
- N1 Export — done (PR #528, memory docs/memory/workspace/workspace-notes-export.md): `.md` with front matter, the zip vault, the print page.
- N2 Import, Markdown/zip — done (PR #553, memory docs/memory/workspace/workspace-notes-import.md): `.md`/`.txt` or a `.zip` (Obsidian, Notion, Joplin, Bear, our own vault), preview, batches, linked files, `[[wikilinks]]`, an export of ours updating its note.
- N2 Import, Evernote `.enex` — done (PR #PRNUM, memory docs/memory/workspace/workspace-notes-import-enex.md): read as a stream at upload and laid out in the stage as Markdown and attachment files, so the preview, batches and `finish` stayed as they were.
- N2 Import, other formats: Google Keep Takeout JSON, single `.html`, each laid out in the stage the same way.
- N3 Sync API: paging and `created_at` done (issue #511); left: `DELETE /workspace/notes/{id}`, deletion records, a per-note `revision` (today `base` has one-second resolution and only refuses another person's change). Schema asks: `ws_notes.revision`, `ws_note_deletions`, `ws_note_sources` (user_id, provider, external_id, hash) for re-import de-duplication.
- N4 Outside apps: Notion through the MCP alias ("save to my notes" target in `mcp_form.php`), OneNote through a Microsoft connect purpose; both wait on the reverse-oauth decisions.

## What is still open?
1. Headings and lists: imports flatten them (headings bold lines, every item `- `); whether `ws_render_body()` later draws them for notes only is still open.
2. Schema for N3: owner approval; N1/N2 ship without it.
3. A read-only token feed like the calendar's (default: no; sync clients use OAuth 2.1 + REST).
4. Format priority (default: Markdown/zip → enex → Keep → HTML).

## Where to start?
- The N3 bugs and schema next (ask the owner about the three tables first), then the Keep and HTML readers (`notes_enex.php` is the pattern), then N4.
