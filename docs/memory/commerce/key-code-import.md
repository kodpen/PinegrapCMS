---
name: key-code-import
description: Key code CSV import (import_key_codes.php): a code already there is updated, not re-added (a used single-use code stays used); BOM, separator, dates, yes/no cells
---

## What does the import do with a code that is already there?
- It is updated in the columns the file has, and only when one of them differs; then `user` and `timestamp` become the importer's. Nothing differs: the row is left alone and counted "unchanged".
- Before #735 (PR #1060) every code in the file was deleted and inserted again, `enabled = 1` when the file had no enabled column: re-importing a file reopened used single-use codes (checkout sets `enabled = 0`) and stamped every row.
- `key_codes.code` is not unique: the update writes every row with that code.

## And a new code?
- It needs an offer code that an offer has (the rule of `add_key_code.php`); the defaults are enabled 1, single use 0, report `key_code`, no expiration date, no notes.

## How are the cells read?
- Pure helpers in `includes/fn/ecommerce.php`, tested in `tests/key_code_import_test.php`: `pg_key_code_import_column()` (export headings, case, spaces, `_`/`-`, a BOM), `pg_key_code_import_separator()` (comma, semicolon or tab, whichever the header line holds most: Excel in Turkish writes `;`), `pg_key_code_import_flag()`, `pg_key_code_import_date()`, `pg_key_code_import_row()`.
- The script steps over a UTF-8 BOM before `fgetcsv()` (left in front of a quoted heading it kept the quotes in the name: "A key code column could not be found").
- Yes/no (enabled, single_use): 1/0, yes/no, true/false, Excel's TRUE/FALSE and DOĞRU/YANLIŞ, evet/hayır; empty leaves the column out.
- Expiration date: the export's Y-m-d; day.month.year with dots; slashes or dashes in `DATE_FORMAT` order, else the other order when that one is not a date; empty or 0000-00-00 is "does not expire". A two-digit year or a time is refused (before, MySQL stored it as 0000-00-00: never expires).
- Report: key_code or offer_code; empty leaves it out. Code and offer code: at most 50 characters (the column), not cut.
- Notes and an empty expiration date are written as the file says, so export → import changes nothing.

## What is reported?
- A row with a cell that cannot be stored, an unknown offer code or a new code without one is skipped whole; the list shows "Key codes imported: n added, n changed, n unchanged, n line(s) skipped." (also in the Site Log) and up to 20 skipped lines with their reason.

## How was it checked?
- Sandbox: import, set one code used, import again (stays used, timestamp kept); an Excel-style file (BOM, `;`, 31.12.2026, DOĞRU/YANLIŞ, a bad date, a bad flag, an unknown offer, no offer); the export imported back: all unchanged.
