---
name: office-own-code-os2-census
description: OS2 census of plan office-own-code: the importers that take only CSV today (file, action, what, how the CSV is read, sample file), those left out, the parts OS2a to OS2c
---

## Which importers take only CSV today? (2026-11-20, most used first)
| # | Screen | File | Action | Imports | CSV read by | Sample file |
|---|---|---|---|---|---|---|
| 1 | Contacts > Import | `import_contacts.php` | POST `submit_import` | contacts into groups | `pg_csv_open()`, `pg_csv_read_separator()`, `fgetcsv()` | none; columns in the help |
| 2 | Users > Import | `import_users.php` | POST `submit_import` | users with rights | `pg_csv_open()`, `pg_csv_read_separator()`, `fgetcsv()` | none; columns in the help |
| 3 | Submitted Forms > Import | `import_submitted_forms.php` | POST `submit_import` | a custom form's records | `pg_csv_open()`, `pg_csv_read_separator()`, `fgetcsv()` | none; the form's export |
| 4 | Key Codes > Import | `import_key_codes.php` | POST `submit_import` | key codes | `pg_csv_open()`, `pg_key_code_import_separator()`, `fgetcsv()` | none; the export's columns |
| 5 | Campaign Profiles > Import | `import_email_campaign_profiles.php` | POST | campaign profiles | `pg_csv_open()`, `pg_csv_read_separator()`, `fgetcsv()` | none; the export |
| 6 | Orders > Import Tracking Numbers | `import_shipments.php` | POST `submit_import` | tracking numbers | `pg_shipping_import_run()` (`includes/fn/shipping_import.php`), own delimiter guess | example lines in File Format |
| 7 | Short Links > Import | `import_short_links.php` | POST | short links | `pg_short_link_csv_rows()` (`includes/fn/short_link_redirects.php`), own encoding and delimiter guess | none; columns in the help |
| 8 | ERP > Accounts > Import Accounts from CSV | `erp_accounts_import.php` | POST `step=upload`, staged, then `step=map` | ERP accounts | `erp_import_to_utf8()`, `erp_import_detect_delimiter()`, `erp_import_read()` (`includes/erp/import.php`) | Download template (`?template=1`) |
| 9 | Translations > Import CSV | `translations_action.php` (form in `translations.php`) | POST `action=import` | reviewed translations | `pg_tr_csv_import()` (`includes/translate/jobs.php`), `;` then `,` | the screen's Export CSV |

## Which are left out?
- Already read workbooks: Products > Import (`import_products_f.php`) and the ERP bank statement import (`includes/erp/bank_import.php`): only the sheet question is added, in OS2c after XL5. Workflow table import (`includes/workspace/wtable_import.php`) already takes .xlsx, .ods and .xls.
- Not a spreadsheet of rows a person makes: the Shopify and WooCommerce migrations (`includes/import/`, those platforms' own CSV export), the system theme CSV (`add_theme_file.php`, a theme format), the workspace notes import (`str_getcsv()` on a front-matter list).

## How is it cut?
- OS2a (#10523): the shared helper and sheet question, importers 1 to 8.
- OS2b: Translations (9), once the open AC2a no longer holds `translations.php`.
- OS2c: the sheet question for the product and bank statement imports, after XL5.
