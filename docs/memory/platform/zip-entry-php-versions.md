---
name: zip-entry-php-versions
description: Why pg_zip_entry_contents() watches the read's warning and compares the CRC itself: fread() and getFromIndex() answer a damaged entry differently by PHP version
---

## What differs between PHP versions?
- `fread()` on a zip stream whose read fails (cannot inflate, CRC mismatch) returns '' up to PHP 7.3 and false from 7.4 on. libzip warns the same way everywhere ("Zip stream error: …"), with libzip 1.5.1 and 1.7.3 alike: the PHP version decides, not libzip.
- `getFromIndex()` compares the CRC itself only in recent releases (PHP 8.4.26 and 8.5.12, ext/zip 1.22.8): a damaged entry gives false and the warning "Cannot read entry: CRC error". PHP 7.1 to 8.3 (8.3.36 too) and older 8.4 releases hand the data over.

## What went wrong?
- `pg_zip_entry_contents()` read one byte of an entry that declares 0 bytes and took '' for a clean end. On PHP 7.1 to 7.3 an entry like that over a stream that cannot be inflated came back as an empty file: Import ZIP File and the designer's import created it instead of leaving it out with "could not be read from the archive".
- A non-empty entry whose data does not match its CRC was taken as it was up to PHP 8.3, and refused with a PHP warning in the site's error log on 8.4.26 and later.

## What does it do now? (#1356)
- The one-byte read runs under a temporary error handler: any warning makes the entry `damaged`, whatever `fread()` returned.
- `getFromIndex()` is called with `@`, and every entry is compared with the CRC `statIndex()` declares (`hash('crc32b')`; an empty entry's CRC is 0). A damaged entry is `damaged` on every version, with no PHP warning.

## How is it tested?
- `tests/zip_entry_contents_test.php`: `garbage.txt` in `test_zip_entry_contents_empty_entries_of_any_writer` (failed on PHP 7.1) and `test_zip_entry_contents_compares_the_checksum` (failed on 7.1 and 8.3, an error on 8.5 from the warning).
