---
name: erp-expense-recurring-restart
description: Starting a stopped repeating expense again goes on from where it stopped and does not write past periods a second time; a restart keeps the first expense as its source
---

## Where does a restarted repeat start?
- `erp_expense_recurrence_create()` asks `erp_expense_recurrence_of()` for the expense's repeat. With a stopped one, the first date is `erp_expense_recurring_first_date($expense_date, $every, $day, $resume, $today)` (pure, `tests/erp_expense_recurring_test.php`): `$resume` is the latest `next_date` of the repeat (`erp_expense_recurrence_resume_date()`), stepped forward until it is today or later. The day of the month is the stopped one's `day_of_month`.
- Without a stopped repeat it is one period after the expense, as before (the run may still catch up to twelve past dates for a back-dated first start).

## What was wrong before?
- The restart always began one period after the expense's own date; the run catches up to twelve past dates and does not look for an expense already written on a date. A rent repeated 08, 09, 10, stopped and started again wrote 08, 09, 10 a second time; paid ones took the money from the till twice and the deductible VAT counted twice (#1078, PR #1227).

## Which recurrences belong together?
- A restart keeps `source_expense_id` of the stopped repeat (the first expense), even when started from an expense the repeat wrote; the expense it was started from gets `recurrence_id` of the new row.
- `erp_expense_recurrence_of()` looks at that family: the expense's own source rows, its `recurrence_id`, and the rows sharing that recurrence's source; the active one first, else the latest. So an expense written by a stopped repeat shows the active restart (and stops it), and a second, parallel repeat of the same expenses is refused ("This expense repeats already.").
- Rows restarted before the fix may have the restarted-from expense as source; they are not rewritten.

## What does the card show?
- `edit_erp_expense.php`, for a stopped repeat: the period preselected to the stopped one's, and the date it goes on from ("Started again, it goes on from …").
