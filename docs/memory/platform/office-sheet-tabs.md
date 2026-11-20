---
name: office-sheet-tabs
description: The sheet tabs' menu (pg_office_view.js, OE2b) that adds, renames, deletes or moves one sheet of an .xlsx: where it opens, what it sends, one change a save
---

## Where is it, and what does it send?
- In the sheet tabs of `assets/js/pg_office_view.js` (no bundle, no .min twin), in the File Manager's file window and a channel's preview. `view.edits()` sends one sheet change as the whole save (`office-sheets.md` has the entries and the server's refusals); a refusal's message shows as the save's error, the change still waiting.
- Offered only when the preview's `sheets_edit.ok` is true (not signed, no password to change, structure not protected).
- The open sheet's tab clicked again, a right click on a tab, the menu key or Shift+F10 open the menu: Rename, Move left, Move right, Delete sheet X. F2 on a tab renames. The + button after the tabs adds a sheet, named "Sheet{n}" (translated) until typed over.
- A name is typed in place of the tab: Enter keeps it, Esc leaves the sheet as it was. The view checks the server's name rules first and names the problem in the status line.

## How does a waiting change look?
- One change a save: with changed cells or a waiting row or column the menu only says "Save the other changes first…"; while a sheet change waits, cells are not typed in, and the tab menu and the grid's head menu offer only "Undo the sheet change" (also Escape in the grid).
- A renamed tab shows its new name, a deleted one is struck through, a moved one has a dashed border, an added one shows after the tabs; the status line says what happens on save.

## What was checked?
- `node --check` and the server's tests; no browser run, the menu not tried by hand.
