---
name: erp-period-lock
description: ERP period lock (config.erp_lock_date, 4.79): what it refuses, the two checks every ledger or till write meets, which date a cancel, return or payment is judged by, who moves the lock.
---

## What does the period lock do?
- `config.erp_lock_date` (2026.4.4, step 4.79; `includes/erp/lock.php`), `0000-00-00` = no lock. Once the accountant has filed a period, nothing dated on or before the lock date may be issued, cancelled, returned, received or paid: what was filed must not change under them.
- Moved on the accountant screen (`erp_accountant.php`; the move needs the ERP settings right) through `erp_lock_set()`: the date must lie before today, an empty date lifts the lock, every move is logged with the previous date.

## How does a new write path use it?
- Every new path that writes to the account ledger or a till asks `erp_lock_refusal($date)` first and gives the operator the sentence it returns (it names the lock date and the refused date). The API endpoints ask up front too (`erp_api_cash_lock_check()`, `api_documents.php`).
- `erp_account_post()` (`ledger.php`) and `erp_cash_post()` (`cash.php`) ask again, so a path that forgot still cannot write into a closed period. They leave the reason with `erp_lock_refused()`, and `erp_db_error()` returns it in place of a database error, so the caller's usual error path shows the reason.

## Which date is a write judged by?
- A new document: its issue date (`erp_invoice_issue()`, `erp_invoice_from_order()`, expenses).
- A return: the return's own date, so a sale of a closed period can still be taken back today.
- A cancellation: the original document's date (`erp_invoice_cancel()`, `erp_receipt_cancel()`): a document or receipt inside the closed period cannot be cancelled, because that would change what was filed; a return dated today takes it back instead. The reversing entry of a cancellation is itself dated today.
- A payment or receipt: its own date, so an unpaid invoice or expense of a closed period can be paid in the open period.
- The e-document "move to today" step refuses an invoice that sits inside the closed period.

Related: docs/memory/erp/erp-invoice-cancel-and-open-amount.md
