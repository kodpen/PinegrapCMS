---
name: office-csv-preview
description: .csv and .tsv as a sheet in Quick Look and a channel's Preview (PR #10484): separator and character set guessed, the choice above the sheet, limits, view only
module: office
---

## What does a .csv or .tsv show?
- Since PR #10484: Quick Look in the File Manager and a channel's Preview (file card, Files tab, the file's menu) draw it as one sheet named after the file, through the doors a workbook uses (`explorer_office_preview`, `ws_file_office`) and `pg_office_read()` → `pg_office_csv_read()`. Before it, Quick Look said "no preview" and a channel offered only its text window.
- Only looked at: `pg_office_preview_kind()` gives `csv`, while `pg_office_kind()`, which every edit asks, still gives ''. The file window keeps opening it in the text editor and a channel keeps its Edit text window: that is where it is changed. Editing a .csv as a sheet is not built (`plans/office-own-code.md`).

## How are the character set and the separator guessed?
- Character set (`pg_office_csv_encoding()`): a UTF-8 byte order mark, or bytes that are all valid UTF-8, read as UTF-8; anything else as Windows-1254, which Excel and older programs on a Turkish Windows save in, converted by the code page's table (`pg_mail_single_byte_to_utf8()`). The mark is never shown; a byte that is not UTF-8, read as UTF-8, shows as U+FFFD.
- Separator (`pg_office_csv_delimiter()`): of comma, semicolon and tab, the one that splits the first 20 records (of the first 64 KB) into the same number of cells, two or more, most often; then the one giving more cells; then the name's own, tab for .tsv and comma otherwise, which is also the answer for one column. A separator inside quotes is not counted, so Turkish Excel's semicolon file with decimal commas reads as semicolon.

## What does the choice above the sheet do?
- Two segmented controls, Separator (Comma, Semicolon, Tab) and Character set (UTF-8, Windows-1254 (Turkish)), pressed on what the sheet was read with (the answer's `csv`). A press asks the same door again with `delimiter` (comma, semicolon, tab) and `encoding` (utf-8, windows-1254), read by `pg_office_csv_choice()`; a value not offered is guessed. Nothing is stored: the next opening guesses again.
- The window gives `pgOfficeView.render()` an `options.reread(choice, done)`; without it no choice is drawn. A refusal is shown under the choice and the sheet stays.

## How are the cells read, and how much?
- `pg_office_csv_records()`: a quoted cell runs over separators and line breaks, a doubled quote is one quote, text after the closing quote is kept; CRLF, LF or a lone CR ends a record; a blank line is an empty row; a quote never closed runs to the end; rows carry their record's number.
- The workbook preview's limits (`office-files.md`): 2,000 rows, 200 columns, 120,000 cells. The read takes the file's first 8 MB (`PG_OFFICE_MAX_CSV`) in whole lines; the record it stops inside is left out and the sheet says only the beginning is shown.
- The records are split on the file's own bytes (every character looked for is ASCII) and only the kept cells are converted, so the file is never held twice: an 8 MB Windows-1254 file read to 2,000 rows took about 26 MB over the request's start, against 54 MB when the whole text was converted first.
- Tests: `tests/office_csv_test.php`.

Related: docs/memory/platform/office-files.md, docs/memory/plans/office-own-code-steps.md
