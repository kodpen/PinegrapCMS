---
name: erp-reconciliation-letter
description: ERP account reconciliation letter (cari mutabakat): as-of balance from the ledger, option sanitizing, reference, row cap, e-mail attachments, screen pattern.
---

## How is the letter built? (phase 5)
Files: `includes/erp/reconciliation.php`, `includes/erp/templates/reconciliation_default.html`, `erp_reconciliation.php?id=<account>`, `get_erp_reconciliation_pdf.php`; entry from the `edit_erp_account.php` toolbar.
- As-of, from the ledger: `erp_reconciliation_balance($id, $as_of)` sums `erp_account_transactions` with `doc_date <= as_of`; the `erp_accounts.balance` cache is NOT used. Movements via `erp_account_statement($id, $from, $as_of)`.
- Sign is the ledger's: positive = debit (account owes you), negative = credit; the letter sentence is built from it.
- It is a movement list, not an invoice list: `paid_total` is today's value and cannot be rebuilt as of a date; the statement is cut exactly by date.

## How are options and the reference handled?
- `erp_reconciliation_options()` falls back to defaults on bad input (today / start of year / 7 days) and never errors; `as_of` cannot be after today, `from` cannot be after `as_of`.
- Reference `MUT-{Ymd}-{account id}` is derived, spends no series.
- 400+ rows (`ERP_RECONCILIATION_MAX_ROWS`) fold into the opening line; the closing balance is unchanged.

## How is the letter e-mailed?
- `email()` (`includes/fn/mail.php`) takes attachments: `'attachments' => [['name' => …, 'content' => <bytes>, 'type' => …], …]` via `addStringAttachment`; without the option behaviour is as before. The letter PDF goes this way.
- Sending calls `log_activity` and keeps the letter that went (4.65, `erp_archive_reconciliation()`): a row in `erp_reconciliation_log` (reference, dates, reply days, balance, recipient) and the PDF (erp-document-archive.md). Nothing is stamped on the account (reconciliation ends with the other side's reply).

## What is the screen pattern?
- Options are GET (preview iframe `&html=1` with the same options); sending is POST + token + `data-confirm-content`; PDF/İndir are plain links.
- Date fields are `<input type="date">` (Y-m-d) like `erp_aging.php`; the PDF endpoint also accepts the site format (`validate_date` + `prepare_form_data_for_input`).
