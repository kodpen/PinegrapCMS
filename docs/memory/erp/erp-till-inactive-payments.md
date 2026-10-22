---
name: erp-till-inactive-payments
description: An expense is never paid from a till or bank account that was taken off (is_active = 0): the one gate, who goes through it, what a repeat does then
---

## Which till can pay an expense?
- `erp_expense_till_refusal($cash_account_id, $currency)` (`includes/erp/expenses.php`) reads the till and asks `erp_expense_till_row_refusal($till, $currency)` (pure, `tests/erp_expense_till_test.php`): missing → "Choose a till or bank account.", `is_active` not 1 → "That till or bank account is not active." (the API's own wording), then the currency rule while foreign currency is on.
- Every payment of an expense goes through it: `erp_expense_pay()` (panel, API pay), `erp_expense_save()` with `pay` (new expense, API, bank statement line, the repeat run) and `erp_expense_recurrence_create()` for a repeat written as paid.

## What was wrong before?
- The gate read `is_active` and never compared it. The screens list only active tills, so the hole was a repeat "paid from Bank A" after Bank A was taken off: the run kept writing paid expenses out of it, and since "total of active tills" and the cash forecast skip inactive tills, the money left no trace in any summary (#1079, PR #1229).

## What does a repeat do now?
- The run's `erp_expense_save()` fails, the date is given back and the reason goes to `last_error` (shown on the expense's repeat card); the next run tries again. There is no screen to change a repeat's till: the operator turns the till back on, or stops the repeat and starts it again written to pay (a paid restart takes the till of the expense it is started from, which is the inactive one).
- An expense already paid from the till keeps its movement; cancelling it still puts the money back into that till.
