---
name: notes-import-export-sync
description: Plan (2026-10-19) for Workspace notes: export, Markdown/zip, .enex, Keep and HTML import, note revision, delete and sources done; small items, later Notion/OneNote; open questions
---

## What is this plan?
- Owner request (2026-10-19): bring notes in from and out to other note apps, and keep them in sync. Notes, their API and limits: docs/memory/workspace/workspace-notes.md. Notion and OneNote: docs/memory/plans/reverse-oauth-leftovers.md; Keep, Evernote and Apple Notes have no usable API, file route only.

## What is done?
- N1 export (PR #528); N2 import of Markdown/zip (PR #553), Evernote .enex (PR #644), a Google Keep Takeout zip (PR #1656), one web page .html (PR #1776); the upload's steps and checks (PR #1570); N3a revision (PR #1663, schema 11.0.84); N3b delete with `ws_note_deletions` (PR #1674, schema 11.0.85); N3c sources with `ws_note_sources` (PR #1812, schema 11.0.86, docs/memory/workspace/workspace-notes-sources.md). Item 1: the panel sends `revision` (PR #1833). Memory: docs/memory/workspace/workspace-notes-*.md, one file a part.
- The owner approved the three N3 schema changes on 2026-10-25: `ws_notes.revision`, `ws_note_deletions` and `ws_note_sources` (all built).

## What is left, in order? (one PR each)
1. Small, any session may take one: a share taken back, or a channel left, is not in `deleted`, so a sync client keeps that note (record it per person like a deletion?); in the import's picked list two different files of one name, size and time count as one, and rows found unreadable are not counted in the heading.
2. N4 outside apps: Notion through the MCP alias ("save to my notes" target in `mcp_form.php`), OneNote through a Microsoft connect purpose; both wait on the reverse-oauth decisions.

## What is owed without a PR?
- Run schema steps 11.0.84, 11.0.85 and 11.0.86 twice on a dev database (`docs/memory/release/rerun-upgrade-steps.md`): only CI's install and upgrade jobs ran them.
- On the owner's Windows dev site (IIS, OneDrive folder), confirm the notes import now takes .md files (the is_writable() fix, PR #1497) and the folder checks of PR #1557: both were shown by a Linux emulation only.

## What is still open?
1. Headings and lists: imports flatten them (headings bold lines, every item `- `); whether `ws_render_body()` later draws them for notes only.
2. A read-only token feed like the calendar's (default: no; sync clients use OAuth 2.1 + REST).
3. Whether the Workspace's other upload doors (channel attachments sent one by one as base64, note attachments sent in parallel) take the import's upload steps (picked list, progress, cancel): not asked yet.

## Where to start?
- Item 1 (small items, one PR each). Read docs/memory/workspace/README.md and the workspace-notes-*.md file of the part first; light mode, `CLAUDE.md` Claim work.
