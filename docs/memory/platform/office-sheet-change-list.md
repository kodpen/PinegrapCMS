---
name: office-sheet-change-list
description: The workbook view's one list of waiting changes (pg_office_view.js, OF2): typed cells and format ranges in the order made, any number of ranges, the count on the status line, what still goes alone
module: office
---

## How does the grid keep its waiting changes?
- `renderBook()` keeps one ordered list, `steps`: a typed cell `{sheet, cell}` (its value stays in `changes`) or a format range `{sheet, range, set}`. The pure helpers are `listCell()`, `listFormat()`, `listLook()`, `listEdits()` (`window.pgOfficeView.sheetList`).
- A format is taken while cells wait and cells are typed while formats wait, on any number of ranges; each is drawn at once (`listLook()` lays every range of the sheet over a cell's look, in order).
- The parts chosen one after the other for the same range are one change; the same range after anything else is a new one. A cell typed again moves to the end, so it is written over every format laid on it before; a cell given back its file value leaves the list.
- `view.edits()` sends the list in order (`office-save-list.md`); the cell being typed in goes last.
- Before PR #11946 a format waited alone: the status line said "Save the other changes first, then change the format." or "One format change is saved at a time…" (`format_first`, `format_one`, both removed with `format_pending` and `changed`).

## What does the status line say?
- "{1} change(s) not saved." (`unsaved`): each typed cell and each range counts one; the recalculation note follows when a cell is among them.

## What still goes alone?
- A row or column inserted or deleted and a sheet change stay a save of their own until OF3 (plan office-sheet-steady): the format bar is hidden and cells are not typed while one waits, and the head and tab menus say "Save the changed cells first…" / "Save the other changes first…" while cells or ranges wait.
- Escape in the grid and the bar's "Undo the format change" drop the last range; Undo and Redo of the whole list come with OF4.

## What was checked?
- `tests/office_sheet_list_test.php` (Node over the view's source): order, merging, moving, dropping, looks, the save list, the strings gone.
- Sandbox with Playwright at 1440 and 390 px, File Manager's New spreadsheet in the file window and in a channel's preview (New spreadsheet from the paperclip): a cell typed, Bold over a range, a fill on a second range and a cell typed into it, Italic on a third, Escape dropping it; the count read 5 then 4; saved and read back with every value and look.
- At 390 px the grid pane shrinks to about one row under the long hint line in the file window (`.office-pane` flex `min-height: 0`), already so before this step.
