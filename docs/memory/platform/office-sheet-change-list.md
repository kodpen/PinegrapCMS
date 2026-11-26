---
name: office-sheet-change-list
description: The workbook view's one list of changes (pg_office_view.js, OF2, OF3): cells, formats, rows, columns and sheets in order, the grid and tabs drawn from it, the count
module: office
---

## How does the grid keep its waiting changes?
- `renderBook()` keeps one ordered list, `steps`: a typed cell `{sheet, cell, value}`, a format range `{sheet, range, set}`, a row or column `{op: insert|delete, sheet, axis, at}` (`at` a row number or 0-based column, the line inserted before), a sheet change `{op: sheet_add|sheet_rename|sheet_delete|sheet_move, sheet, to}`. Every place in an entry is the workbook's at that point of the list.
- The pure helpers (`window.pgOfficeView.sheetList`): `listCell()`, `listFormat()`, `listLook()`, `listBook()`, `listEdits()`.
- `listBook()` replays the list on the sheets the server sent and gives the workbook it leaves: sheets (copies with an `id`, rows, name, `edit` locks and areas moved, `added` rows and columns, `changed`), the typed `values` and the format `sets` of each cell. The grid and the tabs are drawn from it (`workbook` in `renderBook()`), so an inserted row, a new column and an added sheet are typed into before the save (OF3, plan office-sheet-steady).
- An inserted row is drawn empty and its head (and an inserted column's) is marked `office-head-added`; an added sheet is an empty grid of 30 rows and 10 columns like a new spreadsheet; the server makes the rows a cell is typed into (`office-save-list.md`).
- Sort and filter of a sheet are kept by its `id`, so they follow a moved sheet.

## How are cells and formats merged?
- The parts chosen one after the other for the same range are one change; the same range after anything else is a new one.
- A cell typed again moves to the end with its new value, but only past formats and cells: after a row, column or sheet change its address names another cell, so a new entry is made. A cell given back the value it has without its entry leaves the list.
- `view.edits()` sends the list in order (`office-save-list.md`), each sheet named as the list leaves it at that point (after a rename the new name); the cell being typed in goes last.
- Before PR #11946 a format waited alone; before OF3 a row, column or sheet change was a save of its own: cells were not typed while one waited and the menus said "Save the changed cells first…" / "Save the other changes first…" (those strings and the waiting-change sentences left with OF3).

## What does the status line say?
- "{1} change(s) not saved." (`unsaved`): each entry counts one; the recalculation note follows when a cell, a row or a column is among them. A tab is marked changed when any entry touches its sheet.

## How is a change undone?
- Escape in the grid drops the list's last change when it is a format, a row, a column or a sheet change; a cell is undone by typing its value again. The bar's "Undo the format change" drops the last range. Undo and Redo of the whole list come with OF4.

## What was checked?
- `tests/office_sheet_list_test.php` (Node over the view's source): order, merging, the cell after a row change, `listBook()` (rows, styles, shown texts, values, formats, locks and areas moved, sheets added, renamed, moved, deleted, the sent sheets untouched), the save list with names at each point, the strings gone.
- OF3 on a sandbox with Playwright, File Manager's New spreadsheet in the file window and a channel's preview (New spreadsheet from the paperclip), at 1440 and 390 px: cells typed, a row inserted above row 2 and typed into, Bold on it, a column inserted left of A and typed into, a sheet added and typed into, the first renamed, the new one moved left; saved and opened again with every value, look and tab in place. A row deleted and a sheet deleted, each dropped again with Escape.
- At 390 px the file window's grid pane is about one row high under the hint line (`.office-pane` flex `min-height: 0`), so pointer clicks were dispatched there; so before OF2 too.
