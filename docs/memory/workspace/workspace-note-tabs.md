---
name: workspace-note-tabs
description: Note tabs over an open note on My Notes — workspace_note_tabs.js (group as ARIA tablist, marks, +, keys, drag to reorder via ws_note_place) and drawTabs() in startNotes()
---

## Where is the code?
- `assets/js/workspace_note_tabs.js`, bundled into `workspace.min.js` right after `workspace_note_groups.js` (`tests/workspace_note_tabs_js_test.php` pins it; `workspace_replies.js` stays right before `workspace.js`). It exposes `window.PGWsNoteTabs.draw(ctx)` and the pure helpers `pure.order`, `pure.step`, `pure.dropIndex`, `pure.short`, tested in Node by the same test file.
- `drawTabs()` in `startNotes()` (`workspace.js`) builds the ctx and puts the row first in the note's card (`#ws-nb-note-card`); `drawNote()` and every `drawList()` call it, so a save, rename or move redraws it. Nothing is drawn while `CFG.notes_groups` is off or a script is missing.

## What does the row show?
- The group's dot and name (a lock when the group is locked), a tab per note of the group, the open one selected, then + ("New note in this group", `startNew()`, which saves the open note and places the new one last in the group). A note in no group has no row; a new unsaved note started in a group (`newGroup`) shows its group's row with no tab selected, and + there stays in that group.
- Tab order is `group_sort` alone (tie by id), not pinned first as in the folder: a tab's index is the position `ws_note_place` counts.
- Marks per tab: `bi-lock-fill` when `note.locked` (L steps fill it), `bi-people` when the note is shared (not the owner's, or `with`/`in` not empty). Titles cut to 24 characters with an ellipsis, the whole title as `title`.

## How do keys, clicks and drags work?
- ARIA tabs with manual activation: `role=tablist` labelled by the group name, `role=tab` buttons with `aria-selected`, roving `tabIndex`; the card becomes `role=tabpanel` labelled by the open tab. Left/Right wrap (swapped in RTL), Home/End; Enter, Space or a click opens the note through `openNote()`, which saves the one being written first.
- Reorder: drag a tab (HTML5 drag, a line shows where it lands) or Ctrl+Shift+Left/Right; both send `ws_note_place` with `position` (counted without the moved note), then reload the list; focus follows the moved tab. Off during a search (the list then holds only hits, so positions would be wrong) and with fewer than two tabs.
- On a phone the row scrolls sideways (`overflow-x: auto`); the open tab is scrolled into view without moving the page.

## What about shared groups and the lock?
- Shared groups (G6): the row also serves a group shared with the person, with a people mark; no + on a read-only group; a person-plus button opens the owner's Share dialog (`workspace-note-groups-shared-screen.md`). A locked tab opens to the lock pane (`workspace-note-lock-screen.md`).
