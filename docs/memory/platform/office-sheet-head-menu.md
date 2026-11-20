---
name: office-sheet-head-menu
description: The sheet grid's row and column head menu (pg_office_view.js) that inserts above/below, left/right or deletes one row or column: where it opens, what it sends, one change a save
---

## Where is it, and what does it send?
- In the sheet grid of `assets/js/pg_office_view.js` (no bundle, no .min twin): the File Manager's file window and a channel's preview send `view.edits()` as for cell edits and show a refusal's message as the save's error, the change still waiting. No server change came with it.
- A row's number or a column's letter opens the menu; right click on a cell, the menu key or Shift+F10 open it for the cell's row and column. Items: Insert a row above, Insert a row below, Delete row N; Insert a column on the left, Insert a column on the right, Delete column X. No "below" or "right" on the sheet's last row or column.
- Below row N is sent as an insert at N+1, right of column C as an insert at D: the server inserts before the line it gets.
- Offered on a sheet whose cells may be edited and that is not protected. One change a save: with changed cells the menu only says "Save the changed cells first…"; while a line waits, cells are not typed in and the menu offers only "Undo the row or column change" (also Escape).
- The waiting line is marked on the grid (a line before or after it, or struck through) and named in the status line; its sheet's tab shows it as changed.

## What was checked?
- `node --check` and the server's tests (`office-sheet-rows-columns.md`); no browser run, the menu not tried by hand.
