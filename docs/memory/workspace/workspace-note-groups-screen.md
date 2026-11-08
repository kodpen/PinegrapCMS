---
name: workspace-note-groups-screen
description: Note groups on the My Notes screen — workspace_note_groups.js (folders, New group, group menu, Move to group, search, closed folders in the browser) and its hooks in startNotes()
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

## What is not there yet?
- Drag and drop and long press (G3), tabs (G4), shared groups (G5/G6), the lock pane hiding a locked folder's notes (L steps).
