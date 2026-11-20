---
name: sheet-import-door-safety
description: The spreadsheet import door's guards from the bug hunt (#10619): reader by content, the CSV written row by row, waiting workbooks swept by age
module: sheet_import
---

## Which reader does an upload get?
- Which reader: the file's content, not its name (`pg_sheet_import_content_kind()`): an OLE file is .xls, a zip with an OpenDocument spreadsheet mimetype .ods, one with `xl/workbook.xml` .xlsx, any other zip its name's reader. Text goes on as a CSV file, so a tab text saved as `.xls` imports and a workbook named `.csv` is read as one. Products > Import and the bank import still go by the name (their own readers do).

## How does a big sheet stay out of memory?
- The CSV is written row by row as the sheet is read (a 40,000-row sheet took 20 MB held whole before the hunt); a late reader error empties it and refuses.

## What removes a workbook left at the sheet question?
- A waiting workbook nobody answers for (Cancel, a closed tab, an ended session) is removed by its file's age: every import post sweeps `data/temp/sheet_import_*.bin` older than the hour (`pg_sheet_import_sweep()`), whichever session kept it.

## What did the bug hunt change?
- Hunt asked by the architect (agent channel message 10243), PR #10619: the row-by-row CSV, the sweep and the content check above (tests `tests/sheet_import_doors_test.php`).
- Left as issue #10622: a number cell shown with leading zeros by its format (phone, post code, tax ID) loses them; the reader (`sheet_read.php`) does not apply the format.

Related: docs/memory/platform/sheet-import-upload.md
