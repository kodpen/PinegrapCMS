---
name: office-sheet-clipboard
description: Copy, paste, fill down and clear of the workbook grid's selected cells (pg_office_view.js, SA5, PR #12168): the keys, the text format, locked cells, the limits a paste is refused by
module: office
---

## Which keys are there?
- On a selected cell of an editable sheet (the grid, not the cell being typed in): Ctrl/Cmd+C copies the selection, Ctrl/Cmd+V pastes from the selection's first cell on, Ctrl/Cmd+D fills down, Delete or Backspace empties every selected cell.
- C and V are the browser's own copy and paste events (`copyCells()`, `pasteCells()` on the table): no clipboard permission is asked. The grid's keydown stops their propagation so the File Manager's file copy (view_folders.php) never takes them.
- Ctrl+D copies the selection's first drawn row into the rows under it; a one-row selection takes the drawn row above it, as a spreadsheet does. A formula cell of the source row is not copied as its result: its column's cells are left and counted.

## What text goes on and comes off the clipboard?
- Tab-separated text as Excel, LibreOffice and a browser's sheets write it: values joined by tabs, rows by CRLF, a value holding a tab, a line break or a quote is quoted with its quotes doubled (`clipText()`). Copy writes each cell as its editor opens it (`shownValue()`), so a paste into this grid gives the same values.
- `clipRows()` reads LF or CRLF, drops the last line break, keeps empty values and rows; a quote that does not close a whole value is text.
- Only drawn rows count: a row number the sheet does not draw (a gap) is skipped by copy, and a paste goes down the drawn rows.

## What is kept and what is refused?
- Every pasted, filled or cleared cell is a typed cell of the list of changes (`office-sheet-change-list.md`), all of one key press one change of Undo (`listValues()`); a cell given the value it holds makes no entry.
- Locked cells (`office-cell-locks.md`) are left as they were and counted on the status line: "Locked cells left as they were: {1}."
- Refused whole, nothing changed, the reason on the status line: a block reaching past the last drawn row or column ("Not pasted: the copied cells reach past the last row or column shown. Select a cell with room for them."), over 5000 cells (the save's limit, also for a fill or a clear: "Not changed: at most {1} cells change at once."), a value over 32767 characters ("Not pasted: a cell holds at most {1} characters.") (`clipCheck()`, `clipCells()`).
- The pasted block is selected afterwards. A sorted or filtered sheet takes no paste (it is only looked at).

## What was checked?
- `tests/office_sheet_clipboard_test.php` (Node over the view's source): reading and writing the text, the limits, `listValues()` entries.
- Sandbox, Chromium (Playwright): the File Manager's New spreadsheet at 1440 px (paste of a quoted two-line value, copy and paste, fill down of a range and of one row, clear, Ctrl+Z, a too-wide paste refused, saved and the sheet XML checked); a protected sheet with columns C and D unlocked at 1440 and 390 px (locked B skipped and counted); a channel's preview (New spreadsheet from the paperclip) at 1440 and 390 px.
- Not tried: Firefox, Safari, a real Excel clipboard, a phone's paste menu.
