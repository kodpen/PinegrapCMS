---
name: erp-expense-change-reaches-repeat
description: Changing an expense also updates the active repeat of its family (supplier, tax number, category, description, amount, tax rate, tax_deductible); frequency, day, end and till stay
---

## What does a change to a repeating expense do?
- `erp_expense_save()` for an existing expense calls `erp_expense_recurrence_follow($id)` inside the same transaction (`includes/erp/expense_recurring.php`). It finds the family's active repeat with `erp_expense_recurrence_of()`, so a correction on the source expense or on any expense the repeat (or a restart) wrote reaches it.
- `erp_expense_recurrence_changes($expense, $recurrence)` (pure, `tests/erp_expense_recurring_test.php`) returns the columns that differ: category, supplier, tax number, description, `tax_deductible`, and `amount` (the receipt's total) with `tax_rate`. A stopped repeat or no repeat gives nothing.
- Frequency, day of month, end date, due days, pay mode and till are not touched; expenses already written stay.
- An expense changed to another currency keeps the repeat's amount and rate: that is not a corrected amount, and a repeat written as paid pays from a till in its own currency.
- A failed update rolls back the expense change.

## What does the change form say?
- `add_erp_expense.php?id=…`, when the expense's family has an active repeat: an info line that the active repeat takes the new values and written expenses stay.

## Why?
- Owner decision (#6687, `workflow/decisions-made-product-28.md`): before, the repeat kept the values copied when it started; an expense corrected from 'Kira A' 1.000,00 to 'Kira B' 1.500,00 kept writing 'Kira A' 1.000,00, and no screen edits a repeat.
