---
name: erp-cash-forecast
description: ERP cash forecast: what counts as money in and out over 30, 60 or 90 days, the item shape, cheque tills left out of today's cash, how a new source is added, the API's scope.
---

## What does the forecast read? (`includes/erp/cash_forecast.php`, screen `erp_cashflow.php?view=forecast`)
- What is open today, never the ledger's past; nothing is written. In: open sales invoices on their due dates, the next repeating invoices (due by the account's term), cheques and notes received still in the portfolio or at the bank. Out: open purchase invoices, unpaid expenses, the next repeating expenses, cheques and notes given and not yet paid. Drafts and cancelled documents are left out.
- Base currency: open amounts at the booked rates (`includes/erp/aging.php`), a cheque's booked amount, and for a repeating document in another currency today's recorded rate (left out and counted as `unpriced` when there is none). A till in another currency is not counted (`foreign_tills`).
- Columns: past due (counted as if settled today), then 30 days each up to the horizon. The position starts from what the base-currency tills hold now, less the tills the cheques are kept in (`erp_cash_forecast_cheque_tills()`): those cheques are counted on their own dates instead.

## How is a new source added?
- `erp_cash_forecast_items()` collects the items; the pure `erp_cash_forecast_build()` pours them into the columns. A new money source with a date (an instalment plan, say) gets a kind in `erp_cash_forecast_kinds()` (label, in or out) and its collection in `erp_cash_forecast_items()`.
- Every item carries `kind`, `date`, `amount`, `title`, `party`, `url`, `source` (invoice, expense, invoice_recurrence, expense_recurrence, cheque), `source_id`, `account_id`; the API's `items` hands these on.

## Who may read it?
- The screen needs the cash right. `GET /erp/reports/cash-forecast` needs the `erp_cash:read` scope and an owner with the cash right (`includes/erp/api_reports.php`).
- What actually happened is the cash flow report: erp-cashflow-report.md.
