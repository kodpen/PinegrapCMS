---
name: workspace-notes
description: Workspace notes (ws_notes): tables, limits, body format, versions, an assistant's answer under the line that asked, the paged list, what the API offers, a note written in a channel
---

## Where do notes live?
- `ws_notes` (user_id owner, title, body, pinned, source_message/channel/user, channel_id from it, folder_id (attachments), updated_by, updated_app_id, created/updated_at), shares `ws_note_shares` (a person with can_edit, or a channel card, whose can_edit lets the channel's posters edit: `channel-notes-written.md`), versions `ws_note_versions` (30 kept by `ws_note_version_keep()`: first change, 600 s gap, another person, a save under 60 % of the size), titled blocks `ws_blocks`.
- Code `includes/workspace/notes.php`, screen `startNotes()` (workspace.js), actions `ws_note_*` (actions.php).
- Dashboard notes card: `includes/dashboard/v2/cards/notes.php`, `ws_notes_latest()` (`ui/dashboard-v2-notes-card.md`).
- Limits: `WS_NOTE_MAX` 60000 characters, `WS_NOTE_LIMIT` 2000 notes a person. Access is owner, edit or view (`ws_note_access()`); administrators get nothing by rank. Deletes are hard.
- The body is the Workspace's Markdown subset drawn by `ws_render_body()` (bold, `_italic_`, strike, code, links, GFM tables with `=` formulas, checklists, fences, `::: Title` blocks, lists, quotes (`workspace-text-lists.md`), headings (`workspace-text-headings.md`)). Tags are tokens (`<@user:12>`, `<#order:1045>`), normalised on save (`ws_tokens_normalise()`); record tags also in `ws_refs` (`workspace-ref-sources.md`).

## How does an assistant answer inside a note?
- `@Claude` or Pinegrap AI on a line of the note queues a `ws_ai_requests` row with `note_id` and `note_text` (`ws_note_claude_ask()`); the answer lands in `note_reply`. The screen polls `ws_note_claude_state()`; for answered or failed requests `ws_note_claude_deliver()` takes the request once (`note_done`) and writes the answer under the line that asked as a `**Claude:**` / `**Pinegrap AI:**` block.
- The delivery is a save like any other (PR #520): a version and the titled blocks' index (`ws_blocks_index()`). The note is never cut: `ws_note_answer_fit()` shortens the answer to the room left (ellipsis); with under 20 characters left the request fails with "The note is full…", shown as a toast. Test: `tests/workspace_notes_test.php`.

## What does the API offer?
- `GET/POST /workspace/notes`, `GET/POST|PATCH /workspace/notes/{id}` (dry run through `ws_note_save_check()`, Idempotency-Key, `base` → 409 `changed_meanwhile`), versions list and get; generic `workspace:read/write` scopes; every note route is an MCP tool. The object carries `created_at` and `updated_at`.
- The list is paged (issue #511, PR #566): `ws_notes_page_rows()` runs own pinned, other own, then shared notes (`ws_notes_position_pack()`: group, `updated_at`, id), `limit` up to 200 (default 100), `next_cursor` until `has_more` is false, `page.total` every match. `search` and `updated_since` are in the query; a shared note counts as changed when shared since `updated_since` (`s.created_at`).
- The screen's list (`ws_notes_list()`) shows every own note and as many shared ones. Lists read the text cut to `WS_NOTE_LIST_BODY` (4000) characters; a single note is read whole. Test: `tests/workspace_notes_page_test.php`.
- The revision an update sends back (PR #1663): workspace-notes-revision.md.
- A message sharing a note carries `note_card` (#2082, PR #2239; revision, updated_at, can_edit and changed_by since PR #10742 and #10797): one `ws_note_cards_map()` per page, `ws_api_message_note_card()` per row; null with no share, a share taken back or a deleted message. Test: `tests/workspace_api_note_card_test.php`.
- `POST /workspace/channels/{id}/notes` writes a note and its card in a channel in one move (PR #10742): `channel-notes-written.md`.
- Deletes over the API, remembered 30 days for sync clients (PR #1674), as is a share taken back (PR #1983): workspace-notes-deletions.md.
- Re-import: PR #1812 (workspace-notes-sources.md). Sync: workspace-notes-sync-decisions.md.
