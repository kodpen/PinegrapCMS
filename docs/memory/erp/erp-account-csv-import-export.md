---
name: erp-account-csv-import-export
description: ERP account CSV import wizard (steps, matching, batches, defaults) and export profiles incl. Parasut XLSX templates and erp_export_log.
---

## How does the account CSV import work?
`erp_accounts_import.php`, logic in `includes/erp/import.php`; three steps on one address:
- Upload: the file waits under `data/temp/` keyed by a session token; delimiter and charset are detected (Windows-1254 → UTF-8).
- Map and preview: TR/EN header synonyms; the first 20 rows show will-create / already-exists / duplicate / error.
- Run: batches of 100, one transaction per batch.
- A Start import refused for want of a Name column draws step 2 again from the posted mapping and mode (error key `map`), never `go()` to the address, whose plain visit discards the staged file. Step 2 sends once and says "Importing…" (button-busy-state).
- Match order: tax number → e-mail → full name (name only when both others are empty).
- Update writes only the columns the file provides and never touches the balance.
- Default country is `erp_default_country_code()` (`countries.default_selected`), not `'TR'`.
- The due-day field travels as "Vade (Gün)" (after Notlar) in both import and export (see erp-aging-and-due-date).
- Optional-column rule: see erp-account-save-partial-writes.
- An Accounts (CSV) export loads back in either panel language: `erp_import_field_synonyms()` holds each export heading's English source and Turkish wording beside the common names, and `erp_import_parse_kind()` reads "Customer and supplier" as `both`. Before, that row was refused as an unrecognised Type, and a heading in the other language (a file from another site, or one written before the language was switched) stayed unmapped, so an update kept the stored term, reminder and state. `tests/erp_account_csv_roundtrip_test.php` checks every heading and word of the export; a new export column needs its heading there too.

## How does the export work?
`erp_export.php`, profiles in `includes/erp/export.php`.
- Profile = columns + row mapper + formatting (`erp_export_amount/rate/date/currency()`, the single formatting point).
- Default is generic CSV (BOM, `;`, ISO dates).
- Paraşüt profiles open the repo templates `includes/phpexcel/templates/parasut_*.xlsx` as a zip and write only from row 4 (no library).
- `erp_export_log` (4.52: entity, doc_id, profile, run_token) records what was exported. `erp_parasut_log` was the API call trace (renamed `erp_edoc_log` in 4.62); never mix the two.
- Cancelled documents never export; returns count as returns and are skipped; drafts are optional.
- Currency mapping is only TRY→TRL.

Related: docs/memory/erp/erp-account-save-partial-writes.md

## Which words do the generic invoice and receipt CSVs write?
- The panel's: the invoices file's status through `erp_invoice_status_label()`, the receipts file's method through `erp_export_payment_method_label()` (Cash, Bank transfer, Card, Cheque, Other); an unknown code is written as it is. The Paraşüt profiles keep their own value sets (#4320, bug #4290). Test: `tests/erp_export_csv_words_test.php`.
