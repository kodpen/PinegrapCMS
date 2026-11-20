---
name: channel-documents-steps
description: Plan channel-documents, steps KD1 to KD5: the server (write in a channel, the route, access by channel, the touch), the card and its windows, the agent's name, the session tool, close-out
---

## Which steps are there?
- KD1 (M) first: the server. Done: PR #10742.
  - `includes/workspace/notes.php`: `ws_note_access()` reads a channel row's `can_edit`; the save touches the cards' messages; `ws_note_channel_write_check()` and `ws_note_channel_write()`, shared by the panel and the API; the panel actions `ws_note_channel_write` and `ws_note_channel_edit` (the switch).
  - New `includes/workspace/api_channel_notes.php` (the route, its dry run through the check); `ws_api_message_note_card()` gains the three fields; `php tools/check_api_schema.php`.
  - Tests, red first: access by owner, person share and channel share, each with and without `can_edit` and the right to post; the touch; the Markdown rules; the refusals (locked, may not post, too long). Features: N2 to N7.
- KD2 (M) after KD1: the card and its windows.
  - New `assets/js/workspace_channel_notes.js` in the bundle list (`pg_js_bundles()`); in `workspace.js` only the attach menu's entry, `noteCardNode()`'s call and the card's menu items; the fold in `backend.src.css`; texts with `php tools/tr_add.php`.
  - The Versions drawer reads what the notes screen's version list reads; no second store.
  - Tests on the pure parts (the fold's state, the foot's words, which menu items a reader gets). Seen on dev: a note written in a channel, changed over the API while the channel is open, put back from a version; a wide screen and a phone. Features: N1, N8, N9.
- KD3 (S) after KD1: the agent's name (the owner's yes: poll 180, `workflow/decisions-made-schema-37.md`). Feature: N10.
  - KD3a done: PR #10797. The two columns (step 11.0.257), the save, `updated_app` / `changed_by` from `ws_notes_present()`, the card map and `ws_note_versions()`, the API's `changed_by` on `note_card` and `WorkspaceNote` (`workspace/workspace-note-changed-by-app.md`).
  - KD3b (S) after KD2 has landed: the card's foot and the Versions drawer in `workspace_channel_notes.js` draw the server's `changed_by` words (no text built in the script); the notes screen's version list too if it shows who saved. Not yet run twice on a dev database: KD3a's container had none.
- KD4a (S) after KD1, beside KD2: the session's tool. New `tools/ws_note.php` (new, read, update; exit 0 written, 1 refused, 2 usage; on 409 it says so and writes nothing; the base kept in `PATH.ws_note`). Done: PR #10800 (`workflow/agent-channel-note-tool.md`).
- KD4b (S) after #10774 (AW1) has landed: the line in the agent's instructions (`ws_agent_instructions_text()`, beside AW1's writing block; the instructions version changes by itself). The site's text names no repository tool: a plan or a long explanation goes into a note shared in the channel, changed in place with its revision, its first line saying up to which PR or message it is written. Feature: the agents' part.
- KD5 (S) close-out, after all: every N number checked against the code with its PR; `workspace/channel-notes-written.md` written; `workspace/workspace-notes.md`, `api/workspace-message-file-api.md` and `workflow/agent-channel-wake.md` corrected; the user documentation's page noted in `workflow/user-docs-pages.md`; this plan deleted.

## Which steps run side by side?
- After KD1: KD2 and KD4a; KD4b after #10774. KD3 enters `notes.php` after KD1 has landed and the card's script after KD2 has.
- `workspace.js` and `backend.src.css` are entered by KD2 and by KA2 of channel-activity (landed, #10813): never at once. The Activity tab comes first (owner, poll 179): KD1 may start beside KA1, KD2 waits for KA2's merge.

## What does each step's PR carry?
- `Decision: channel message 10572`; a migration step in KD3 alone; what was seen on dev where a step draws.
