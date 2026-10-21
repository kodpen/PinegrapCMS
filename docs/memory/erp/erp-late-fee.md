---
name: erp-late-fee
description: ERP late payment fee (10.61): the rate setting, the pure day-by-day calculation, what lowers what is owed, draft-only writing, late_fee_through so no day is charged twice, the API.
---

## How is the fee set and worked out? (`includes/erp/late_fee.php`, 2026.4.10 step 10.61)
- Terms from config, not constants: `erp_late_fee_settings()` reads `erp_late_fee_rate` (percent a month, 0 = off, at most `ERP_LATE_FEE_MAX_RATE` 50, which catches a yearly rate typed in) and `erp_late_fee_tax_rate` (the fee line's tax rate; null = the account's own zone rate). Set on the ERP Settings card (`erp_settings.php`).
- `erp_late_fee_for_invoice($total, $due_date, $reductions, $after, $as_of, $rate)` is pure: day by day from the day after the due date (or after the last day already charged) on what is still owed; each payment or return comes off on its own date; a month is 30 days; rounded once at the end. Base-currency figures, as the ledger holds them.
- `erp_late_fee_account()` collects the account's issued sales invoices due before the as-of date with their reductions: settlements (`erp_settlements`) and returns. A new source that lowers what is owed must be added to those reductions.

## How is it written?
- Only as a one-line sales invoice draft by `erp_late_fee_draft()`, dated today, in the base currency, with each invoice's days and fee in the notes. The fee panel (`erp_late_fee_panel()`) on the account screen (`edit_erp_account.php`) and the reconciliation screen (`erp_reconciliation.php`) posts to `add_erp_late_fee_invoice.php`. Nothing reaches the ledger until the draft is issued; deleting it charges nothing.
- With a fee draft of the account already waiting, no second one is written: that draft is returned.
- An issued fee invoice carries `erp_invoices.late_fee_through` (the period's last day); the next fee starts the day after (`erp_late_fee_charged_through()`), so no day is charged twice. A fee invoice itself earns no fee.

## What does the API do? (`includes/erp/api_reports.php`)
- `GET /erp/accounts/{id}/late-fee` and `POST /erp/accounts/{id}/late-fee-invoice` call the same functions. A waiting draft is answered with 200 instead of 201; `as_of` cannot be after today; 422 while fees are off or nothing is owed.
