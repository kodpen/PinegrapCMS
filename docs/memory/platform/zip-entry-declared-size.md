---
name: zip-entry-declared-size
description: A zip entry is read no further than its archive declares: streams via pg_zip_entry_uri(), whole entries via pg_zip_entry_contents(), ZIP imports via pg_zip_import_open(), .ods checked
---

## What was wrong?
- The size ceilings on archives (`pg_office_open()`, `pg_restore_archive_read()`, `pg_explorer_archive_size()`) add up the sizes the central directory declares (`statIndex()`). `getFromName()`/`getFromIndex()` read no more than that, but a stream does: libzip (1.7.3) compares an entry's size only once the stream reached its end, so `zip://` under XMLReader and `ZipArchive::getStream()` read whatever the entry inflates to.
- Found on #634: a 24 KB `xl/sharedStrings.xml` declared as 1000 bytes came out as 10 MB and 600,000 strings.
- PclZip (search index docx/pptx/xlsx text, Import ZIP File, the designer's HTML/ZIP import) inflated a whole entry with `gzinflate()` and no limit, and PHPExcel's .ods reader took `content.xml` at any declared size: an 82 KB docx took 186 MB to index, a 52 KB site zip wrote a 50 MB file.

## What do streams do now? (PR #700)
- `pg_zip_entry_uri($archive, $entry, $limit)` (includes/fn/files.php) returns `php://filter/read=pg_zip_cap.<limit>/resource=zip://…`; its filter (`pg_zip_stream_cap`, includes/zip_stream_cap.php) fails the read at the first chunk past the limit, `pg_zip_entry_overran()` says so afterwards. `pg_zip_entry_size()` is the declared size, -1 without the entry.
- Office preview, product importer and bank statement .xlsx readers stream through it; past the size → "The file could not be read.", nothing taken (a preview sheet: rows so far, `truncated`); the cut part's parser error stays out of the PHP warnings (`libxml_use_internal_errors()`). Preview shared strings also stop at 500,000 strings or 32 MB (`PG_OFFICE_MAX_STRINGS`, `PG_OFFICE_MAX_STRING_BYTES`).
- `pg_restore_archive_copy()` and `pg_restore_extract_step()` read at most one byte past the declared size, then report the file damaged.

## And entries read whole? (PR #730)
- `pg_zip_entry_contents($zip, $entry, $limit, &$error)` (files.php): null with `large` (declares more than `$limit`), `damaged` (not exactly its declared size; `getFromIndex()` is asked for size + 1) or `missing`.
- `pg_zip_import_open($path, &$error)` refuses, before inflating, more than 10,000 entries, 2 GB declared or the disk room (`pg_explorer_disk_has_room()`, now in files.php). `import_zip.php` and `pg_designer_import_zip()` use both; an entry past 64 MB (`PG_ZIP_IMPORT_MAX_ENTRY`) or damaged is left out with a warning. Names are read raw (`FL_ENC_RAW`), as PclZip gave them.
- Search index: `pg_office_open()`, then the part at `PG_OFFICE_MAX_PART`; pptx slides share that room. A refused part gives no text.
- .ods: `pg_products_import_ods_check()` (import_products_f.php; bank import too) reads `content.xml` and `meta.xml` once against `PG_OFFICE_MAX_PART` before PHPExcel: "The file is too large." / "The file could not be read.".
- Without ZipArchive these refuse, as restore and update do; PclZip is no longer read.

## What is the rule?
- Stream an entry only through `pg_zip_entry_uri()` and ask `pg_zip_entry_overran()`; read a whole entry only through `pg_zip_entry_contents()` with a ceiling; open an archive imported entry by entry with `pg_zip_import_open()`; copy in chunks bounded by the declared size.

## Where are the tests?
- `tests/zip_entry_cap_test.php` (streams, copies) and `tests/zip_entry_contents_test.php` (whole entries, import ceilings, .ods): archives whose declared size is changed after writing.
