---
name: workspace-notes-groups-lock
description: Plan (owner 2026-11-07): Workspace notes in groups (personal, shared, drag and drop, tabs), a PIN lock on notes and groups, media previews in a note; parts G, L, M
---

## What is the status?
- Approved by the owner on 2026-11-07, schema included (`workflow/decisions-made-workspace-8.md`, `workflow/decisions-made-schema-12.md`). Steps: `workspace-notes-groups-lock-steps.md` (N0, G1-G7) and `-steps-2.md` (L1-L4, M1-M3, N9); screens and words: `-design.md`. Claimed as `[plan:workspace-notes-groups-lock] <step> — <topic>`.

## What is it?
- Groups: a person's own folders in My Notes (name, colour, order). A person keeps a note in one of their groups or in none, notes shared with them too. Drag and drop moves notes into, out of, along and between groups, and orders the groups.
- Shared groups: a group is shared like a note, with people (read or edit) or in a channel. Its notes are shared through it; people with edit put their own notes in it (still theirs); one who leaves takes their notes out.
- Tabs: an open note that is in a group shows the group's notes as tabs over it.
- Lock: off until the person sets a PIN in My Notes. Own notes and own groups lock; the PIN is asked again after the time the person chose; a code by e-mail opens it when the PIN is forgotten. A locked note or group is never shared.
- Media: a file link on a line of its own in a note (a canvas, a picture, a video, a sound, a PDF) is drawn as its preview, in the writing box and in the read view.

## How does it hold together?
- `ws_note_places` (user_id, note_id → group_id, sort) says who keeps which note where; a group's notes are its rows, whoever put them there. A reader sees each note once: in their own place, else in the shared group that brought it.
- A group's share writes `ws_note_shares` rows with `via_group` = the group, one per note and person or channel. Every reader of note shares (access, files, the list, sync, deletions) works on them unchanged; taking a group's share back deletes only its own rows, so a direct share stays.
- Lock: `ws_notes.locked` (the owner's), `ws_note_groups.locked`, `ws_note_locks` (PIN hash, minutes, wrong tries). A note is behind a person's lock when it is their locked note or sits in their locked group (`ws_note_behind_lock()`); while the lock is closed, its text, files and versions are given to nobody and nothing. The open lock lives in the session.
- Media: no format change. `[name](url)` of a file of this site alone on a line becomes a block in `ws_render_body(…, $note = true)` and in the writing box; export, import and the API keep the link, and old notes get previews as they are.

## What is left out?
- Groups inside groups, note shares over the API (the API has none today), encrypting a locked note in the database (the owner chose the server lock).

## Where does it start?
- N0, M1 and M2 at once; G1 and L1 once N0 lands. Two coordinators: one holds G, the other L and M. Steps whose files differ run side by side, as the steps files say; each new script is its own file in the workspace bundle, so the notes screen is not one step's at a time.
