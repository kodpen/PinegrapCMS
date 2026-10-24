---
name: erp-till-inactive-payments
description: An expense is never paid from a till or bank account that was taken off (is_active = 0): the one gate, who goes through it, what a repeat does then, how its till is changed
---

## Which till can pay an expense?
- `erp_expense_till_refusal($cash_account_id, $currency)` (`includes/erp/expenses.php`) reads the till and asks `erp_expense_till_row_refusal($till, $currency)` (pure, `tests/erp_expense_till_test.php`): missing → "Choose a till or bank account.", `is_active` not 1 → "That till or bank account is not active." (the API's own wording), then the currency rule while foreign currency is on.
- Every payment of an expense goes through it: `erp_expense_pay()` (panel, API pay), `erp_expense_save()` with `pay` (new expense, API, bank statement line, the repeat run) and `erp_expense_recurrence_create()` for a repeat written as paid.

## What was wrong before?
- The gate read `is_active` and never compared it. The screens list only active tills, so the hole was a repeat "paid from Bank A" after Bank A was taken off: the run kept writing paid expenses out of it, and since "total of active tills" and the cash forecast skip inactive tills, the money left no trace in any summary (#1079, PR #1229).

## What does a repeat do now?
- The run's `erp_expense_save()` fails, the date is given back and the reason goes to `last_error` (shown on the expense's repeat card); the next run tries again.
- An expense already paid from the till keeps its movement; cancelling it still puts the money back into that till.

## How is a repeat moved to another till? (#1332, PR #1342)
- The repeat card on `edit_erp_expense.php` has "Change the till" (a till select and a method select) for an active repeat written as paid, shown only with the till right (`USER_MANAGE_ERP_CASH`) and not to the read-only accountant; the POST action `repeat_till` checks the same. It opens by itself while the named till is taken off.
- `erp_expense_recurrence_set_till($id, $cash_account_id, $method)` (`includes/erp/expense_recurring.php`) asks `erp_expense_recurrence_till_refusal($recurrence, $till, $method)` (pure, `tests/erp_expense_recurring_test.php`): active, then `pay_mode = 'paid'`, then the till gate above, then the method against `erp_cash_payment_methods()`. It writes `cash_account_id`, `payment_method`, clears `last_error`, and leaves the expenses already written alone.
- The given-back date is written on the next run from the new till, on its own date, with the lock checked per date; a run catches up at most twelve dates.
- Only the till changes. Paid to unpaid (or back) is still stop, then start again from an expense; a paid restart takes that expense's till. No API endpoint and no `erp_event` exist for repeats (start, stop, till change); the panel's `log_activity()` line reaches the audit trail because the file is named `erp_*`.
