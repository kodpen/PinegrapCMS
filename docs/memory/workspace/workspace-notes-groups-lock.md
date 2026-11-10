---
name: workspace-notes-groups-lock
description: Overview of note groups, shared groups, tabs, the PIN lock and media in notes (owner 2026-11-07): how the parts hold together, what was left out, the keyboard and screen-reader ways, open ends
---

## What is it?
- Groups: a person's own folders in My Notes (name, colour, order); a note sits in one of the person's groups or none, notes shared with them too. Drag and drop moves notes and orders groups. A group is shared like a note (people read/edit, or a channel); its notes are shared through it. An open note in a group shows the group's notes as tabs. A PIN lock (off until set up) locks own notes and groups; a locked note or group is never shared. A file link alone on a line of a note is drawn as its preview. Owner decision: `workflow/decisions-made-workspace-8.md`; schema: `workflow/decisions-made-schema-12.md`.

## How does it hold together?
- `ws_note_places` (user_id, note_id → group_id, sort) says who keeps which note where; a reader sees each note once (own place, else the shared group that brought it).
- A group's share writes `ws_note_shares` rows with `via_group`; every reader of note shares works on them unchanged, and taking the group's share back deletes only its own rows, so a direct share stays.
- Lock: `ws_notes.locked`, `ws_note_groups.locked`, `ws_note_locks`; `ws_note_behind_lock()`; the open lock lives in the session, and nothing acting as the person (screen, API, MCP, assistants, search, export) gets a locked note's text, files or versions while it is closed.
- Media: no format change; `[name](url)` of a site file alone on a line becomes a block in `ws_render_body(…, $note = true)` and in the writing box; export, import and the API keep the link.

## What was left out on purpose?
- Groups inside groups; note shares over the API (sharing stays on the screen, for groups too); encrypting a locked note in the database (the owner chose the server lock); the API cannot open the lock.

## Which are the keyboard and screen-reader ways?
- Drag and drop has menu twins: a note's Move to group, Move up and Move down; a folder's Move up and Move down; the context-menu key or a long press opens a row's menu. After a move from a menu the list is drawn again and the focus goes to the moved note's row (`data-note-id`) or the folder's toggle (`data-group-id`), unless it already sits somewhere else (`refocus()` in `workspace_note_groups.js`).
- A folder: toggle with `aria-expanded`/`aria-controls`, body `role="group"` named after the folder, lock mark `role="img"` with a name, its ⋯ button named "More: <group>".
- A note row's pin mark (`drawItem()`, workspace.js, "Pinned") and lock mark (`mark()`, workspace_note_lock.js) and a folder's lock mark are `role="img"` with an `aria-label`, and drop the `aria-hidden` that `icon()` / `kit.icon()` sets; before, the label was never read (PR #NUM).
- Tabs: ARIA tablist, one tab in the Tab order, arrows/Home/End move, Ctrl+Shift+arrow drags a tab along (`aria-keyshortcuts`); a cut title is read whole from a `visually-hidden` span.
- Lock: the toolbar button's `aria-haspopup` is `dialog` before set-up, `menu` after; a refused PIN's message is `role="alert"`, tied to the fields with `aria-describedby`, the fields `aria-invalid`. Pinned in `tests/workspace_notes_a11y_test.php`.

## Which files say more?
- Store: `workspace-note-groups.md`, `workspace-note-group-shares.md`; screen: `workspace-note-groups-screen.md`, `workspace-note-tabs.md`, `workspace-note-groups-shared-screen.md`; API: `workspace-note-groups-api.md`; lock: `workspace-note-lock.md`, `-gate.md`, `-readers.md`, `-screen.md`; media: `note-media.md`.

## What is still open?
- A message's `note_group_card` (`ws_note_group_cards_map()`) is not in the API. No unit test drives the database paths of locking with shares.
