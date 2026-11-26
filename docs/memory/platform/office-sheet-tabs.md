---
name: office-sheet-tabs
description: The sheet tabs' menu (pg_office_view.js, OE2b) that adds, renames, deletes or moves a sheet of an .xlsx: where it opens, what it puts in the list of changes, how the tabs are drawn
---

## Where is it, and what does it send?
- In the sheet tabs of `assets/js/pg_office_view.js` (no bundle, no .min twin), in the File Manager's file window and a channel's preview. `view.edits()` sends each sheet change in its place in the list (`office-sheets.md` has the entries and the server's refusals); a refusal's message shows as the save's error, the changes still waiting.
- Offered only when the preview's `sheets_edit.ok` is true (not signed, no password to change, structure not protected).
- The open sheet's tab clicked again, a right click on a tab, the menu key or Shift+F10 open the menu: Rename, Move left, Move right, Delete sheet X. F2 on a tab renames. The + button after the tabs adds a sheet, named "Sheet{n}" (translated) until typed over. Delete is not offered for the last sheet shown.
- A name is typed in place of the tab: Enter keeps it, Esc leaves the sheet as it was. The view checks the server's name rules against the sheets as the list leaves them and names the problem in the status line.

## How does a waiting change look?
- Since OF3 (plan office-sheet-steady) sheet changes go into the one list beside cells, formats, rows and columns, any number of them (`office-sheet-change-list.md`). The tabs are drawn again from the list: a renamed tab shows its new name, a moved one stands in its new place, a deleted one is gone, an added one is a tab whose empty grid is typed into before the save; each changed sheet's tab carries the changed dot. Escape in the grid drops the change while it is the last one.
- Before OF3 one sheet change was a save of its own, struck through or dashed on the tabs, with "Undo the sheet change".

## What was checked?
- The server's tests (`office-sheets.md`), the list's tests and the browser run of `office-sheet-change-list.md`.
