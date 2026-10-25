---
name: notes-import-export-sync
description: Plan (2026-10-19) for Workspace notes: export, Markdown/zip, .enex and Keep import, note revision and delete done; N3c sources, HTML reader, later Notion/OneNote; slices, open questions
---

## What is this plan?
- Owner request (2026-10-19): bring notes in from and out to other note apps, and keep them in sync. Notes, their API and limits: docs/memory/workspace/workspace-notes.md. Notion and OneNote: docs/memory/plans/reverse-oauth-leftovers.md; Keep, Evernote and Apple Notes have no usable API, file route only.

## What is done?
- N1 export (PR #528); N2 import of Markdown/zip (PR #553), Evernote .enex (PR #644), a Google Keep Takeout zip (PR #1656); the upload's steps and checks (PR #1570); N3a revision (PR #1663, schema 11.0.84); N3b delete with `ws_note_deletions` (PR #1674, schema 11.0.85). Memory: docs/memory/workspace/workspace-notes-*.md, one file a part.
- The owner approved the three N3 schema changes on 2026-10-25: `ws_notes.revision`, `ws_note_deletions` (both built) and `ws_note_sources`.

## What is left, in order? (one PR each)
1. N3c sources: `ws_note_sources` (user_id, provider, external_id, hash, note_id) in a schema step of the open version (the next free Workspace label after 11.0.85, `docs/memory/release/version-numbering.md`), added to `get_tables()`. Written where the import creates or updates a note (`ws_note_import_write()`), keyed by provider (keep, enex, markdown) and an id the file carries (Keep: the JSON entry name; Evernote: its guid when exported, else title + created; Markdown: front matter, else the path in the upload). Read in `ws_note_import_plan()` so a known note shows as "update" or "same" instead of "new"; a note deleted since drops its source row (`ws_note_delete()`).
2. N2 HTML: a single `.html` file (a web clip or another app's export) laid out in the stage like the Keep and Evernote readers (`notes_keep.php`, `notes_enex.php`); add `html` to `ws_note_import_extensions()`.
3. Small, any session may take one: a share taken back, or a channel left, is not in `deleted`, so a sync client keeps that note (record it per person like a deletion?); the panel still sends `base`, not `revision`; in the import's picked list two different files of one name, size and time count as one, and rows found unreadable are not counted in the heading.
4. N4 outside apps: Notion through the MCP alias ("save to my notes" target in `mcp_form.php`), OneNote through a Microsoft connect purpose; both wait on the reverse-oauth decisions.

## What is owed without a PR?
- Run schema steps 11.0.84 and 11.0.85 twice on a dev database (`docs/memory/release/rerun-upgrade-steps.md`): only CI's install and upgrade jobs ran them.
- On the owner's Windows dev site (IIS, OneDrive folder), confirm the notes import now takes .md files (the is_writable() fix, PR #1497) and the folder checks of PR #1557: both were shown by a Linux emulation only.

## What is still open?
1. Headings and lists: imports flatten them (headings bold lines, every item `- `); whether `ws_render_body()` later draws them for notes only.
2. A read-only token feed like the calendar's (default: no; sync clients use OAuth 2.1 + REST).
3. Whether the Workspace's other upload doors (channel attachments sent one by one as base64, note attachments sent in parallel) take the import's upload steps (picked list, progress, cancel): not asked yet.

## Where to start?
- Item 1 (N3c). Read docs/memory/workspace/README.md, workspace-notes-import.md and workspace-notes-import-keep.md first; light mode, one PR, `CLAUDE.md` Claim work.
