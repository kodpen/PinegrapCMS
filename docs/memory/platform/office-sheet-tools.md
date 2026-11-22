---
name: office-sheet-tools
description: The workbook view's tools (pg_office_view.js, OE4): sort by a column, filter by value, find in the workbook, Download as CSV of the open sheet; nothing written to the file
module: office
---

## Where are they, and for which files?
- `assets/js/pg_office_view.js`, no server change: every workbook it draws (.xlsx, .ods, .xls, .csv/.tsv) in the File Manager and a channel's preview, over the rows the server sent (2,000 rows, 200 columns at most), never the whole file.
- A line under the sheet tabs (`.office-tools`, look A): the search field (`pg-input pg-input-sm`) with "{1} of {2}" and Previous/Next match (`pg-icon-button`), the sort and filter in words with "Clear sort and filter" (quiet), and "Download as CSV" (quiet) at the end. It wraps at 390 px.
- A column's letter is a button on every sheet: Sort A to Z, Sort Z to A, Filter by value, and the removal of a sort or filter set on it; on a sheet whose rows and columns may change, the insert and delete items follow a line. Right click, the menu key or Shift+F10 on a cell of an editable grid offer the same for its column. On a sheet only looked at, the column letters are one Tab stop, the arrows move between them.

## How do sort and filter work?
- Per sheet while the view is open, never saved.
- Sort: numbers by size first, then text in the page language's order (Turkish letters, numbers inside text by size, case ignored), empty cells last either way; equal values keep the file's order. Dates are compared as the reader sends them (yyyy-mm-dd), so by day.
- Filter by value: the column's shown texts once each (at most 300, "(Empty)" for blanks) as checkboxes in the menu; clearing one hides its rows at once. Filters of several columns add up.
- Header row: the first row stays on top, and out of the value list, when it holds text and no number and more rows follow (`headerRow()`); the tools' line says "Row 1 stays on top as the header."
- While a sheet is sorted or filtered its cells are only looked at: no typing, no format bar, no row or column insert or delete; the status line says "Clear the sort and filter to change cells." A range or a row's place is then always the file's.

## How does find work?
- Every sheet, in the tabs' order, rows in the order shown (a row filtered out is not found), case ignored in the page's language. It searches the text a cell shows (number format's text, a typed change). Typing goes to the first match from the open sheet on; Enter / Shift+Enter or the arrows step, opening the match's sheet; Escape empties the field before the window sees it. Matches are tinted, the current one ringed. The focus stays in the field.

## What does Download as CSV write?
- The open sheet as the grid shows it: its rows in the sorted and filtered order (header first), each cell's shown text, so numbers with the site's separators and their format (10,00 ₺, 12%), dates and times as the preview shows them (2026-03-01, 08:30), a typed change not saved yet as typed. A blank row the file does not hold is not written; columns run to the last one holding a text.
- UTF-8 with a byte order mark, comma, CRLF, RFC 4180 quotes. A text a spreadsheet would read as a formula (`=`, `+`, `-`, `@`, tab, return first) gets a leading apostrophe unless it is a shown number (-12,50, -₺1.234).
- Built in the browser (Blob, `a[download]`), named after the sheet (`csvName()`). A cut-short sheet downloads only the rows shown, and the line says so.

## What was checked?
- `tests/office_sheet_tools_test.php` runs `window.pgOfficeView.sheetTools` in Node: header row, sorts, filters, value list, find, CSV text and fields, file names.
- Headless Chromium on a page with the built panel CSS, 1440 and 390 px: sort, filter, find over two sheets, download, editable and read-only. Not checked: the File Manager and a channel themselves, the dark theme, a non-ASCII download name (headless Chromium named it "download").

Related: docs/memory/platform/office-sheet-head-menu.md
