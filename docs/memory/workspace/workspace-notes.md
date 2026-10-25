---
name: workspace-notes
description: Workspace notes (ws_notes): tables, limits, body format, versions, an assistant's answer under the line that asked, the paged list, what the API offers and lacks
---

## Where do notes live?
- `ws_notes` (user_id owner, title, body, pinned, source_message/channel/user, channel_id made from it, folder_id for attachments, updated_by, created/updated_at), shares `ws_note_shares` (person with can_edit, or a channel card), versions `ws_note_versions` (30 kept by `ws_note_version_keep()`: first change, 600 s gap, another person, or a save under 60 % of the size), titled blocks `ws_blocks`. Code `includes/workspace/notes.php`, screen `startNotes()` in `assets/js/workspace.js`, actions in `includes/workspace/actions.php` (`ws_note_*`).
- Limits: `WS_NOTE_MAX` 60000 characters, `WS_NOTE_LIMIT` 2000 notes a person. Access is owner, edit or view (`ws_note_access()`); administrators get nothing by rank. Deletes are hard.
- The body is the Workspace's Markdown subset drawn by `ws_render_body()` (bold, `_italic_`, strike, code, links, GFM tables with `=` formulas, checklists, fences, `::: Title` blocks, one level of lists and quotes: workspace-editor-lists.md); headings are not drawn. Tags are tokens (`<@user:12>`, `<#order:1045>`), normalised on save by `ws_tokens_normalise()`.

## How does an assistant answer inside a note?
- `@Claude` or Pinegrap AI on a line of the note queues a `ws_ai_requests` row with `note_id` and `note_text` (`ws_note_claude_ask()`); the answer lands in `note_reply`. The screen polls `ws_note_claude_state()` and, for answered or failed requests, calls `ws_note_claude_deliver()`, which takes the request once (`note_done`) and writes the answer under the line that asked as a `**Claude:**` / `**Pinegrap AI:**` block.
- The delivery is a save like any other (PR #520): it keeps the state before as a version (`ws_note_version_keep()`) and indexes titled blocks (`ws_blocks_index()`). The note is never cut: `ws_note_answer_fit()` shortens the answer to what the note has left (ellipsis), and when fewer than 20 characters are left the request is set aside as failed with "The note is full…", which the screen shows as a toast. Before, the whole note was cut to 60000 characters with no version, so a nearly full note lost its tail for good. Test: `tests/workspace_notes_test.php`.

## What does the API offer?
- `GET/POST /workspace/notes`, `GET/POST|PATCH /workspace/notes/{id}` (dry run through `ws_note_save_check()`, Idempotency-Key, `base` → 409 `changed_meanwhile`), versions list and get; generic `workspace:read/write` scopes; every note route is an MCP tool. The object carries `created_at` and `updated_at`.
- The list is paged (issue #511, PR #566): `ws_notes_page_rows()` runs own pinned, other own, then shared notes (`ws_notes_position_pack()`: group, `updated_at`, id), `limit` up to 200 (default 100), `next_cursor` until `has_more` is false, `page.total` every match. `search` and `updated_since` are in the query. Before, 500 own and 500 shared were cut first, `updated_since` filtered after the cut and `has_more` was always false, so a sync client lost older notes silently.
- The screen's list (`ws_notes_list()`) shows every own note (at most `WS_NOTE_LIMIT`) and as many shared ones, instead of the latest 500. Lists read the text cut to `WS_NOTE_LIST_BODY` (4000) characters, enough for the name and excerpt; a single note is read whole. Test: `tests/workspace_notes_page_test.php`.
- Still missing (plan N3, schema waits for the owner): delete, deletion records, a per-note revision. Plan: docs/memory/plans/notes-import-export-sync.md.
