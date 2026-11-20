---
name: channel-documents
description: Plan (owner 2026-11-20, agent channel poll 179): a document in a channel is a note written there, drawn as it reads and changed in place by people and agents, with versions; KD1 to KD5
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel message 10569): what the Markdown button adds comes out in the channel drawn as Markdown, while ordinary messages stay as they are; agents explain a subject with it, a plan for one, and change that document when the plan changes.
- Owner (poll 179, message 10572): "İkisi de plana girsin"; after the Activity tab (`plans/channel-activity.md`). Recorded in `workflow/decisions-made-workspace-42.md`.

## What is there today?
- A note shared in a channel is a card that draws the note as it is now: headings, tables, checklists, lists (`ws_note_cards_map()`, `ws_render_body()` with `$note`), in a box 18rem high that scrolls, with Read and, for who may edit, Edit, which leaves for the notes screen.
- Notes keep 30 versions and a revision; the API's update takes `base` and answers 409 when the note changed meanwhile; every note route is an MCP tool (`workspace/workspace-notes.md`).
- Missing: no way to write one from the channel in one move; no route shares a note in a channel, so an agent cannot post one; a save does not touch the card's message, so an open screen and `changed_since` miss it; only the owner and the people a note is shared with may change it; the card names the person a key acts for, never the agent.
- A `.md` file in a channel opens in a text window, is not drawn in the conversation, and an agent cannot write over it (`api/workspace-message-file-api.md`).

## What did the architect decide? (2026-11-20)
- The document is a note: not a file and not a new record kind. Drawing, versions, the refusal of a stale write, export, the lock and the MCP tools are there already. The product's word stays "note".
- Messages keep `#` and `---` as words (owner, #2420, `workspace/workspace-text-headings.md`).
- Who may change it: its owner and the people it is shared with, as today. Its owner may open it to the channel ("People who may post here can edit"), off unless chosen; stored as `can_edit` on the channel's share row, a column the row has.
- A note an agent writes belongs to the person its key acts for and counts in that person's 2,000 notes.
- An edit writes no message, wakes nobody and writes no inbox line: the card says who changed it and when; whoever wants it announced writes a message.

## What did the owner answer?
- The agent's name on an edit: yes to the two columns `ws_notes.updated_app_id` and `ws_note_versions.app_id` (poll 180, message 10580; `workflow/decisions-made-schema-37.md`). KD3 is free after KD1.

## What is built?
- Features: `channel-documents-features.md` (N1 to N10). Steps: `channel-documents-steps.md`. Claim as `[plan:channel-documents] <step> — <topic>`.

## What is left out?
- Headings in messages, a `.md` file drawn in the conversation, two people writing at once in one box, comments on a line, a document kind of its own, an edit announced by the site. Each is a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the card keeps its frame; the fold, the foot and the window are built by look A's recipes and seen on dev.
