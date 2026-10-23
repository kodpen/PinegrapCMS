---
name: erp-recurring-invoice-day
description: The day of the month a repeating invoice keeps: the 31st repeats on each month's last day, not the 28th; upgrade step 11.0.60 moves recurrences written before back
---

## Which day does a repeating invoice keep?
- `erp_invoice_recurrence_create()` stores `day_of_month` from `erp_invoice_recurring_day($issue_date, $first_date)` (pure, `includes/erp/invoice_recurring.php`, `tests/erp_invoice_recurring_test.php`).
- The day is the first date's day, unless the first date is the last day of its month and the invoice's own day is larger: then the invoice's day. 31.01 with first 28.02 keeps 31 (31.03, 30.04, 31.05…); 30.01 keeps 30; a first date the operator moved to another day (15.03) keeps that day.
- The run and the cash forecast step with `erp_expense_recurring_step($date, $months, $day_of_month)`, which cuts the day to a shorter month's last day, so the stored day must be the uncut one.

## What was wrong before?
- The day was taken from the first date (`substr($first, 8, 2)`). The invoice screen proposes the first date with the step, so an invoice of the 31st proposed 28.02 and the recurrence stayed on the 28th for good; 31.08 drifted to 30.09, 30.10… (#1074, PR #1226). Repeating expenses always took the expense's own day and were right.

## What happens to recurrences written before the fix?
- Upgrade step 11.0.60 (`upgrade_2026_11_0_erp_invoice_recurring_day()`, 2026.11.0) moves them back to the invoice's day. The first date is not stored (`next_date` moves on with each run), so a row is fixed only when it provably started on the screen's proposal P = issue date + 1 month: active, `day_of_month` 28/29/30, P's day equals it and is below the invoice's day, and `next_date` = P + `occurrences` x `every_months` months (where every written run leaves it).
- It sets `day_of_month` to the invoice's day and moves the pending `next_date` to that day inside its own month (28.11 to 30.11 for an invoice of the 31st), never into another month, and leaves `next_date` when the moved date would pass `end_date`. Re-runnable: a fixed row no longer matches.
- Left as they are: a first date the operator changed, a recurrence with a period skipped by the period lock (`next_date` ahead of the count), one whose source invoice is gone, and stopped ones. Stopping one and starting it again from the invoice stores the right day.

## Where is the first date proposed?
- `edit_erp_invoice.php`, the "Repeat this invoice" card: one month after the issue date whatever the period, through the step (the same date as MySQL `DATE_ADD(issue_date, INTERVAL 1 MONTH)`). The API (`/erp/invoices/{id}/recurrence`, `includes/erp/api_documents.php`) takes `first_date` and goes through the same create function.
