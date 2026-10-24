---
name: csv-escape-argument
description: Every fgetcsv(), fputcsv() and str_getcsv() call passes enclosure '"' and escape '\\': PHP 8.4 deprecates leaving the escape out; why '\\' and not '', and the test that holds it
---

## Why does every CSV call pass its escape character?
- Leaving `$escape` out of `fgetcsv()`, `fputcsv()` or `str_getcsv()` is deprecated from PHP 8.4, because its default is to change. On 8.4 and later the unit tests stopped with an ERROR at `import_products_f.php:721`; sites saw nothing, since the product hides `E_DEPRECATED` (platform/error-reporting.md).
- Fixed in #1450: 25 calls in 11 files (the six `import_*.php`, `import_products_f.php`, `add_theme_file.php`, `view_visitor_report.php`, `includes/erp/audit.php`, `includes/translate/jobs.php`) pass `'"', '\\'`; a two-argument `fputcsv()` also gets its separator `','`. The ERP bank statement, export and import, the short link import and the notes import passed it already.

## Why '\\' and not ''?
- `'\\'` is what PHP has always used, so nothing any file reads or writes changes on any version, and files the product writes and reads back (the XLSX-to-CSV step of the product import, the translation export and import) keep matching.
- `''` follows RFC 4180, as spreadsheets do: a field holding `a\"b` is written `"a\""b"` instead of `"a\"b"`. Each setting misreads the other's form (`"a\"b"` read with `''` gives `a\b"`), so switching changes how such a field reads and writes: a change of its own. The product feeds write CSV by hand for this reason (commerce/product-feeds.md).

## How is it held?
- `tests/csv_escape_argument_test.php` reads every PHP file under `pinegrap/` with the tokenizer, the vendored libraries excluded in `tools/phpcs.xml` left out, and lists each call with too few arguments. PHPCompatibility's `RemovedProprietaryCSVEscaping` sniff has no entry left in `tools/phpcs-baseline.json`, so a new call without the argument fails CI there too.
