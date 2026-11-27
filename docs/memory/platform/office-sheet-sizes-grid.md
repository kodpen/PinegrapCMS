---
name: office-sheet-sizes-grid
description: The sheet grid's column widths, row heights and frozen top rows (pg_office_view.js, SA4): how they are drawn, the drag on a head's edge, the menu items, the list entry
module: office
---

## How does the grid draw the sizes the reader sends?
- `sheetTable()` reads `sheet.sizes` (`office-sheet-sizes.md`): a column with a width holds its head and every cell to it (`sizeCell()`, `data-width` so `paint()` keeps it, a wrapped cell included); a row with `h` gets that height (`sizeRow()`), its cells without padding above and below. A table row is never lower than one line of its text.
- Units (`window.pgOfficeView.sheetSizes`): a width in characters is `chars × 7 + 5` px, a height in points `× 4/3` px, as a spreadsheet at 100 % with Calibri 11. Read back to two decimals inside the save's limits (0.5 to 255 characters, 1 to 409 points).
- The default width and height of the sheet are not drawn: a column with no width of its own still takes its widest text.

## How are the frozen rows drawn?
- Rows `n <= sizes.frozen` among the first ones shown are marked `data-pinned`; `pinRows()` makes their cells sticky under the heads once the table is laid out (`placePins()`, next frame for a book not yet on the page). A pinned cell gets an opaque background in `paint()`; `padHeadings()` adds their height to the scroll padding.
- No stylesheet rule: every size and pin is an inline style, so the step changed no `backend.src.css`.

## How is a size set?
- A drag on a column head's end edge or a row head's bottom edge (4 px with a mouse, 8 px by touch; the cursor turns to col-/row-resize), drawn live, kept on release; the click that ends a drag opens no menu. A touch drag the browser takes for a scroll is dropped.
- The head menu and a cell's menu (right click, Shift+F10) add, after the insert and delete items: Column width…, Row height… (a cell's menu both), and Freeze top row or Unfreeze rows. Width and height open a number field under the head or cell (`askSize()`), filled with the size now (its own, else the drawn one); Enter or Apply keeps it, Escape closes; out of range: "Enter a number above 0 and up to {1}."
- Offered where rows and columns may be inserted (`sheetShiftable()`: editable, not protected, not sorted or filtered); the server refuses the rest with its own message.

## What goes into the list?
- `{op: width|height|freeze, sheet, at, value}` (`listSize()`): the same size set again at once is one entry; a size moves no cell (`listMoves()`), counts one change, brings no recalculation note. `listBook()` lays it on the sheet (`sizeSheet()`); `listEdits()` sends `{op: width, column: "C", width}`, `{op: height, row, height}`, `{op: freeze, on}`. Undo and Redo as any change.
- A column insert or delete moves the widths; a column inserted between two of the same width takes it (the file's span widens, `pg_office_shift_span()`); heights move with their rows.

## What was checked?
- `tests/office_sheet_sizes_test.php`: entries and merging, the workbook left (widths, heights, frozen, shifts, taken off), the save list, the units, the strings.
- Sandbox, Chromium (Playwright), File Manager's New spreadsheet at 1440 px: drags on B and row 2, Column width… with a refused 999 then 20, Freeze top row from a row head, the pinned row staying while the pane scrolls, Shift+F10 items, Ctrl+Z/Ctrl+Y of the freeze.
- Saved and reopened there: 167 px, 145 px, 56 px, row 1 pinned; the file's `<cols>`, `ht` and `<pane>` checked. At 390 px the file window's grid pane is under 1 px high (the bar and hint take the room), so only the keyboard path was run there. A channel's preview at 1440 and 390 px: drag, both sizes by keyboard, freeze, saved; the file checked.
- Not tried: Firefox, Safari, a real touch screen, right-to-left.
