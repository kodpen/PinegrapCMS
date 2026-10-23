---
name: payment-iyzico-threeds-return
description: How the frozen Iyzipay block of submit_order.php finishes iyzico's 3-D Secure return: the iyzipay_3ds_state row, the amount check, the total and instalment charge the order keeps (#1422)
---

## Who finishes the 3-D Secure return?
- The card driver has no `complete()`, so `iyzipay_threedsecure_return` goes to the frozen Iyzipay block of submit_order.php. It reads the `iyzipay_3ds_state` row the driver wrote before iyzico was asked (`pg_payment_iyzico_threeds_keep()`: base total, instalment charge, discount). The row must belong to the session's order, and writing its `payment_id` claims it once, so a replayed callback is refused.

## What does the order keep? (#1422)
- The return posts no card: `$card_number` and the expiry are `''`. The instalment limit is still checked, but iyzico is not asked for a new quote.
- After the amount check (the current total equals the row's base, iyzico's paid price equals base plus charge, else the payment is cancelled), the order takes the row's total and charge (`pg_payment_threeds_state_totals()`). `orders.installment_charges` is what the bank took, lowered by #1269 where a rate's total would be out of reach (erp/erp-order-charges-vat.md).
- Before, the charge came from a new quote at an empty BIN (0, or another bank's figure), so the order's ERP invoice was refused as not matching the order total.

## Tests
- `tests/payment_iyzico_threeds_return_test.php`: the row's totals, the order's invoice (57.92 + 2.04), the order of the return block's statements.
