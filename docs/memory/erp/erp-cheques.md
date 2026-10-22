---
name: erp-cheques
description: ERP cheques and promissory notes (4.101): the steps, money only through erp_post_receipt and erp_post_transfer, a step never rewrites an earlier movement, which tills hold them.
---

## How do cheques move money? (`includes/erp/cheques.php`, 2026.4.4 step 4.101)
- A customer's cheque or note is a collection into a portfolio till the day it is taken, so the customer's debt comes down then. A cheque or note given to a supplier is a payment out of a given-cheques till, settled when the bank pays it.
- Every step moves money only through the ledger's own doors, `erp_post_receipt()` and `erp_post_transfer()`, so the tills, the accounts, the period lock and the audit trail see it as any receipt.
- Steps (`erp_cheque_step()`, allowed starts in `erp_cheque_step_from()`): deposit (portfolio → at the bank, no money moves); collect (portfolio or bank → collected: a transfer from the portfolio till to the bank); endorse (portfolio → passed on: a payment to a supplier out of the portfolio); bounce (portfolio or bank → bounced: a payment back to the customer out of the portfolio, so they owe it again); pay (given → paid: a transfer from the bank to the given-cheques till).
- Each step is a new movement dated its own day; an earlier movement is never rewritten, so a period locked in between stays as it was filed. A step claims the row first (`FOR UPDATE` on its status), so a second click takes no step.

## Which tills hold them?
- Ordinary ERP tills the user opens under Cash and Bank: a portfolio for the ones taken, one for the ones given. Nothing special marks them; the form says to add one first when there is none.
- The cash forecast leaves these tills out of today's cash and counts the cheques on their own dates (erp-cash-forecast.md).
- Screens `erp_cheques.php`, `erp_cheque.php`; API writes (`api_cash.php`) need `erp_cash:write` and the owner's cash right (`erp_api_expense_cash_gate()`).
