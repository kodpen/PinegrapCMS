---
name: notes-screen-opening
description: Which note My Notes opens on (address, the note last open, else the first listed), the phone opening on the list, the next after a delete, the first note's card
---

## Which note does My Notes open on?
- `?note=` in the address wins (a link, a channel, a search hit), listed or not; `?group=` and `?new=` keep their own start.
- Else the note last open in this browser (`localStorage` `pg_ws_notes_open_<person id>`) while it is still listed; else the first of the list as ordered in ⋯ (pinned first), My notes before Shared with me.
- Chosen by `firstOpen()` in `assets/js/workspace_note_list.js`, called from `openFirst()` in `startNotes()`; tested in `tests/workspace_note_list_test.php`. No table, column or endpoint.

## And on a phone?
- Below 1024 px (where `.ws-nb` shows one card at a time) the screen opens on the list with no note chosen; only an address naming a note opens one. `wide()` in `startNotes()` holds the width; keep it equal to the stylesheet's.

## What happens when the open note is deleted or left?
- `forget()` opens the note after it as ordered, else the one before (`nextOpen()`); below 1024 px the list shows instead. With no note left, the first note's card.

## What shows with no note at all?
- One card, no list beside it: "Write your first note", "A note is yours alone until you share it.", New note (`showList()`, `.ws-nb-none`). The first note written or shared brings the list card back; on a wide screen a shared one opens.
- The old "Pick a note…" pane and its second New note are gone (`placeholder()` removed in the redraw, `notes-screen-drawing.md`).

## And a search that finds nothing?
- Said in the list card ("Clear" in place of the rows); the open note stays.
