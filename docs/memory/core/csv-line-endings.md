---
name: csv-line-endings
description: The CSV importers open the upload with pg_csv_open(), which turns a classic Mac (CR-only) file into LF lines instead of the auto_detect_line_endings setting deprecated in PHP 8.1 (PR #1757)
---

## How do the importers read a CR-only CSV file?
- `fgetcsv()` splits lines only on LF. The six importers (`import_contacts.php`, `import_email_campaign_profiles.php`, `import_key_codes.php`, `import_products.php`, `import_submitted_forms.php`, `import_users.php`) open the upload with `pg_csv_open()` (`includes/fn/core.php`) instead of `fopen()`.
- An LF or CRLF file comes back as the plain handle. When the first line break in the first 8 KB is a lone CR, the file is copied into a `php://temp` stream with each lone CR turned into LF (a CR at a chunk edge waits for the next byte, so a CRLF is never split). The handle is seekable, so the BOM skip (`fseek()`) still works.
- Returns `false` when the file cannot be opened, like `fopen()`.

## Why not keep auto_detect_line_endings?
- Until #1757 each importer ran `@ini_set('auto_detect_line_endings', true)`. The setting is deprecated since PHP 8.1 and removed in 9.0, though it still works through 8.5. Dropping it alone would have made a CR-only file read as one row on 8.1+; `pg_csv_open()` reads the same rows on every version.

## How is it held?
- `tests/csv_line_endings_test.php`: LF, CRLF and CR files give the same rows, a CR file past the 64 KB copy chunk, a CRLF whose CR ends the 8 KB sample, a missing file. The PHPCompatibility baseline holds no `auto_detect_line_endings` entry, so a new `ini_set()` of it fails CI.
