---
name: office-sheets
description: Add, rename, delete and move a sheet of an .xlsx (office_sheets.php, OE2a): the save entry, what each change writes, what is refused and why, one change a save
module: office_sheets
---

## How is a sheet added, renamed, deleted or moved?
- A save whose `cells` list holds one entry: `{op: sheet_add, to: "Name"}`, `{op: sheet_rename, sheet, name, to: "New"}`, `{op: sheet_delete, sheet, name}` or `{op: sheet_move, sheet, name, to: 2}` (`sheet` and `to` are places in the preview's sheet list, `name` the sheet's name as opened). Sent to the endpoints cell edits use (`explorer_file_save`, `ws_file_office_save`).
- `pg_office_xlsx_save()` hands it to `pg_office_xlsx_sheets_save()`, under the same lock, version check and package rebuild as cell and row saves. Mixed with other changes it is refused (`none`). No new endpoint, permission or schema.
- `pg_office_save_package()` takes a `removed` list from its `$prepare` too: the parts a delete takes out of the package.
- The preview of a workbook read for editing carries `sheets_edit` {ok, why: signed|password|protected}: whether the tabs may offer sheet changes.
- The sheet tabs' menu that sends it: `office-sheet-tabs.md`.

## What does each change write?
- Add: an empty worksheet after the last sheet, as `xl/worksheets/sheetN.xml` (the first free N), with the next sheet id and relationship id, the content type and relationship type and namespaces the workbook's worksheets already have.
- Rename: the sheet's name, and every place that writes it before `!`: cell formulas, validation and conditional formulas, chart series, table column formulas, link locations, defined names, a pivot cache's source sheet. Names in quotes and other workbooks' sheets stay. A new name is quoted when Excel would quote it. Formula results are kept.
- Delete: the sheet's part, its rels part, its printer settings, its relationship and content type, its local defined names (print area), its calculation chain entries (the chain goes when none is left). Local defined names of later sheets move up a place; when it was the open sheet, the next visible sheet (or the one before) opens and is marked selected.
- Move: the order in `workbook.xml`; local defined names and the open sheet follow their sheets. No other part changes.
- Every other part is carried byte for byte. `docProps/app.xml` keeps the old sheet titles; Excel writes it again on its next save.

## What is refused, with its reason?
- Every change: a signed file, a password to change it, a protected workbook structure (`workbookProtection lockStructure`).
- Add and rename: a name that is empty, longer than 31 characters, holds `\ / ? * [ ] :` or a control character, starts or ends with an apostrophe, is `History`, or is another sheet's name in any case (`pg_office_sheet_name_error()`).
- Delete: a formula, defined name, chart, pivot cache or table elsewhere that points to the sheet; a sheet holding drawings, comments, tables, pivot tables or any part but printer settings; a chart sheet; the last visible sheet.
- Move: a reference across sheets (`Sheet1:Sheet3!A1`) anywhere, as the order sets what it takes in.
- A worksheet, chart, pivot cache or table part past 32 MB to read: `sheets_large`. Messages: `pg_office_sheets_message()`; the file stays as it was.

## What was checked?
- `tests/office_sheets_test.php`: the request, the name rules and quoting, renames inside formulas, each change saved into a fixture package and read back by `pg_office_read()` with untouched parts compared byte for byte, every refusal leaving the file as it was. Not opened in Excel or LibreOffice.
