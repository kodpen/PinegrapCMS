---
name: office-sheet-abilities
description: Plan (owner 2026-11-26, poll 235): the steady spreadsheet editor's new abilities: more formats, column widths, row heights, a frozen top row, copy, paste, fill down, clear; steps SA1 to SA6
priority: 6
---

## What is asked?
- Owner (2026-11-26, agent channel poll 235, message 14401): all eight abilities offered for the two editors (`workflow/decisions-made-product-115.md`). This plan holds three of the workbook's four; the formulas are `plans/office-sheet-formulas.md`.

## When does it start?
- After OF4 of `plans/office-sheet-steady.md`: every step uses the change list that plan builds. Until then no step is free: the architect marks lines `free` when OF4 has landed.

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- SA1 (M, `includes/fn/office_format.php`): the format entry also takes underline, strikethrough, a font size (8 to 72 points), borders (none, all sides, outline, bottom) and wrap text, written as today's formats are: new styles beside the old ones. The reader gives them in a cell's look. Tests: each read back, untouched parts byte for byte.
- SA2 (M, `pg_office_view.js`): the bar gets them, copied from its own controls: Underline and Strikethrough beside Italic (Ctrl+U too), a Size select, a Borders menu of the four choices, a Wrap text toggle. The grid draws each; a control shows the selected cells' state.
- SA3 (M, new `includes/fn/office_size.php`): a change entry for a column's width and a row's height (`<cols>`, a row's `ht` with `customHeight`) and one for "Freeze top row" on or off (the sheet view's pane). The reader gives widths, heights and the frozen row. Refused with its reason where the sheet's protection forbids it.
- SA4 (M): the grid draws the stored widths and heights. A drag on a column head's end edge or a row head's bottom edge sets one; the head menu's "Column width…" and "Row height…" ask a number, for the keyboard. "Freeze top row" in the head menu keeps row 1 in view while the grid scrolls. Each joins the change list.
- SA5 (M): Ctrl+C puts the selected range on the clipboard as tab-separated text. Ctrl+V writes such text (from Excel or this grid) from the selected cell on, as typed cells. Ctrl+D fills the range's first row down. Delete clears the selected cells. Locked cells are skipped and counted in the status line; a paste that passes the grid's limits is refused whole with its reason.
- SA6 (S) Close-out: `platform/office-sheet-format.md`, `office-sheet-format-controls.md`, `office-sheet-head-menu.md` and `office-overview.md` corrected, new `platform/office-sheet-sizes.md` and `office-sheet-clipboard.md`; this file deleted.

## What does every step keep?
- What `plans/office-sheet-steady.md` keeps: Save a button, no library, no schema, every text through `lang()`, look A.
- A file the writer cannot carry safely is refused with its reason, never written damaged (`platform/office-files-scope.md`); the cell locks hold (`platform/office-cell-locks.md`).
- SA2, SA4 and SA5 are tried in a browser on a sandbox, in the file window and a channel's preview, at 1440 and 390 px: no unit test reaches a drag or the clipboard. The PR says what was tried.

Related: docs/memory/plans/office-sheet-steady.md, docs/memory/plans/office-sheet-formulas.md, docs/memory/platform/office-sheet-format-controls.md
