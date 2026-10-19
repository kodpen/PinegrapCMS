---
name: notes-import-export-sync
description: Plan (2026-10-19) for Workspace notes: export (Markdown, zip vault, print), import (Markdown/zip, enex, Keep, HTML), API gaps a sync client needs, later Notion/OneNote; slices N1-N4, open questions
---

## What is this plan?
- Owner request (2026-10-19): bring notes in from and out to other note apps, and keep them in sync.

## What exists today (2026-10-19)?
- `ws_notes` (user_id, title, body, pinned, source_*, channel_id, folder_id, updated_by, created_at, updated_at), shares in `ws_note_shares`, 30 versions in `ws_note_versions`, attachments in a `Workspace/Notes/Note N` folder linked as `[name](url)`. `WS_NOTE_MAX` 60000 chars, `WS_NOTE_LIMIT` 2000 notes a person. Hard delete; no notebooks, tags or external ids.
- Body is a Markdown subset drawn only by `ws_render_body()` (bold, `_italic_`, strike, code, links, GFM tables with `=` formulas, checklists, fences, `::: Title` blocks); headings, lists, quotes and inline images are not drawn. No Markdown library in the code base.
- Screen `startNotes()` (workspace.js ~17677-19204): no export, print, import or template.
- API: `GET/POST /workspace/notes`, `GET/POST|PATCH /workspace/notes/{id}` (dry run, Idempotency-Key, `base` → 409), versions routes; generic `workspace:read/write` scopes; no delete, no deletion records, no `created_at` in the object, list capped at 500 with `has_more:false` (issue #511). All note routes are MCP tools.
- Precedents: ERP import (`erp_accounts_import.php`: upload → map/preview → result, staged in `data/temp`, 5 MB), calendar token feed (`ws_calendar_feeds`), OAuth 2.1 server (`oauth.php`, PKCE).
- Outside apps: `docs/memory/plans/reverse-oauth-leftovers.md` lists Notion (MCP alias first) and OneNote (Graph); Google Keep, Evernote and Apple Notes have no usable API, file route only.

## What are the slices?
- N1 Export — done (PR #528, memory docs/memory/workspace/workspace-notes-export.md): `.md` with front matter, the zip vault, the print page.
- N2 Import: `.md`/`.txt`, a `.zip` of Markdown (Obsidian, Notion, Joplin, Bear exports), Evernote `.enex` (XMLReader; `en-todo` → checklist, `en-media` → attachment), Google Keep Takeout JSON, single `.html`. Pure converters in `includes/workspace/notes_io.php` with `tests/workspace_notes_io_test.php`; root `workspace_notes_import.php` (multipart, CSRF, `data/temp`, upload → preview with warnings → batched import → result); `ws_note_import_write()` reusing `ws_note_save_check()`, keeping source dates, indexing blocks, refusing over 60000 chars, checking the 2000 limit up front. Conversions: `# heading` → bold line, lists → `- ` lines, `*x*` → `_x_`, rename ```` ```math ```` fences, neutralise text shaped like `<@…>`/`<#…>`, `[[wikilinks]]` → note links in a second pass.
- N3 Sync API: paging on the list, `created_at`, `DELETE /workspace/notes/{id}`, deletion records, a per-note `revision` (today `base` has one-second resolution and only refuses another person's change). Schema asks: `ws_notes.revision`, `ws_note_deletions`, `ws_note_sources` (user_id, provider, external_id, hash) for re-import de-duplication.
- N4 Outside apps: Notion through the MCP alias ("save to my notes" target in `mcp_form.php`), OneNote through a Microsoft connect purpose; both wait on the reverse-oauth decisions.

## What is still open?
1. Should `ws_render_body()` learn headings and lists for notes only (the chat composer stays a chat box) before N2, or do imports flatten them (default: flatten now, renderer later)?
2. Schema for N3: owner approval; N1/N2 ship without it.
3. A read-only token feed like the calendar's (default: no; sync clients use OAuth 2.1 + REST).
4. Format priority (default: Markdown/zip → enex → Keep → HTML).

## Where to start?
- N2 Markdown/zip next (a `pinegrap_note` front matter line names the note it came from), then the N3 bugs and schema, then enex/Keep, then N4.
