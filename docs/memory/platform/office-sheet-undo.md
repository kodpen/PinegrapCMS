---
name: office-sheet-undo
description: Undo and Redo of a workbook's list of changes (pg_office_view.js, OF4): the bar's pair, Ctrl+Z / Ctrl+Y / Ctrl+Shift+Z, what one press takes back, Escape keeping the list
module: office
---

## Where are Undo and Redo?
- At the start of the sheet bar (`.office-format`), before Bold: two `pg-icon-button pg-icon-button-sm`, `bi-arrow-counterclockwise` "Undo (Ctrl+Z)" and `bi-arrow-clockwise` "Redo (Ctrl+Y)" (the strings `doc_undo`, `doc_redo` the Word sheet uses, `office-docx-undo.md`). Greyed while there is nothing to undo or redo.
- The pair shows on every sheet of an editable workbook, the format controls beside it only where cells take a format (`office-sheet-format-controls.md`).
- Keys anywhere in the workbook (grid, tabs, bar): Ctrl/Cmd+Z undo, Ctrl/Cmd+Y and Ctrl/Cmd+Shift+Z redo (`historyKey()`), kept from the File Manager's own Ctrl+Z. A field keeps its own: the find box, a tab's name being typed.
- In the cell being typed in the keys are the field's while its value differs from the one it opened with; with that value they go to the list. A press of the button while typing keeps the typed value first, so that is the change undone.

## What is one change?
- Each change made to the list (`office-sheet-change-list.md`): a cell's value kept (Enter, Tab, a click away, Delete), one part chosen on the bar for the selection, a row or column inserted or deleted, a sheet added, renamed, deleted or moved.
- A state is a copy of the whole list (`listHistory()`, `window.pgOfficeView.sheetList.history`), kept only when the change altered the list; so Bold then Italic on one range, one entry of the list, are two presses, and a value typed back to the file's leaves no empty step.
- The last 100 states stay (`changeList(100)`); a new change drops what could be redone. `view.reset()` (after a save, or dropping every change) empties both ways.

## What does a press put back?
- The list as it was; the grid and the tabs are drawn again from it (`listBook()`), the sheet on show kept open by its id (or the nearest place when it left), its scroll kept, the same cell selected; the focus stays in the workbook.
- Before OF4 Escape in the grid dropped the last format, row, column or sheet change, and a quiet "Undo the format change" button dropped the last range (string `format_undo`, gone). Now Escape never drops a waiting change: in the cell editor it only puts back the value it opened with.

## What was checked?
- `tests/office_sheet_list_test.php`: `listHistory()` in Node (one press per part, copies kept, redo dropped, the limit, clear) and the source (the pair, the keys, no Escape drop, reset clears).
- Sandbox, Chromium (Playwright), 1440 and 390 px. The File Manager's file window (New spreadsheet): typing, Bold and Italic undone one by one with Ctrl+Z, Ctrl+Y, Ctrl+Shift+Z, the buttons; a new change dropping Redo; Escape keeping the count; a row inserted above row 2 and typed into, both undone and redone; saved, the file read back as the list left it. A channel's preview (New spreadsheet from the paperclip): the same keys and buttons, a sheet added, undone and redone from the tabs.
- Not tried: Firefox, Safari, a phone keyboard, an input method.

Related: docs/memory/platform/office-sheet-change-list.md, docs/memory/platform/office-docx-undo.md
