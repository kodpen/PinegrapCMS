---
name: zip-entry-declared-size
description: A zip entry read as a stream (office preview, xlsx importers, restore and extract copies) stops at the size its archive declares; pg_zip_entry_uri(), shared string caps
---

## What was wrong?
- The size ceilings on archives (`pg_office_open()`, `pg_restore_archive_read()`, `pg_explorer_archive_size()`) add up the sizes the central directory declares (`statIndex()`). `getFromName()`/`getFromIndex()` read no more than that, but a stream does: libzip (1.7.3) compares an entry's size only once the stream reached its end, so `zip://` under XMLReader and `ZipArchive::getStream()` read whatever the entry inflates to.
- Found on #634 (D5 of files-office-documents): a 24 KB `xl/sharedStrings.xml` declared as 1000 bytes came out as 10 MB and 600,000 strings, so the preview of a workbook of about 100 KB could exhaust a 128 MB memory limit.

## What does the code do now? (PR #700)
- `pg_zip_entry_uri($archive, $entry, $limit)` (includes/fn/files.php) returns `php://filter/read=pg_zip_cap.<limit>/resource=zip://…`. Its read filter (`pg_zip_stream_cap`, includes/zip_stream_cap.php, registered once as `pg_zip_cap.*`; the limit travels in the filter's name) fails the read at the first chunk past the limit. `pg_zip_entry_overran()` says afterwards whether it did; the next address clears it. `pg_zip_entry_size()` is the declared size, -1 without the archive or entry.
- Office preview: shared strings past their declared size → "The file could not be read."; a sheet past it → the rows read until then, marked `truncated`. The string table also stops at 500,000 strings or 32 MB (`PG_OFFICE_MAX_STRINGS`, `PG_OFFICE_MAX_STRING_BYTES`) → "too large to show here": a 64 MB part of short strings would take several hundred MB as a PHP array.
- Product importer (`pg_products_import_xlsx_strings()`, `pg_products_import_xlsx_to_csv()`) and bank statement import (`erp_bank_read_spreadsheet()`): either part past its size → "The file could not be read.", nothing is taken. The parser error of a cut part is kept out of the PHP warnings (`libxml_use_internal_errors()`).
- `pg_restore_archive_copy()` (install upload, file manager extracts, update package) and `pg_restore_extract_step()` read at most one byte past the declared size and then report the file damaged.

## What is the rule?
- Stream a zip entry only through `pg_zip_entry_uri()` with its declared size, and ask `pg_zip_entry_overran()` when the read is over; copy in chunks bounded by the declared size. `getFromName()`/`getFromIndex()` are bounded already; check the declared size before using them.

## What is not covered?
- PclZip reads (`update_search_index.php` docx/pptx/xlsx text, `import_zip.php`, `includes/designer_import.php`) inflate a whole entry with `gzinflate()` and no limit; PHPExcel's .ods reader reads `content.xml` through `zip://` whole. Unchanged.

## Where are the tests?
- `tests/zip_entry_cap_test.php`: small archives whose declared size is lowered after writing. The reader tests skip themselves on a libzip that already stops at the declared size.
