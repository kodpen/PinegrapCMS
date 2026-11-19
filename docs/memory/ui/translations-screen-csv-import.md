---
name: translations-screen-csv-import
description: Translations screen's Import CSV: which rows of a file are written, which are left as they are (unchanged), and what the import notice counts
---

## Which rows does Import CSV write?
`pg_tr_csv_import()` (`includes/translate/jobs.php`) writes a row whose translation passes `pg_tr_result_check()` and differs from the text stored for the language, as reviewed (or machine with "Import as machine translations (still to be reviewed)"), engine `import`.
A row whose cleaned translation is the stored text, byte for byte (`pg_tr_csv_import_unchanged()`), is counted as unchanged and not written: its status, engine and suspicious flag stay.

## Why are untouched rows left alone?
Export CSV lists every text with its translation, so a file sent back after a few fixes carries every other row untouched. Before, the import wrote them all: by default every unread machine translation became reviewed (the Machine filter emptied), with the box ticked every reviewed one went back to machine, and all were relabelled as imported. "Approve all" is the way to mark many reviewed. (PR #10372)

## What does the notice count?
"CSV import: n translation(s) written, n unchanged, n empty row(s) skipped, n row(s) whose text has since changed, n row(s) rejected." (`translations_action.php`); the refused rows are listed under it (`content-translation-result-check.md`).
