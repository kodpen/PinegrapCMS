---
name: erp-till-below-zero
description: A cash till (drawer, received-cheque portfolio) never goes below zero on a transfer, payment or expense: the one check, where it is asked, who skips it, what an already negative till does
---

## Which tills may go below zero? (#4460)
- Only tills of kind `bank`, `pos` and `credit_card` (an overdraft, card credit). A till of kind `cash` (the drawer, a received-cheque portfolio) refuses an outflow larger than its balance with "Not enough balance in the till." ("Kasada yeterli bakiye yok."); emptying it to exactly zero is allowed.
- A till already below zero (from before the check) is left as it is; every new outflow from it is refused until money comes in or a correction is posted. No row is rewritten, no schema.

## Where is it asked?
- `erp_till_outflow_row_refusal($till, $amount)` in `includes/erp/cash.php` is the pure rule (kind and balance; `tests/erp_till_below_zero_test.php`). `erp_till_outflow_refusal($cash_account_id, $amount)` reads the till's `kind, balance` with `FOR UPDATE`, so inside the posting transaction two outflows at once cannot both pass on one balance. It reads the cached `balance`, which `erp_cash_refresh_balance()` rewrites in the same transaction as every movement and which the Cash and Bank screen shows.
- Transfer: `erp_post_transfer()` after `erp_tx_begin()`, before the first movement (from-till). The API (`erp_api_cash_transfers_create()`) asks the row rule on the till it read before the dry run stops.
- Payment: `erp_post_receipt_check()` for `direction = payment` (screen, API, API dry run, bank statement line) and again in `erp_post_receipt()` after `erp_tx_begin()`.
- Expense payment: `erp_expense_payment_refusal()` with the expense's `total_amount` (`erp_expense_save_check()` passes it), so a new expense paid on the spot, Pay, the API, a bank statement line and a repeat paid from a till all meet it; inside `erp_expense_post_payment()` it is asked again under the lock. A repeat refused this way keeps its date and shows the refusal as its last error, and the next run tries again.

## Who skips it?
- Only the cheque module (`includes/erp/cheques.php`): its three postings pass `'allow_below_zero' => true`. The given-cheques till is below zero by design between the giving and the bank's payment (`erp-cheques.md`). The API files never pass the flag (pinned by the test).
- Corrections stay free: a cancel's reversal (`erp_receipt_cancel()`, an expense's cancel) and a till's opening figure.
