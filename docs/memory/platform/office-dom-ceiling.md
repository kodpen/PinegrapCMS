---
name: office-dom-ceiling
description: How much of a docx or xlsx becomes a libxml tree (office.php, office_edit.php): 8 MB a DOM part, a long document read from its beginning, rows and strings node by node
---

## Why a ceiling of its own?
- libxml's memory for a tree is not counted by `memory_limit`; a DOM costs 15 to 60 times its part. Measured (PHP 8.3, libxml 2.9.14), before: a 60 MB `word/document.xml` (120 KB docx) took 863 MB; a 60 MB `xl/styles.xml` 1.2 GB; one 60 MB row (150 KB xlsx) 3.7 GB and 29 s; one 60 MB shared string 1.6 GB; a `<f>` holding 30 MB of elements, in the editing preview, 2 GB. `PG_OFFICE_MAX_PART` (64 MB) only bounds the unpacked bytes.
- Left out of #730, which gave the zip importers' DOM parts 8 MB for the same reason.

## How is a part read now?
- `pg_office_xml($zip, $name, &$status, $head)`: whole, through `pg_zip_entry_contents()` at `PG_OFFICE_MAX_DOM` (8 MB). `$status` '' or 'cut'; with null 'missing' (absent or empty), 'large', 'damaged' or 'xml'. Missing and not XML read as none, as before; large and damaged refuse the file (`pg_office_refuse()`).
- `word/document.xml` past 8 MB is read with `$head`: its first 8 MB up to the last `>`, loaded with `DOMDocument::$recover` so the elements open there close. Shown `truncated`, never offered for editing (`large`, the edit ceiling is the same 8 MB). The preview cuts its HTML at 600,000 characters well before, so the HTML is the same: an 8 to 60 MB document and a 13 MB python-docx one came out identical to the old reader's, at 158 MB.
- Any other DOM part past 8 MB (workbook, its relationships, styles, numbering, the document's relationships): "too large to show here"; damaged: "could not be read". Not drawn without it (dates would show as numbers).
- Rows, cells, shared strings and the editing scan's formula text are read node by node (`pg_office_xlsx_cell_parts()`, `pg_office_reader_text()`), never with `readString()`, `readOuterXml()` or `expand()`, which build the element as a tree first. Values as SimpleXML gave them (first `v`, first `is/t`, runs' first `t`, the row's own cells, names as written). The 60 MB row now takes 50 MB and 5 s.

## And the editing path?
- Styles or the workbook part unreadable: the save is refused (`read`). A sheet's relationships or a table part: the sheet is not offered or saved (`large`; damaged `unusual`, `sheet`). Word settings: `large` or `unusual`, as protection is unknown. A changed row or a workbook part over 8 MB is not built as a tree (`large`). Sheets and the document are read whole through `pg_zip_entry_contents()`. Saves of openpyxl and LibreOffice files came out with the same parts as before.

## What is the rule?
- Build a DOM only from a part read through `pg_office_xml()`, or a string checked against `PG_OFFICE_MAX_DOM`; walk a streamed part node by node, never expanding an element of it.
- Tests: `tests/office_dom_ceiling_test.php`. Not covered: the product importer's and the bank statement's xlsx readers (`import_products_f.php`, `includes/erp/bank_import.php`) still take each row with `readOuterXml()`.
