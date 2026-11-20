---
name: workflow-table-import-values
description: What a file's values become in a workflow table import (PR #10370): a Windows-1254 CSV read, phone numbers and postal codes kept as text, a written "no" unticks a box on a keyed import
---

## Which encoding is a CSV read in?
- UTF-8; a value that is not is read as Windows-1254 (ISO-8859-9 where mbstring lacks it), what Turkish Excel writes for "CSV (comma delimited)" (`ws_wtable_import_utf8()`, per cell in `ws_wtable_import_file_table()`). The panel, the API import and the bulk product drafts share the reader.
- A table that cannot be kept between upload and confirmation is refused with "The file could not be read." (`ws_wtable_import_keep_json()`).
- Symptom before PR #10370: `json_encode()` failed on the bytes, an empty temp file was kept, and the upload landed on the import window with no confirmation and no error.

## When is a column of figures not guessed as Number?
- When any value is written as a code (`ws_wtable_import_code_like()`): a leading `+`, a leading `0` before a digit, or digits grouped by spaces other than in thousands (`532 123 45 67`; `1 500` is a number). The column stays text, the values as written.
- Symptom before PR #10370: `0532 123 45 67` was stored as 5321234567 and the postal code `06100` as 6100.
- Only the guess changes: a column the person makes Number still reads `ws_wtable_import_number()`.

## Which cells does a file row fill?
- `ws_wtable_import_filled()`: every cell with a value, and an unticked box whose field the file wrote (`no`, `hayır`, `0`); an empty field fills nothing. A keyed import (`workflow-table-key-column.md`) lays these over the matched row, so `Done=no` unticks Done; a row holding only a written no is no empty row. The dry run counts the same way.
- Symptom before PR #10370: the box stayed ticked while the report counted the row as changed.

## Tests
- `tests/workspace_wtable_import_test.php`: `_file_table`, `_guess`, `_filled`.
