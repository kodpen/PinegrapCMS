---
name: workspace-note-groups-screen
description: Note groups on the My Notes screen — workspace_note_groups.js (folders, New group, group menu, Move to group, drag and drop, search, closed folders in the browser) and its hooks in startNotes()
---

## Where is the screen code?
- `assets/js/workspace_note_groups.js`, bundled into `workspace.min.js` ahead of `workspace_replies.js` (`tests/workspace_saved_replies_picker_test.php` pins replies right before `workspace.js`). It exposes `window.PGWsNoteGroups` (`mine`, `sharedLoose`, `menuItems`, `placeNew`, `groupNotes`).
- `startNotes()` in `workspace.js` builds the context in `groupsCtx()` (kit, groups, notes, `drawItem`, `ctxMenu`, `colorPicker`, `setGroups`, `reload`) and falls back to the old flat lists when `CFG.notes_groups` (`ws_note_groups_ready()`, `screen.php`) is off or the script is missing. A note's row is `drawItem(note)`, shared by the flat sections and the folders.

## What does My notes show?
- Own groups as folders (chevron, colour dot, name, count; a lock instead of the count when locked), each with its notes: pinned first, then `group_sort`. Then own notes in no group. A shared note the person keeps in their group sits in that folder and leaves Shared with me (`sharedLoose()`).
- Closed folders are kept in `localStorage` key `pg_ws_note_groups_closed` (try/catch: without storage every folder opens). A search opens every folder holding a hit and hides the others.
- New group and the group menu's Change the group use one dialog (name, palette colour via `colorPicker()`); Delete the group asks first and says the notes stay.
- Move to group in a note's menu (only once the person has a group): a select of No group and the groups, sent as `ws_note_place` at the end.
- New note while a note in a group is open: saved first, then placed last in that group (`placeNew()`).

## How do drag and drop work (G3)?
- Rows of My notes (`noteRow()`) and folder heads are `draggable`; a module-level `drag` ({kind: note|group}) is set on dragstart, so a file dragged in for the import is never taken. Drop places: a row in a folder (top or bottom half: before / after, position from `group_sort` without the dragged note), a folder head (note in, last; folder before / after), a folder's body (in, last), a loose row or anywhere else in My notes (out of its group). Marks: `.ws-nb-drop-before/after/into`, `.ws-nb-dragging` (backend.src.css).
- `placeNote()` asks first: into a shared group (everyone will see it), out of one (they lose it unless shared on its own), a shared note into a locked group (locking takes its shares back); somebody else's note into a shared group is refused with a toast (the server refuses it too). Groups carry `shared` from `ws_note_groups()`.
- Keyboard and touch: Move to group, Move up / Move down in a note's menu (inside a folder of two or more) and in a folder's menu; a long press opens a row's or folder's menu (`onContext()`). Shared-with-me rows are not draggable; Move to group places them.

## What is not there yet?
- Shared groups on the screen: `workspace-note-groups-shared-screen.md`. The lock on the screen (a closed locked folder offers the PIN through `ctx.lockedBody`): `workspace-note-lock-screen.md`. Tabs over an open note: `workspace-note-tabs.md`.
