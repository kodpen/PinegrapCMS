---
name: erp-cheques
description: ERP cheques and promissory notes (4.101): the steps, money only through erp_post_receipt and erp_post_transfer, a step never rewrites an earlier movement, which tills hold them.
---

## How do cheques move money? (`includes/erp/cheques.php`, 2026.4.4 step 4.101)
- A customer's cheque or note is a collection into a portfolio till the day it is taken, so the customer's debt comes down then. A cheque or note given to a supplier is a payment out of a given-cheques till, settled when the bank pays it.
- Every step moves money only through the ledger's own doors, `erp_post_receipt()` and `erp_post_transfer()`, so the tills, the accounts, the period lock and the audit trail see it as any receipt.
- Steps (`erp_cheque_step()`, allowed starts in `erp_cheque_step_from()`): deposit (portfolio → at the bank, no money moves); collect (portfolio or bank → collected: a transfer from the portfolio till to the bank); endorse (portfolio → passed on: a payment to a supplier out of the portfolio); bounce (portfolio or bank → bounced: a payment back to the customer out of the portfolio, so they owe it again); pay (given → paid: a transfer from the bank to the given-cheques till).
- Each step is a new movement dated its own day; an earlier movement is never rewritten, so a period locked in between stays as it was filed. A step claims the row first (`FOR UPDATE` on its status), so a second click takes no step.

## What does a bounce do to the invoices the cheque closed? (#4429, PR #4517)
- In the step's transaction it takes every allocation off the cheque's receipt (`open_cash_id`, `erp_txn_unsettle_all()`, the path `erp_receipt_cancel()` uses) and works each invoice out again, so the returned debt is on open invoices, aging and the overdue view. The note says how many invoices opened again. Before, the invoice stayed "Paid" and the debt showed only on the account balance.

## Can a bounced cheque's movements be allocated afterwards? (#4694, PR #4780)
- No. Its collection (`open_cash_id`) and the bounce payment (`close_cash_id`) cancel each other out; allocating the first again re-closed the reopened invoice. `erp_cheque_allocation_refusal($cheque)` gives the reason; `erp_settlement_allocate()` refuses with it before writing, and `erp_receipt.php` shows it instead of the allocation form, with Unallocated 0.
- A cheque bounced before #4429's fix keeps its allocations: `erp_cheque_bounced_allocations()` lists them, the cheque screen names the invoices and links to the receipt, whose Remove opens them. Nothing repairs those rows on its own.

## Which date may a step carry? (#4436, PR #4517)
- Not before the cheque's own `doc_date`: `erp_cheque_step_date_refusal()` inside `erp_cheque_step()`, so the screen and the API refuse alike; the screen's date box starts at today or `doc_date` and carries `min`. Before, a back-dated collect left the portfolio below zero for the days between.

## What does the screen say after a step?
- `erp_cheque_step_note($step)`: deposit says the cheque went to the bank and the money moves when the bank collects it; collect and pay say the amount moved between the business's own tills; endorse and bounce say the movement is in the till and on the account. Before, every step said the last, so handing a cheque to the bank seemed to move money (#4004, PR #4184).

## Which tills hold them?
- Ordinary ERP tills the user opens under Cash and Bank: a portfolio for the ones taken, one for the ones given. Nothing special marks them; the form says to add one first when there is none.
- Their postings pass `'allow_below_zero' => true`, so the balance check a cash till meets elsewhere (erp-till-below-zero.md, #4460, PR #4666) does not stop the given-cheques till going below zero until the bank pays.
- The cash forecast leaves these tills out of today's cash and counts the cheques on their own dates (erp-cash-forecast.md).
- Screens `erp_cheques.php`, `erp_cheque.php`; API writes (`api_cash.php`) need `erp_cash:write` and the owner's cash right (`erp_api_expense_cash_gate()`).
