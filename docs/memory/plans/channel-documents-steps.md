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
- KD3 (S) after KD1, only after the owner's yes to poll 180 (message 10580): the agent's name. The two columns by a migration step of the open version with `install_add_column()`, run twice on a dev database; the save writes the application when a key saved; the card, the versions and `note_card` name it. Feature: N10. The worker writes the columns into the PR body and asks the architect before ready.
- KD4 (S) after KD1, beside KD2: the session's tool and the instructions. New `tools/ws_note.php` (new, read, update; exit 0 written, 1 refused, 2 usage; on 409 it says so and writes nothing), the line in the agent's instructions (the instructions version changes). Feature: the agents' part.
- KD5 (S) close-out, after all: every N number checked against the code with its PR; `workspace/channel-notes-written.md` written; `workspace/workspace-notes.md`, `api/workspace-message-file-api.md` and `workflow/agent-channel-wake.md` corrected; the user documentation's page noted in `workflow/user-docs-pages.md`; this plan deleted.

## Which steps run side by side?
- After KD1: KD2 and KD4. KD3 enters `notes.php` after KD1 has landed and the card's script after KD2 has.
- `workspace.js` and `backend.src.css` are entered by KD2 and by KA2 of `plans/channel-activity.md`: never at once. The Activity tab comes first (owner, poll 179): KD1 may start beside KA1, KD2 waits for KA2's merge.

## What does each step's PR carry?
- `Decision: channel message 10572`; a migration step in KD3 alone; what was seen on dev where a step draws.
