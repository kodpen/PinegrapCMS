---
name: privacy-centre-screen
description: Contacts › Privacy centre (privacy_centre.php, KV2): the inventory and transfers on screen, the Excel with the VERBİS columns, the PDF with the destruction records, the Site Settings link card
module: privacy_centre
---

## What is it?
- `privacy_centre.php` (manager and up, Contacts menu after Personal data erasure) has two views as link segments, Inventory and Requests (`?view=requests`, `core/privacy-requests.md`). Inventory: the catalogue of `core/privacy-inventory.md` with live counts, the Retention rules card (`#privacy-rules`, `core/privacy-rules-card.md`), and the transfers. Names no person, only counts. Toolbar: Download Excel, Download PDF (only when dompdf is installed), Personal data export, Personal data erasure.
- Site Settings › Visitors and privacy has only a "Privacy centre" card (`pgset-privacy-centre`) with "Open the Privacy centre"; nothing is saved there.
- One info line says once that grounds and retentions are suggested defaults, not legal advice, and that erased data stays in older backups. The PDF says both too.

## How are the rows built?
- `includes/fn/privacy_centre.php`, pure, labels through a translate callable (`'lang'` on the screen, so catalogue texts need Turkish keys: a new purpose or label is added with `tr_add.php`).
- `pg_privacy_centre_rows()`: one row per catalogue line, keys of `pg_privacy_centre_columns()`: table, category, purpose, legal ground, data subject group, recipients, retention, transfer abroad, measures, records, people.
- Recipients: personal transfers whose `data` meets the line's categories; transfer abroad names those of them abroad as `Name (CC)`, else "No". A count not known stays null (shown "–", an empty cell in Excel).
- Measures: one suggested sentence for every line (`pg_privacy_centre_measures()`).
- The site's country is `pg_erp_store_country()` (the ERP's country, else the default country).

## What do the downloads hold?
- Excel: one sheet through `pg_xlsx_write_plain()`, the eleven columns. GET `?download=xlsx`; no audit line (no person in it).
- PDF (`?download=pdf`, A4 landscape, `pg_privacy_export_pdf()`): the inventory without the measures column (said once below), the transfers, abroad first, and the destruction records.
- Destruction records: `pg_privacy_centre_destruction_records()` reads `audit_log` lines of the last 3 years, newest first, at most 500: erasures (`action = 'erase'`, `entity_type = 'person'`) and retention rule runs (`entity_type = 'privacy_rule'`, its `label` shown). Counts are the sums under `deleted` and `anonymised` in `after_json` (nested a level deeper too), so a rule's run writes its counts under those two keys.

## What was verified?
- `tests/privacy_centre_test.php`: rows cover every catalogue line, recipients and abroad, unknown counts, record sums, file names, the PDF's escaping and its two sentences.
- Not run on a database or a browser when written (#10789): the count queries of KV1 and the PDF rendering are first seen on dev.

Related: [[privacy-inventory]], [[personal-data-erasure]], [[personal-data-export]]
