---
name: erp-recurring-invoice-day
description: The day of the month a repeating invoice keeps: an invoice of the 31st repeats on every month's last day, not on the 28th the first February cut it to
---

## Which day does a repeating invoice keep?
- `erp_invoice_recurrence_create()` stores `day_of_month` from `erp_invoice_recurring_day($issue_date, $first_date)` (pure, `includes/erp/invoice_recurring.php`, `tests/erp_invoice_recurring_test.php`).
- The day is the first date's day, unless the first date is the last day of its month and the invoice's own day is larger: then the invoice's day. 31.01 with first 28.02 keeps 31 (31.03, 30.04, 31.05…); 30.01 keeps 30; a first date the operator moved to another day (15.03) keeps that day.
- The run and the cash forecast step with `erp_expense_recurring_step($date, $months, $day_of_month)`, which cuts the day to a shorter month's last day, so the stored day must be the uncut one.

## What was wrong before?
- The day was taken from the first date (`substr($first, 8, 2)`). The invoice screen proposes the first date with the step, so an invoice of the 31st proposed 28.02 and the recurrence stayed on the 28th for good; 31.08 drifted to 30.09, 30.10… (#1074, PR #XXXX). Repeating expenses always took the expense's own day and were right.
- Recurrences written before the fix keep their stored day; the fix does not rewrite them (no data step). Stopping one and starting it again from the invoice stores the right day.

## Where is the first date proposed?
- `edit_erp_invoice.php`, the "Repeat this invoice" card: one period after the issue date, through the step. The API (`/erp/invoices/{id}/recurrence`, `includes/erp/api_documents.php`) takes `first_date` and goes through the same create function.
