---
name: office-xlsx-cell-values
description: How the Excel preview reads workbooks not written by Excel: prefixed elements, rows and cells without r, times and durations by the number format (PR #1118)
---

## Which workbooks looked empty or collapsed, and why?
- Open XML SDK writes every SpreadsheetML element with a prefix (`<x:row>`, `<x:c>`). XMLReader's `name` carries the prefix, so the reader found no rows ("The sheet is empty."). Shared strings, cell parts and rows now match on `localName` (`pg_office_xlsx_strings()`, `pg_office_xlsx_cell_parts()`, `pg_office_xlsx_rows()`); DOM's `getElementsByTagName()` already matched the local name.
- The `r` attribute is optional on `<row>` and `<c>` (ECMA-376). Without it every cell landed in column A, each overwriting the last, and the rows were numbered 0. A row without `r` is now the one after the last row read; a cell without `r` is the column after the last cell of its row (a referenced cell resets the count, as `includes/erp/bank_import.php` already did).

## How is a time or a duration shown?
- `pg_office_xlsx_date_styles()` gives each date-like style a kind from `pg_office_xlsx_format_kind()` instead of `true`: `date` (a day, month or year, with or without a time), `time` / `time_s` (a time of day), `hours` / `hours_s` (`[h]`, elapsed), `minutes` (`mm:ss`). Built-ins: 14–17 and 22 date, 18/20 time, 19/21 time with seconds, 45/47 minutes, 46 elapsed hours.
- An `m` after an hour or before a second is minutes, not a month; quoted text, escaped, padding (`_x`) and fill (`*x`) characters, `AM/PM` and brackets other than `[h]` are left out before deciding.
- `pg_office_xlsx_time()` prints `HH:MM` or `HH:MM:SS` rounded to the unit shown; a time of day leaves the days out, `[h]` counts all hours (`25:30:00`), a negative duration keeps its sign. Hours are always two digits (Excel's `h` shows `8:30`, the preview `08:30`). A `date` cell still goes through `pg_office_xlsx_date()`.
- Before: `0.3541` as `h:mm` showed `1899-12-30 08:30`, seconds were dropped, `[h]` durations became a date in 1899.

## What does it change for edits?
- `pg_office_xlsx_style_kinds()` (office_edit.php) still asks `isset()` of the map, so the cells taken as dates are the same, with fewer false hits from padding characters. A time typed into such a cell is still not parsed (only dates are) and is stored as text, as before.

## Tests
- `tests/office_xlsx_reader_test.php`: a prefixed workbook, cells and rows without `r`, time formats, `pg_office_xlsx_time()`, `pg_office_xlsx_format_kind()`.
