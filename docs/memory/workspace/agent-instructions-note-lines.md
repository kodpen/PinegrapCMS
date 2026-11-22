---
name: agent-instructions-note-lines
description: The agent instructions' block: a plan or long explanation is a note shared in the channel, changed in place with its revision; API routes only, no repository tool
---

## What does the block tell an application agent?
- `ws_agent_instructions_note_lines()` in `includes/workspace/agent_instructions.php`, between the writing lines and the never-list of `ws_agent_instructions_text()`. The feature: `channel-notes-written.md`.
- A plan or a long explanation (more than a few paragraphs, or a text kept up to date) is a note shared in the channel, not messages.
- Written once with `POST /workspace/channels/{id}/notes` (`title`, Markdown `body`, `channel_edit` true when the channel's people should change it too); the answer gives `note_id` and `revision`.
- Its first line says up to which PR or message it is written (a PR's full address or `<#message:ID>`), changed with every update.
- Changed in place, never posted again: `GET /workspace/notes/{id}` for the body and `revision`, then `POST /workspace/notes/{id}` with the whole body and that revision; a 409 `changed_meanwhile` means read again, merge, send again.
- The request is still closed with the answer route: one short line pointing at the note's card with `<#message:ID>`.

## Why does it name no tool?
- `decisions-made-product-92.md`: text written for an AI agent is English, a plain string, never `lang()`; the site's text names no repository tool. `tools/ws_note.php` is for this repository's sessions only (`workflow/agent-channel-note-tool.md`).

## What changed for running agents?
- Adding the block changed every agent's `instructions_version` (`agent-instructions.md`), so a session reads the text again at its next brief.
- Test: `test_workspace_agent_instructions_note_lines` in `tests/workspace_agent_instructions_test.php` (its place, the routes, no `tools/`, `.php` or git, the version change).
