---
name: erp-no-till-hint
description: What the ERP till pickers say when no usable till is set up (erp_no_till_hint()): the screens that call it, the transfer and cheque variants, why the save's refusal texts stay.
---

## Why does a till picker need a hint?
- Tills are opened only by hand on `add_erp_till.php`; no starter dump, installer, migration or API call inserts into `erp_cash_accounts`. A fresh ERP has none, so every picker held only its placeholder (Bank statements: no option at all) and the save was refused with "Choose a till or bank account." or "Choose the bank account.", asking for a choice the list could not give. The usual first visit is the invoice's Record payment button.

## What does the screen show now?
- `erp_no_till_hint($usable_count, $kind)` in `includes/erp/cash.php` returns one `form-text` line with a link to `add_erp_till.php` (Open a Till), or `''` when the picker offers enough. Each screen counts the rows of the same active-till query that fills its picker (`$till_count`).
- `'till'` (one needed): `add_erp_receipt.php`, `erp_bank_statements.php` (inside the upload form), `add_erp_expense.php` (in `#erp_expense_pay_now`; the last-till prefill of `tests/erp_expense_prefill_test.php` is untouched).
- `'transfer'` (two needed, under To till): with one till both selects offer the same till, so it says a second till is needed.
- `'cheque'`: `erp_cheque.php`'s bank select leaves out the cheque's own till (`portfolio_till_id`), so the hint says no bank account is set up besides it; it sits in `$bank_field` (At the bank, Collected, Paid).
- Not added: `erp_cash.php` (its toolbar has Open a Till), `erp_cheques.php` (Kept in already says to add one), `edit_erp_expense.php` (its own alert when `$till_count === 0`), the counter sale (`local_sale.php` note).

## Why are the refusals unchanged?
- "Choose a till or bank account.", "Choose both accounts.", "Choose two different accounts." (`cash.php`) and "Choose the bank account." (`bank_import.php`, `cheques.php`) are also the API's answers; the screen gets the hint, the shared text stays. Test: `tests/erp_till_empty_hint_test.php`.

Related: erp-expenses.md, erp-cheques.md, erp-bank-statement-lines.md
