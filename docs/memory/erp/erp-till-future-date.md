---
name: erp-till-future-date
description: A receipt, payment, transfer or expense payment dated after today is refused by one shared check; the cheque module keeps its dates; rows already dated ahead stay
---

## What is refused? (#5581, PR #6372)
- A till movement whose `doc_date` is after today: "A till movement cannot be dated after today. Record money that comes or goes later as a cheque or note, or as a due date." ("Kasa hareketi bugünden sonraya tarihlenemez. …").
- Why: the till's cached balance sums every movement whatever its date, so a receipt dated 2030 let a payment of money not in the drawer pass the below-zero check (`erp-till-below-zero.md`) and the ledger read negative today. The till ledger holds money that moved; money due later is a cheque, a note or an invoice due date.

## Where is it asked?
- `erp_till_future_date_refusal($date, $today = null)` in `includes/erp/cash.php` (pure; `tests/erp_till_future_date_test.php`). An empty date passes: the date checks refuse it with their own words.
- `erp_post_receipt_check()` (receipt and payment: screen, API, dry run, bank statement line, local order payments), `erp_post_transfer()`, `erp_expense_payment_refusal()` (field `paid_date`; it replaced the expense's own "The payment cannot be dated after today.").

## Who skips it?
- Only the cheque module's three postings (`includes/erp/cheques.php`) with `'allow_future_date' => true`: a cheque keeps the date written on it. The API files never pass the flag (pinned by the test).

## What happens to rows already dated ahead?
- Nothing: they stay, the cached balance is not recomputed, no schema.
