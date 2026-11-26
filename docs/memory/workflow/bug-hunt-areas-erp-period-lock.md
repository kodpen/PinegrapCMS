---
name: bug-hunt-areas-erp-period-lock
description: The bug hunt of the ERP period lock and the accountant pack (architect, 2026-11-26, request 3141): what it fixed, what was read clean, what was left
---

## Which area was hunted?
- The period lock (`includes/erp/lock.php`, `erp-period-lock.md`) and the accountant pack (`includes/erp/accountant.php`, `erp_accountant.php`, `erp_accountant_download.php`, `erp_accountant_job.php`, `erp-accountant-pack.md`).
- Looked at: every writer of a dated ledger or till record (screens, API, recurring jobs, bank statement, cheques, counter sales), the pack's link token, who may fetch the ZIP, and the pack's sheets against the VAT report and Cash flow screens.

## What did the hunt's PR fix?
- PR #11869. Add account (`add_erp_account.php`) saved the account and only then met the lock on its opening balance: the account stayed without it, and the edit screen has no opening card. The lock is now asked on the Date field before the save, as the API already did (`erp-period-lock.md`).

## What was read clean?
- Writers: `erp_account_post()` and `erp_cash_post()` are the only inserts into the two ledgers and both refuse a locked date; invoice issue, order invoice, return, cancel (by the original date), receipt, transfer, cheque create and steps, expense save, pay and cancel, account opening (panel and API), both recurring jobs (skip and note a locked date), e-document "move to today" ask up front.
- Reversals of a cancel, a gift card credit and an exchange difference are dated today, so they never meet the lock.
- Pack link: 48-hex random token, only its sha256 kept, expiry checked in SQL, wrong or spent token gets the same 404; a failed e-mail restores the previous link.
- Who fetches: download, link and e-mail of a pack with till sheets need the cash right (`USER_MANAGE_ERP_CASH` matches `validate_erp_access($user, 'cash')`); link and e-mail refuse the read-only right; lock and settings need the ERP settings right.
- Pack against screens: documents, lines, per-document figures, VAT by rate, withholding and the difference come from the same functions as the VAT report (`erp_accountant_documents()`, `erp_accountant_document_figures()`, `erp_vat_add_document()`, `erp_vat_difference()`); the till sheet uses `erp_cashflow_by_till()`.

## What was left?
- A waybill fills an empty shipment date or carrier on an issued invoice even inside the locked period (`waybills.php`); only blanks are filled, so no fault was built.
- Allocating or removing an allocation between a receipt and an invoice of the locked period changes the invoice's paid status (no ledger row); read as a product choice.
- `erp_accountant_job.php` run from the panel by GET builds and sends without a form token; other jobs run the same way.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/erp/erp-period-lock.md, docs/memory/erp/erp-accountant-pack.md
