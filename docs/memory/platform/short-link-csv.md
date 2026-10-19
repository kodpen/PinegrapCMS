---
name: short-link-csv
description: Short links as a CSV file (2026.11.0): the list's export (from,to,code), import_short_links.php and its line rules, the reader's encodings, separators and heading, the formula guard.
---

## Why is there a CSV file of short links?
- A site that moves keeps hundreds of old addresses; they arrive as a spreadsheet (from, to, code). Redirects are short links (`short-link-redirects.md`), so the short link list exports and imports them.

## What does the export write?
- `view_short_links.php?export=csv` -> `pg_short_link_csv()`: `from,to,code`; from = PATH + name, to = the URL as entered or the path of the page, product group, product or file (a page with `?t=` for its tracking code), code = the stored answer.
- Every cell is quoted; a cell that starts with `= + - @`, tab or CR gets an apostrophe (`csv_cell()`) so a spreadsheet shows it instead of running it. BOM and CRLF, for Excel.
- Named, live links only: a one-time link has no name, a binned one is left out.

## How does the import read a file?
- `pg_short_link_csv_rows()`: BOM dropped; bytes that are not UTF-8 are read as Windows-1254 (ISO-8859-9 where mbstring lacks it); `;` or tab separates when the first line has no comma; a first line whose second cell holds no `/` is a heading; empty lines skipped; the apostrophe of the formula guard comes off again.

## What does a line do?
- `import_short_links.php` (manager, after 11.0.12) -> `pg_short_link_import()`: 5 MB and 10,000 lines at most. Each line is a URL short link: name from the first cell (`pg_short_link_name_from_address()`), address checked by `pg_short_link_url_problem()`, code 301 when empty, 0 and 302 accepted.
- A name a page, file, folder or binned short link holds is a line error. A live short link of the same name is skipped; with Replace a link to an address gets the new address and code, a link to a page, product or file never.
- Loop checks run against one prepared set kept current line by line (`pg_short_link_prepare()`, `pg_short_link_prepared_put()`), so a file may chain its own addresses but not close a loop.
- The list screen reports added, changed and skipped counts and the first 20 line errors with their line numbers; when nothing is saved, the import screen does.

Tests: `tests/short_link_redirects_test.php` (reader, writer, round trip).
