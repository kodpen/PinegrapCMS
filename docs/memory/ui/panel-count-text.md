---
name: panel-count-text
description: Panel counts read right at one: ERP due badges, Create Order cart count, Users lock, API application counts, delete/connection notices and File Manager answers use suffix or two-key forms
---

## Which screens were fixed, and how?
- ERP Invoices due badges (`'{var:1} day{suffix:1} overdue'`, `'due in {var:1} day{suffix:1}'`), ERP dashboard top overdue (`'{var:1} day{suffix:1}'`), Create Order payment term and recent sales line count, the Users list lock (`'Clears in {var:1} minute{suffix:1}.'`) and the Settings › API applications counts (`'{var:1} connection{suffix:1}'`, device, request, error) carry a `{suffix:N}`; before, English read "1 days overdue", "Clears in 1 minutes." and the API counts were glued outside `lang()`.
- Sentences whose verb agrees with the count pick one of two literal keys: users deleted, connections ended, files updated/compressed/extracted, pages updated, products updated (with and without the notes), items moved.
- The Create Order cart count is `local_sale_count_text($totals)` in `includes/local_sale.php` (cart header, summary card, Ajax `count_text`); lines and units go through `pg_format_number($n, 0)`.

## Why does the File Manager move log name "the folder"?
- The old line glued `$moved . ' item(s) ' . 'were moved to {var:1}'`, and the Turkish value `{var:1}'e taşındı` gave "Blog'e". `'{var:1} item(s) was/were moved to the folder {var:2}'` translates as `{var:1} öğe {var:2} klasörüne taşındı`, so no case suffix is glued to the folder name.

## Where is it tested?
- `tests/panel_count_text_test.php` pins the keys per file and their Turkish values; `tests/session_screen_reasons_test.php` pins the lock text on the Users list.

Related: docs/memory/core/lang-function-syntax.md, docs/memory/core/translation-rules.md
