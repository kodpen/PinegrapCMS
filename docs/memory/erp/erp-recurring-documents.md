---
name: erp-recurring-documents
description: Repeating ERP expenses (4.96) and invoices (4.93): what may repeat, how a run claims a date, lock and error handling, where runs start, draft or issue mode, stop and restart.
---

## What can repeat?
- Expenses (`includes/erp/expense_recurring.php`, `erp_expense_recurrences`, 4.96): any expense not cancelled. The next ones are left to pay (due `due_days` later) or paid from a named till on their own date (a direct debit).
- Invoices (`includes/erp/invoice_recurring.php`, `erp_invoice_recurrences`, 4.93): only an issued sales invoice typed in the ERP (`order_id = 0`, `erp_invoice_recurring_refusal()`). An order's invoice carries shipping and fees in its header, so a copy of its lines would bill less.
- Every 1, 3, 6 or 12 months (`erp_expense_recurring_periods()`), on the same day of the month or the month's last day (`erp_expense_recurring_step()`); an optional end date.

## How does a run write them?
- An expense always through `erp_expense_save()` (lock, VAT and till rules apply as for any expense). An invoice through `erp_invoice_draft_save()` (mode `draft`, the default) or `erp_invoice_create_manual()` (mode `issue`, takes the next number).
- A date is claimed with `UPDATE … SET next_date = <next> WHERE id = … AND next_date = <date> AND status = 'active'`; only the run whose update hit the row writes it, so two runs never write one month twice.
- A date in a locked period is skipped and the reason kept in `last_error`; any other failure gives the date back so the next run tries again.
- The give-back is conditional on `next_date` still being the date this run moved it to; when a run beside it has moved on since, only `last_error` is written. An unconditional give-back wound the row back under the other run and wrote its later date twice.
- Starting a repeat runs its "already repeats" look and the insert under `erp_start_lock()` (`GET_LOCK` per invoice or expense, in `expense_recurring.php`): a form sent twice started two active repeats, each writing every period. Typed first and end dates must be calendar days (`erp_recurring_date_ok()`, `tests/erp_expense_recurring_test.php`): 30.02 stored `0000-00-00` (never due, or no end).
- One run: at most 50 recurrences, 12 dates each.
- Runs: the daily jobs (`erp_expense_recurring_job.php`, `erp_invoice_recurring_job.php`) and a visit to the expense or invoice list by a user who may change things (not the read-only accountant), so a store without a crontab still gets its rent written.

## What follows an invoice a run issued?
- Whatever the store does after any issue: e-document sending, the automatic e-mail (erp-invoice-mail.md). Before testing the issue mode on dev, check that automatic sending is off.
- A foreign-currency document takes the rate recorded for its day; a draft may wait for one, an issued invoice or an expense cannot be written without it.

## How is a recurrence stopped or restarted?
- Stopping keeps what it wrote. A stopped recurrence row is not started again; starting the document again writes a new row. One active recurrence per source document.
- A restarted expense repeat goes on from the stopped one's `next_date`, stepped to today, and never writes a past period again; the new row keeps the first expense's `source_expense_id`, so `erp_expense_recurrence_of()` finds the same repeat from any of its expenses (#1227, erp-expense-recurring-restart.md).
- A new invoice repeat never starts in the past: the card proposes the later of one period after the invoice and tomorrow (`erp_invoice_recurring_first_default()`), with `min` on its date inputs, and `erp_invoice_recurrence_create()` refuses a first date before today; running repeats keep the 12-date catch-up, which is for missed job runs only (#5322).
- Deleting a draft a repeat wrote clears that repeat's `last_invoice_id`; the repeats screen and the API read the last invoice through the join, so an id with no row shows as none (#5304).
