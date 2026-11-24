---
name: agent-instructions-note-lines
description: The agent instructions' block: a plan or long explanation is a note of the channel, changed in place with its revision; an older own note is moved there; API routes only, no repository tool
---

## What does the block tell an application agent?
- `ws_agent_instructions_note_lines()` in `includes/workspace/agent_instructions.php`, between the writing lines and the never-list of `ws_agent_instructions_text()`. The feature: `channel-notes-written.md`.
- A plan or a long explanation (more than a few paragraphs, or a text kept up to date) is a note of the channel, not messages.
- Written once with `POST /workspace/channels/{id}/notes` (`title`, Markdown `body`); the note belongs to the channel: who may post there changes it, `GET /workspace/channels/{id}/notes` lists it, it does not fill the owner's own notes (`channel-note-owned.md`). The answer gives `note_id` and `revision`.
- A note written earlier among the owner's own notes and shared in a channel is moved there with `POST /workspace/notes/{id}/move` and `channel_id`, one call each.
- Its first line says up to which PR or message it is written (a PR's full address or `<#message:ID>`), changed with every update.
- Changed in place, never posted again: `GET /workspace/notes/{id}` for the body and `revision`, then `POST /workspace/notes/{id}` with the whole body and that revision; a 409 `changed_meanwhile` means read again, merge, send again.
- The request is still closed with the answer route: one short line pointing at the note's card with `<#message:ID>`.

## Why does it name no tool?
- `decisions-made-product-92.md`: text written for an AI agent is English, a plain string, never `lang()`; the site's text names no repository tool. `tools/ws_note.php` is for this repository's sessions only (`workflow/agent-channel-note-tool.md`).

## What changed for running agents?
- Adding the block changed every agent's `instructions_version` (`agent-instructions.md`), so a session reads the text again at its next brief.
- The channel's notes and the move line changed the version again (PR #11272).
- Test: `test_workspace_agent_instructions_note_lines` in `tests/workspace_agent_instructions_test.php` (its place, the routes, the move and list routes, no `tools/`, `.php` or git, the version change).
