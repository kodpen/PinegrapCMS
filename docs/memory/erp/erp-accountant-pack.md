---
name: erp-accountant-pack
description: ERP accountant pack (4.78): the shared xlsx writer, where the ZIP is kept, the link token, the monthly job, and what a new record type must add to the pack.
---

## What builds the pack? (`includes/erp/accountant.php`, 2026.4.4 step 4.78)
- `erp_accountant_collect($from, $to)` builds the sheets and the document list; `erp_accountant_build()` writes the workbook and the ZIP and records an `erp_accountant_packages` row.
- Base currency unless a column says otherwise; cancelled documents are listed, marked and left out of every total. Built from the screen, the till sheets go in only for a user with the cash right; the monthly pack always has them.
- The build writes `with_cash` into the row's `summary` JSON; `erp_accountant_package_has_cash()` reads it (a row without it counts as carrying the tills). Without the cash right: no download, link or e-mail of such a pack; before, anyone with ERP access got them (#5733).
- Building and a panel download are logged (audit trail). People see the period via `erp_profit_period_title()`; `erp_accountant_period_label()` is for file names. Sizes: `erp_accountant_size_text()`, KB below 1 MB (#5734, #5738).
- Packs table full width; one send window (`erp_accountant_send_modal`) holds the address and both send buttons (#5737). The dates pick the range radio, the month picker the month one (#5735). `Lock` with no date is refused; only `unlock` lifts it (#5736).
- Screen `erp_accountant.php`, gate `validate_erp_access($user, 'accountant')`: the read-only accountant builds and downloads; a link or an e-mail needs a user who may change things; the settings and the period lock (erp-period-lock.md) need the ERP settings right.
- A refused settings save (Monthly on with no e-mail, an address `FILTER_VALIDATE_EMAIL` rejects) stores the post with `add_fields_to_session()`; the card redraws from the liveform (`$settings_form`). (#3553).

## Which xlsx writer does a report use?
- `erp_accountant_write_workbook($sheets, $path)`: several sheets, frozen bold heading with filters. A cell is a scalar typed by its column, or `['v' => value, 't' => type]` (`text`, `money`, `money_bold`, `date`, `int`, `number`, `bold`); money in currency units, dates as dates.
- Used by the VAT, profit and expense exports; a new Excel output uses it. The export profiles (`includes/erp/export.php`) keep their own (Paraşüt templates).

## Where is the ZIP kept?
- `data/temp/erp_packages/` (`erp_accountant_directory()`, plus a `Require all denied` `.htaccess`). The ZIP is a delivery copy, not a record: the row keeps what went in, and a pack whose file is gone is built again.
- Inside: the workbook and, with PDFs on, `belgeler/<folder>/<number>.<ext>`: the provider's official copy first; a sales document without one takes its kept PDF or is rendered and kept now; a purchase only the provider's copy, else nothing (#4104); expense receipts.

## How does the accountant's link work?
- `erp_accountant_new_link()`: a random 48-hex token, only its sha256 kept (`token_hash`), valid `ERP_ACCOUNTANT_LINK_DAYS` (7); each new link replaces the last. `erp_accountant_download.php` opens it without a login; a wrong, spent or guessed token gets the same plain 404 page.
- `erp_accountant_send()` restores the previous token when the e-mail fails.
- Monthly job `erp_accountant_job.php` (daily in the job registry, `erp_accountant_monthly_run()`): last month's pack, built and sent once when the card asks for it; one built but not sent is sent again without rebuilding.
- A run that could not build or send (reason not `off`/`done`/`sent`) writes one Site Log line, user `UNKNOWN` in the background.

## What does a new record type need in the pack?
- Its own sheet in `erp_accountant_collect()`.
- Its VAT summary line: a helper in `includes/erp/vat_report.php`, its key in `erp_vat_sorted()` and `erp_vat_kind_labels()`.
- Its Summary block: `$kind_labels` and `$summary['<type>']` in `erp_accountant_collect()`.
- Its files, if any: a folder under `belgeler/` in `erp_accountant_build()`.
