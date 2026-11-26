---
name: office-sheet-head-menu
description: The sheet grid's row and column head menu (pg_office_view.js) that inserts above/below, left/right or deletes one row or column: where it opens, what it puts in the list of changes
---

## Where is it, and what does it send?
- In the sheet grid of `assets/js/pg_office_view.js` (no bundle, no .min twin): the File Manager's file window and a channel's preview send `view.edits()` and show a refusal's message as the save's error, the changes still waiting.
- A row's number or a column's letter opens the menu; right click on a cell, the menu key or Shift+F10 open it for the cell's row and column.
- A column's menu starts with its sort and filter items (`office-sheet-tools.md`), the insert and delete items after a line; a sheet sorted or filtered offers only those. Items: Insert a row above, Insert a row below, Delete row N; Insert a column on the left, Insert a column on the right, Delete column X. No "below" or "right" on the sheet's last row or column.
- Below row N is put in as an insert at N+1, right of column C as an insert at D: the server inserts before the line it gets.
- Offered on a sheet whose cells may be edited and that is not protected.

## How does a change look while it waits?
- Since OF3 (plan office-sheet-steady) it goes into the one list of changes beside cells, formats and sheet changes, any number of them (`office-sheet-change-list.md`). The grid is drawn again as the list leaves it at once: an inserted row or column is there, empty, its head in the accent colour, and is typed into before the save; a deleted one is gone and the rest moved. Escape in the grid drops it while it is the last change.
- Before OF3 one row or column change was a save of its own, drawn as a line or struck through, with "Undo the row or column change".

## What was checked?
- The server's tests (`office-sheet-rows-columns.md`), the list's tests and the browser run of `office-sheet-change-list.md`.
