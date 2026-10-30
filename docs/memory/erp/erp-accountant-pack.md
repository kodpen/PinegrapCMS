---
name: erp-accountant-pack
description: ERP accountant pack (4.78): the shared xlsx writer, where the ZIP is kept, the link token, the monthly job, and what a new record type must add to the pack.
---

## What builds the pack? (`includes/erp/accountant.php`, 2026.4.4 step 4.78)
- `erp_accountant_collect($from, $to)` builds the sheets (summary, sales, purchases, returns, VAT summary, expenses, receipts and payments, tills, account balances, stock value) and the document list; `erp_accountant_build()` writes the workbook and the ZIP and records an `erp_accountant_packages` row.
- Base currency unless a column says otherwise; cancelled documents are listed, marked and left out of every total. Built from the screen, the till sheets go in only for a user with the cash right; the monthly pack always has them.
- Screen `erp_accountant.php`, gate `validate_erp_access($user, 'accountant')`: the read-only accountant builds and downloads; a link or an e-mail needs a user who may change things; the settings and the period lock (erp-period-lock.md) need the ERP settings right.
- A refused settings save (Monthly on with no e-mail, an address `FILTER_VALIDATE_EMAIL` rejects) stores the post with `add_fields_to_session()`; the Accountant card then draws the address and both switches from the liveform (`$settings_form`), from the stored settings otherwise. Before, the address went back to the stored one and Monthly silently to off (#3553, `tests/erp_refused_forms_keep_test.php`).

## Which xlsx writer does a report use?
- `erp_accountant_write_workbook($sheets, $path)`: several sheets, a bold frozen heading row with filters. A cell is a scalar typed by its column, or `['v' => value, 't' => type]` to override that cell (`text`, `money`, `money_bold`, `date`, `int`, `number`, `bold`); money goes out in currency units, dates as dates, so a filter or SUM works in the accountant's program.
- Used by the VAT report (`erp_vat_workbook()`), the profit report (`erp_profit_workbook()`) and the expense list export (`erp_expenses.php`). A new Excel output uses it; do not write a second writer. The export profiles (`includes/erp/export.php`) keep their own because they fill Paraşüt's import templates.

## Where is the ZIP kept?
- `data/temp/erp_packages/` (`erp_accountant_directory()`, plus a `Require all denied` `.htaccess`). The ZIP is a delivery copy, not a record: the row keeps what went in, and a pack whose file is gone is built again (the screen says so, the monthly job does it itself).
- Do not open a new permanent folder under `data/`; a file the software can make again goes under `data/temp/` (docs/memory/platform/clean-up-tool-file-list.md).
- Inside: the workbook and, with PDFs on, `belgeler/<folder>/<number>.<ext>`: the provider's official copy of an e-document first, else the kept PDF (a sales document with neither is rendered and kept now), and the expense receipts.

## How does the accountant's link work?
- `erp_accountant_new_link()`: a random 48-hex token, only its sha256 kept (`token_hash`), valid `ERP_ACCOUNTANT_LINK_DAYS` (7); each new link replaces the last. `erp_accountant_download.php` opens it without a login; a wrong, spent or guessed token gets the same plain 404 page.
- `erp_accountant_send()` writes the previous token back when the e-mail fails, so a failed mail does not cost the accountant the link they have.
- Monthly job `erp_accountant_job.php` (daily in the job registry, `erp_accountant_monthly_run()`): last month's pack, built and sent once when the card asks for it; one built but not sent is sent again without rebuilding.

## What does a new record type need in the pack?
- Its own sheet in `erp_accountant_collect()`.
- Its VAT summary line: a helper in `includes/erp/vat_report.php` (like `erp_vat_add_expense()`), its key in `erp_vat_sorted()`'s order array and in `erp_vat_kind_labels()`.
- Its Summary block: `$kind_labels` and `$summary['<type>']` in `erp_accountant_collect()`.
- Its files, if any: a folder under `belgeler/` in `erp_accountant_build()`.
