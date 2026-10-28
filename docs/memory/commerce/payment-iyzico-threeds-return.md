---
name: payment-iyzico-threeds-return
description: How the iyzico card driver's complete() finishes the 3-D Secure return (PR #1710): the iyzipay_3ds_state row and its claim, the instalment limit, the amount check, the totals the order keeps (#1422)
---

## Who finishes the 3-D Secure return?
- The card driver's `complete()` (`pg_payment_iyzico_complete_with()`, includes/payments/iyzico.php) since PR #1710; before, the Iyzipay block of submit_order.php did, with the same request, sentences and log lines (that block was removed in PR #2770).
- The start (`authorize`) writes the `iyzipay_3ds_state` row before iyzico is asked (`pg_payment_iyzico_threeds_keep()`: conversation id, order id, base total, instalment charge, discount) and keeps the visitor's instalment choice as the driver's session state.

## What does the return check, in order?
- The kept instalment choice against the store's limit (it may have been lowered meanwhile); no new quote from iyzico. No state, no check.
- The row of the posted `conversationId` (`pg_payment_iyzico_threeds_find()`) must belong to the session's order and a `paymentId` must be posted, else "Session could not be verified".
- `pg_payment_iyzico_threeds_claim()` writes the posted payment id into the row only while it is empty: a replayed return finds it used and is refused. Up to here iyzico is not asked and the session state is kept.
- ThreedsPayment: error 5002, a status other than success, fraud -1 refuse; fraud 0 is logged and passes.
- Amount: the order total now equals the row's base, iyzico's paid price equals base plus charge (1% either way under a protected currency, at today's rate), else the payment is cancelled (`pg_payment_iyzico_void()`, MANUAL REFUND REQUIRED when iyzico refuses).

## What does the order keep? (#1422)
- The answer's amount is the row's base plus charge, so `pg_payment_checkout_totals()` gives the row's total and charge, the figures `pg_payment_threeds_state_totals()` gives: `orders.installment_charges` is what the bank took, lowered by #1269 where a rate's total would be out of reach (erp/erp-order-charges-vat.md). The instalment count is the bank's.
- The return posts no card: submit_order.php's driver branch sets the card number and expiry to `''` on a return (`$payment_return`); a hosted card (Stripe) keeps the service's last four digits.
- Before #1422 the charge came from a new quote at an empty BIN (0, or another bank's figure), so the order's ERP invoice was refused as not matching the order total.

## Tests
- `tests/payment_iyzico_threeds_return_test.php`: the row's totals and the order's invoice (57.92 + 2.04), the driver's request against the gateway code's, each refusal with its sentence and log line, the claim, the instalment limit, the void, the protected currency, the driver branch's empty card.
