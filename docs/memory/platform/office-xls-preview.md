---
name: office-xls-preview
description: .xls as a workbook in Quick Look, the file window and a channel's Preview (PR #10542), read through the sheet reader, view only; Save a copy as .xlsx beside the file or as a new message
module: office
---

## What does an .xls show?
- Since PR #10542: Quick Look and the file window in the File Manager, and a channel's Preview, draw it as an .xlsx is drawn: worksheets in order (chart and macro sheets left out), a hidden sheet flagged, the sheet's own row numbers, gaps kept. Before it, it had no preview.
- Doors: `explorer_office_preview`, `ws_file_office`, then `pg_office_read()` → `pg_office_xls_read()`. `pg_office_preview_kind()` gives `xls`; `pg_office_kind()`, which every edit asks, gives '', so it is never edited in place (`plans/office-own-code.md`).
- Sheet names and hidden flags: `pg_sheet_xls_sheets()` (`sheet-reader.md`), from the BOUNDSHEET records. Rows: `pg_sheet_rows($path, 'xls', …)` through `pg_office_ods_rows(…, 'xls')`; a cell is shown as an .ods cell is (`office-ods-preview.md`). A formula shows the result the file stores.

## What are the limits?
- The workbook preview's: 2,000 rows and 200 columns a sheet, 120,000 cells a workbook.
- The file: 8 MB (`PG_OFFICE_MAX_XLS`); past it "This file is too large to show here. Download it to open it." A file the reader refuses: "The file could not be read." More than 1,000 sheets: too large.
- The reader parses the whole file again for each sheet, so the preview reads sheets while their count times the file's size stays within 64 MB (`PG_OFFICE_MAX_XLS_WALK`); later ones are listed empty and cut.
- Memory: the reader holds the file and a sheet's cells as the library's arrays, about 40 bytes for each byte of the file (8 MB, 260,000 cells: 300 MB peak, 1.1 s preview, 2.4 s copy). `pg_office_xls_memory()` raises `memory_limit` to 512 MB, as the product import does, unless it is higher.

## What does Save a copy as .xlsx do?
- A button above the sheet (`pgOfficeView.render()` with `options.saveCopy`), the same in the file window, Quick Look and a channel, with the note "This file is only shown here. A copy as .xlsx can be changed; formulas come over as their results." The answer is said under the button.
- File Manager: `explorer_office_copy` writes the copy into the file's folder, named with .xlsx, numbered by `get_unique_name()` when taken, then reloads the list. Offered (`can_copy` in the preview answer) to whoever may read the file and add a file to its folder (`check_edit_access()`, outside the recycle bin); never for an ERP document.
- Channel: `ws_file_office_copy` sends it as a new message's file in reply to the original, for whoever may post in the channel (`can_copy`). The upload checks of a channel apply.
- Written by `pg_office_xls_copy()` through the shared writer's `pg_xlsx_write_book()` (`workspace/workflow-tables-export.md`): every sheet whole, hidden ones kept hidden, each cell typed: text, number as the file wrote it, boolean, error, date, date and time, time. Past 256 MB of parsing (sheets × file size, `PG_OFFICE_MAX_XLS_COPY_WALK`): "This file is too large to copy here."
- Not carried: a formula's expression (the reader does not decode BIFF formula tokens; the result is kept as a value), number formats other than dates and times, styles, column widths, merged cells.

## How is it tested?
- `tests/office_xls_test.php` on the sheet reader's fixtures (`tests/fixtures/sheet_read/*.xls`): the preview, a hidden sheet (a byte of book.xls changed in the test), refusals, the copy read back by `pg_sheet_rows(…, 'xlsx')` cell for cell and by the .xlsx preview as the .xls preview showed it, the writer's names and failures, the doors.

Related: docs/memory/platform/office-files.md, docs/memory/platform/sheet-reader.md, docs/memory/plans/office-own-code-steps.md
