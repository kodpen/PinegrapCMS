---
name: erp-account-csv-import-export
description: ERP account CSV import wizard (steps, matching, batches, defaults) and export profiles incl. Parasut XLSX templates and erp_export_log.
---

## How does the account CSV import work?
`erp_accounts_import.php`, logic in `includes/erp/import.php`; three steps on one address:
- Upload: the file waits under `data/temp/` keyed by a session token; delimiter and charset are detected (Windows-1254 → UTF-8).
- Map and preview: TR/EN header synonyms; the first 20 rows show will-create / already-exists / duplicate / error.
- Run: batches of 100, one transaction per batch.
- Match order: tax number → e-mail → full name (name only when both others are empty).
- Update writes only the columns the file provides and never touches the balance.
- Default country is `erp_default_country_code()` (`countries.default_selected`), not `'TR'`.
- The due-day field travels as "Vade (Gün)" (after Notlar) in both import and export (see erp-aging-and-due-date).
- Optional-column rule: see erp-account-save-partial-writes.

## How does the export work?
`erp_export.php`, profiles in `includes/erp/export.php`.
- Profile = columns + row mapper + formatting (`erp_export_amount/rate/date/currency()`, the single formatting point).
- Default is generic CSV (BOM, `;`, ISO dates).
- Paraşüt profiles open the repo templates `includes/phpexcel/templates/parasut_*.xlsx` as a zip and write only from row 4 (no library).
- `erp_export_log` (4.52: entity, doc_id, profile, run_token) records what was exported. `erp_parasut_log` was the API call trace (renamed `erp_edoc_log` in 4.62); never mix the two.
- Cancelled documents never export; returns count as returns and are skipped; drafts are optional.
- Currency mapping is only TRY→TRL.

Related: docs/memory/erp/erp-account-save-partial-writes.md
