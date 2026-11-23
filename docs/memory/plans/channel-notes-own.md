---
name: channel-notes-own
description: Plan (owner 2026-11-23): a note written in a channel belongs to the channel, off My Notes and off a person's limit, listed in the channel's Notes tab; steps CN1 to CN3
priority: 6
---

## What is asked?
- Owner (2026-11-23): the notes agents write in the agent channel fill his own notes; a note written in a channel stays a channel note (`workflow/decisions-made-workspace-49.md`).

## What is a channel's note?
- A `ws_notes` row with `owner_channel_id` above 0 (`workflow/decisions-made-schema-48.md`). `user_id` stays the writer (for an agent the person its key acts for), shown as "Written by"; `updated_app_id` names an agent as today.
- "Write a note" in a channel and `POST /workspace/channels/{id}/notes` write one from CN1 on. A person's own note shared into a channel stays that person's, with its share row.
- Access comes from the channel, with no share row: who reads the channel reads it, who may post there (`ws_can_post_channel()`) edits it; in an archived channel it is read only. The rule stays in the pure `ws_note_access_of()`. "People who may post here can edit" is not drawn on it.
- It is on nobody's My Notes (list, search, tabs, groups, "Export all", the dashboard notes card) and is not counted in `WS_NOTE_LIMIT`. A channel holds at most 2000 (`WS_NOTE_CHANNEL_LIMIT`).
- It takes no notes lock, no note group and no share with a person: those are a person's. Versions, the refusal of a stale write, one note's export, attachments and an assistant's answer inside it stay.
- The writer and whoever manages the channel delete it. Deleting its card's message leaves the note, still listed in the Notes tab. It is deleted with its channel, as the channel's messages are.

## Where does it open?
- Its card in the conversation, as today (`workspace/channel-note-card.md`), with "Written by <name>" at the foot.
- A Notes tab beside Files, shown once the channel has a note: the Files tab's rows (title, "Changed by <name> · <time>", the row menu Open, Versions, Export, Delete), newest change first, a search box over the titles, "Write a note" at the right of the tab's bar. On a phone as the Files tab is.
- API: `GET /workspace/channels/{id}/notes` (paged as the notes list is); the note routes read and write one by the channel's rights; the note object carries `owner_channel_id`.

## What happens to the notes written in channels before?
- The card menu of a person's own note shared in a channel gets "Move to this channel": the note becomes the channel's, its channel share row goes and it leaves My Notes. The way back is "Copy to my notes" on a channel's note.
- `POST /workspace/notes/{id}/move` with `channel_id` does the same for the caller's own notes, so a person's agent moves the plans it wrote in one go.

## Which steps? (all enter `includes/workspace/notes.php`: one worker at a time)
- CN1 (M), done (#11198): the column in a migration step; `ws_note_access_of()`; every reader of a person's notes leaves a channel's note out, held by a guard test like the notes lock's (`workspace/workspace-note-lock-readers.md`); the two writers; the channel's delete and the limit.
- CN2 (M): the Notes tab, the list route, "Written by", who deletes.
- CN3 (S): "Move to this channel", "Copy to my notes", the move route, the agent instructions' note lines, then the close-out.
- Check of a step on a sandbox with the Workspace on, 1440 and 390 px, both themes: a note written by a person and by an agent's key, My Notes before and after, an archived channel.

## What does the close-out do?
- Rewrites `workspace/channel-notes-written.md`, `workspace-notes.md`, `agent-instructions-note-lines.md` and `workflow/agent-channel-note-tool.md` to what was built and deletes this file.

Related: docs/memory/workspace/channel-notes-written.md, docs/memory/plans/channel-share-links.md
