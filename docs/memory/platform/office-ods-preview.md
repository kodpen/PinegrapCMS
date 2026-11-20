---
name: office-ods-preview
description: .ods as a workbook in Quick Look, the file window and a channel's Preview (PR #10519): read through the sheet reader, the preview's limits, the cell ceiling, the shared walk, view only
module: office
---

## What does an .ods show?
- Since PR #10519: Quick Look and the file window in the File Manager, and a channel's Preview (file card, Files tab, the file's menu), draw it as an .xlsx is drawn (`office-files.md`): sheet tabs in the workbook's order, a hidden sheet (its table style has `table:display="false"`) flagged as the .xlsx preview flags one, the sheet's own row numbers, gaps kept.
- Doors: `explorer_office_preview`, `ws_file_office`, then `pg_office_read()` → `pg_office_ods_read()`. Before it, Quick Look said "no preview", the file window showed the file's icon and a channel had no Preview.
- Only looked at: `pg_office_preview_kind()` gives `ods`, while `pg_office_kind()`, which every edit asks, gives ''. The answer's kind is `ods`, and `pgOfficeView.render()` draws it with no editing options, whatever the window passes. Editing an .ods is not built (`plans/office-own-code.md`).

## How is it read?
- `pg_office_open($path, $error, 'content.xml')` measures the package as for a workbook. `pg_office_ods_sheets()` streams content.xml once for the names and the hidden table styles, counting tables as the sheet reader does; each sheet's rows come from `pg_sheet_rows($path, 'ods', $error, $index)` (`sheet-reader.md`), and the walk is left once the preview has its rows.
- A cell as the .xlsx preview writes one (`pg_office_ods_text()`): a number without exponent, TRUE/FALSE, a date as Y-m-d (with H:i when it has a time), a time alone as hh:mm (hh:mm:ss with seconds), text and an error (#DIV/0!) as they are; a percent or a currency as its value (0.25).

## What are the limits?
- The workbook preview's: 2,000 rows and 200 columns a sheet, 120,000 cells a workbook; past them the sheet says only the beginning is shown.
- The reader's: content.xml up to 8 MB, and the cell ceilings of `ods-cell-ceiling.md` on what is read. A sheet past them refuses the file: "This file is too large to show here. Download it to open it." A ceiling passed only below the rows shown is not reached, as the walk stops there. A damaged part: "The file could not be read."
- The walk (`PG_OFFICE_MAX_ODS_WALK`, 64 MB): the reader walks content.xml from its beginning for each sheet, so sheets are read while their count times the part's size stays within it (8 of an 8 MB part, 64 of a 1 MB one); later ones are listed empty and cut, as an .xlsx's sheets past the cell limit are.
- Why the walk: without it 40,000 one-cell sheets (5.6 MB) meant 40,000 walks; the worst file at the limit (8 MB of empty nodes before the tables) takes 4–5 s.
- More than 1,000 sheets (`PG_OFFICE_MAX_ODS_SHEETS`): too large. 500,000 empty sheets fit in 7 MB and filled 512 MB as tabs.
- Tests: `tests/office_ods_test.php`: the sheet reader's LibreOffice `book.ods` and packages the test writes.

Related: docs/memory/platform/office-files.md, docs/memory/platform/sheet-reader.md, docs/memory/plans/office-own-code-steps.md
