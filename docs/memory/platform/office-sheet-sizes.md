---
name: office-sheet-sizes
description: Column width, row height and Freeze top row of an .xlsx (office_size.php, SA3): the save entries, how each is written, what the reader gives the grid (widths, heights, frozen row), refusals
module: office_size
---

## How is a width, a height or a frozen top row saved?
- Entries of a save's `cells` list: `{op: width, sheet, name, column: "C", width: 12.5}` (characters, above 0 to 255), `{op: height, sheet, name, row: 7, height: 20}` (points, above 0 to 409), `{op: freeze, sheet, name, on: true|false}`. A `null` width or height takes it off. Sizes are rounded to two decimals.
- `pg_office_size_request()` reads them; `pg_office_xlsx_save()` applies each as a step of kind `size` with `pg_office_xlsx_size_parts()` (`office-save-list.md`). Same doors as cell edits; no new endpoint, permission or schema. Columns past the grid's 200 are refused as `none`.

## How is each written?
- Width (`pg_office_xlsx_size_width()`): every `<col>` entry covering the column, in every `<cols>` list, is split around it (the others keep their width, style, hidden); the column gets `width` and `customWidth="1"`. A column no entry names gets its own entry in its place by `min`, in the last list; with no list, one right before `sheetData`. Taken off: `width`/`customWidth` go, an entry left with only `min`/`max` goes, an empty list goes.
- Height (`pg_office_xlsx_size_height()`): the row's `ht` and `customHeight="1"`; a missing row is made empty in its place. Taken off: both attributes go.
- Freeze top row (`pg_office_xlsx_size_freeze()`), in every `sheetView`: on puts `<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>` in place of any pane (frozen columns too, as Excel's Freeze Top Row), drops the view's `topLeftCell`, keeps one selection (the active pane's) with `pane="bottomLeft"`. Off takes out a frozen pane only (a plain split stays) and the kept selection loses its `pane`. No views: one `sheetViews` is made before `sheetFormatPr`/`cols`.
- Only the sheet part changes; values, styles, formula results stay (no `fullCalcOnLoad`).

## What does the reader give the grid?
- Each .xlsx sheet of `pg_office_xlsx_read()` has `sizes`: `widths` (0-based column => characters, a later `<col>` wins, cut to 200 columns), `width` (`defaultColWidth`, else `baseColWidth`, else null), `height` (`defaultRowHeight` or null), `frozen` (the first view's frozen `ySplit`, else 0). A row with `ht` says `h` (points).
- Read in the same streaming walk as the rows (`pg_office_xlsx_rows()`, `pg_office_size_note()`), only before `sheetData`, so custom sheet views after it do not count. Drawing them is SA4 of `plans/office-sheet-abilities.md`.

## What is refused, with its reason?
- Width on a protected sheet unless `sheetProtection formatColumns="0"` (`size_columns`); height unless `formatRows="0"` (`size_rows`); freeze when the workbook has `workbookProtection lockWindows` (`size_windows`; sheet protection does not stop it, as in Excel).
- As every edit: signed, password, a chart sheet, a sheet renamed since (`changed`), a part over the edit ceiling (`large`), comments, CDATA or a DOCTYPE in the part or rows without their number (`sheet`). Messages: `pg_office_size_message()`; the file stays byte for byte.

## What was checked?
- `tests/office_size_test.php`: the request, each writer on fixture text (splits, new entries and rows in place, taking off byte for byte), protection, the reader's notes, a mixed list saved and read back by `pg_office_read()` with untouched parts compared, every refusal and its place in a list. Not opened in Excel or LibreOffice.
