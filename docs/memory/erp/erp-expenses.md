---
name: erp-expenses
description: ERP expenses (4.95): why an expense is nobody's debt, how payment and cancel post to the till, amount and base figures, date and till rules, categories, the API's cash gate and events.
---

## What is an expense in the books? (`includes/erp/expenses.php`, 2026.4.4 step 4.95)
- A receipt that is not a purchase invoice (rent, fuel, a subscription): one `erp_expenses` row, in its own currency and in the base currency. Status `unpaid` (to pay), `paid`, `cancelled`. Screens `erp_expenses.php`, `add_erp_expense.php`, `edit_erp_expense.php`, `erp_expense_categories.php`.
- It is nobody's debt: nothing is written to an account's ledger. Paying is `erp_cash_post()` with `doc_type = 'expense'`, `doc_id` = the expense, `account_id = 0`, in the same transaction as the paid status. Cancelling a paid one turns that movement round, dated today: `doc_type = 'cancel'`, `doc_id` = the movement, so the till book strikes it through like a cancelled receipt.
- A screen that names a till movement by its type needs an `expense` branch (the till book in `edit_erp_till.php` and the dashboard have one).

## How are the amounts worked out?
- `erp_expense_figures($amount, $includes_tax, $rate, $typed_tax)`: the amount is the receipt total (VAT included) or the net. A VAT amount typed from the receipt wins over the rate's, but only within the slack the receipt's line rounding allows.
- Base currency: the total and the VAT are converted separately and the net is the difference, so the base figures add up the way the document's do.
- A paid expense keeps its figures and date (they are what left the till); category, supplier, receipt number, words and `tax_deductible` can still be corrected. A cancelled one is not changed.

## Which date and till rules apply?
- The expense date and the payment date cannot be after today; a payment cannot be dated before the expense; neither may fall in a locked period (`erp_lock_refusal()`).
- The paying till must be active and, while foreign currency is on, kept in the expense's currency (`erp_expense_till_refusal()`); the pay form lists only those tills, and the repeat run and the bank statement go through the same gate (erp-till-inactive-payments.md, #1229).

## What about the categories?
- Switched off, never deleted: a switched-off category keeps its expenses and is offered for no new one. The first read of an empty table writes the defaults in the operator's language (`erp_expense_categories()`, under a named lock so two first visits write them once).
- Whether a category's VAT is taken back: erp-accounting-rules.md.

## What does the API check? (`/erp/expenses…`, routes in `includes/erp/api.php`, handlers in `api_resources.php`)
- Every money movement (a create with `cash_account_id`, pay, cancelling a paid one) calls `erp_api_expense_cash_gate()`: the key needs `erp_cash:write` and its owner the panel's cash right. Till transfers and cheques through the API use the same gate.
- `erp.expense.created|paid|cancelled` are fired with `erp_event_expense()` inside the writer's transaction (a save that rolls back announces nothing; one paid on the spot announces both). A new write path fires them too.
- Receipt files: erp-expense-receipts.md. Repeating expenses: erp-recurring-documents.md.
