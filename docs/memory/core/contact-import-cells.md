---
name: contact-import-cells
description: Contact importer (import_contacts.php): cells read by pg_import_contact_value() (yes/no, dates, lengths, export guard undone), repeated headings left out, Turkish headings, the screen's hint
module: ecommerce
---

## How is a cell read?
- `pg_import_contact_value($column, $cell, $max_length, $heading)` (`includes/fn/ecommerce.php`) answers `array('value', 'problem')`; the screen adds each problem to its line list (`pg_csv_problem_list()`). `pg_import_contact_cell()` is the same without the problem (the user importer uses it).
- Every cell is trimmed and loses the apostrophe the export's `csv_cell()` put before `= + - @` (`pg_csv_formula_unguard()`), so an export imports back unchanged (#5335).
- `opt_in`, `affiliate_approved`: `pg_import_yes_no()` reads 1/0, yes/no, y/n, true/false, on/off, evet/hayır, e/h. Empty or unknown is 0, an unknown one with a problem: consent is never guessed. Before, "Evet" became 0 without a word (#5331).
- `expiration_date`: `pg_date_input_iso()` (site order with `/ - , .`, or ISO); empty, `0000-00-00` or unreadable is `0000-00-00`, an unreadable one with a problem.
- Text longer than its column is cut with a problem. The lengths come from `SHOW COLUMNS FROM contacts` (`pg_import_column_lengths()`), not a list in the code, so a migration that widens a column needs nothing here.
- `affiliate_commission_rate`: `pg_parse_commission_rate()`, as before.
- `affiliate_code`: `pg_import_affiliate_code()` leaves a code another contact already holds empty, with a problem (rows written earlier in the same file count). Checkout and the commission screens find the affiliate by the code, and the contact form refuses a taken code; before, re-importing an export doubled every code. The user importer (`import_users.php`) clears it the same way, without a problem line.

## What happens to two headings for one field?
- `pg_import_unique_columns()` keeps the first heading per contacts column and leaves the later one out; the result names them ("These columns fill the same field…"). Outlook writes Title + Job Title and Location + Office Location: the insert named the column twice and MySQL stopped the whole import with "Query failed." (#5330).

## Which headings are known?
- `convert_column_name()` folds the heading with `pg_list_search_fold()` (İ, I, ı are i; lower case), drops spaces, `_` and `-`, then looks in a Turkish map (Ad, Soyad, E-posta, Şirket, Unvan, Cep Telefonu, İş Telefonu, Adres, Şehir/İlçe = city, İl = state, Posta Kodu, Ülke, Açıklama/Not, İleti İzni, Üye Kimliği, Bitiş Tarihi, …) and then the English names (#5332).
- The file box takes `accept=".csv,.txt,text/csv"` and a hint says the first row names the columns, an export imports as it is, and which headings are known.

Related: docs/memory/core/export-import-columns.md
