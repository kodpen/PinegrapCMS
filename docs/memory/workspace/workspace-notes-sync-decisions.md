---
name: workspace-notes-sync-decisions
description: How note apps sync with Workspace notes (OAuth 2.1, REST notes routes, 30-day deletions, export vault), no secret-address feed (#3880), import routes, headings and lists (#2420)
---

## What does a sync client use?
- An OAuth 2.1 token (an `api_devices` row, cut by the account's revocation hooks: password change, sign out everywhere, account deletion; `api/api-oauth-server.md`) and the REST notes routes, all also MCP tools: the paged list with `updated_since` (workspace-notes.md), `revision` and 409 `changed_meanwhile` (workspace-notes-revision.md), deletions and person shares taken back kept 30 days (workspace-notes-deletions.md), versions. A one-off copy is the export vault (workspace-notes-export.md).
- Nothing else is offered: there is no second credential and no table for one.

## Is there a read-only secret-address feed for notes? (#3880)
- No (decided 2026-10-31, `workflow/decisions-made-product-13.md`). A note holds private text and attachment links; an address pasted into another app is a long-lived bearer secret with no scope or expiry that the revocation hooks do not cut. The calendar has one (`workspace_ical.php`, workspace-personal-ical.md) only because calendar apps subscribe by address; note apps can do OAuth. A request for a notes feed is answered by this entry, not built.

## Which apps can notes come in from?
- By file: Markdown or a zip vault (workspace-notes-import.md), Evernote .enex (workspace-notes-import-enex.md), a Google Keep Takeout zip (workspace-notes-import-keep.md), one web page (workspace-notes-import-html.md). Keep, Evernote and Apple Notes have no usable API, so the file is their only route.
- Through the person's own account (#3180): Notion over the site's `notion` MCP alias (PR #3212, workspace-notes-import-notion.md), OneNote over Microsoft Graph (PR #3291, workspace-notes-import-onenote.md).
- A file brought in again is matched by its source id instead of added twice (PR #1812, workspace-notes-sources.md).

## What happens to headings and lists on import? (#2420)
- Headings are kept and drawn in notes only (PR #2649, workspace-text-headings.md); messages and tasks keep `#` as a word.
- Lists stay one level: no nested-list drawing was added; `ws_note_import_convert()` only turns `*` / `+` items into `- ` (indent kept as text).

## How do attachments travel?
- From the screen as a form part over XHR with progress and Cancel, no picked-file list (#3745, PR #3774, workspace-attachment-uploads.md).

## What was never checked outside CI?
- Schema steps 11.0.84, 11.0.85 and 11.0.86 ran only in CI's install and upgrade jobs, never twice on a dev database (`release/rerun-upgrade-steps.md`).
- The .md import on a Windows site (IIS, OneDrive folder: the `is_writable()` fix, PR #1497) and the folder checks of PR #1557 were shown by a Linux emulation only.
