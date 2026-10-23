---
name: payment-driver-iyzico
description: iyzico drivers (card for Iyzipay, redirect for Pay With Iyzico), the one submit_order.php branch, the 3-D Secure return still in the Iyzipay block, behaviour tests
---

## Which drivers are there?
- `iyzico` (card, gateway `Iyzipay`) and `pay_with_iyzico` (redirect, method `Pay With Iyzico`), one file, two prefixes, both `'settings' => 'config'`: keys, mode, 3DS, instalment limit, protected currency and the PWI switch stay in the config columns the Ecommerce card already draws; no `_fields()`.
- Card: `authorize` (direct payment or 3-D Secure start), `refund`, `cancel`; no `complete`. PWI: `authorize`, `complete`, `refund`, `cancel`. Capabilities: installments, partial_refund (`threeds` waits for the card's `complete()`).
- Every request is built with the same values as the gateway code: CreatePaymentRequest (instalment price from RetrieveInstallmentInfo at the BIN; protected currency converts price, paid price and basket at today's rate), PWI initialize (base currency, unconverted), AmountBaseRefund (view_order.php), Cancel (process_order_cancellation(), iyzipay_cancel_payment()). Same visitor sentences and log lines.

## How does submit_order.php use them?
- One branch before the non-recurring charge switch: `pg_payment_checkout_driver(method, ECOMMERCE_PAYMENT_GATEWAY, mode)` non-empty → `authorize` (or `complete` on the driver's return mode) through `pg_payment_call()`; the switch runs only when it is empty. The answer is recorded in the variables the gateway code set (`pg_payment_checkout_totals()`: amount above the total = instalment charge; `card_brand` → `card_type`).
- Return modes keep the old addresses (`pg_payment_return_mode()`): `iyzipay_threedsecure_return`, `pay_with_iyzico_return`, so the checks at the top of submit_order.php and order_preview.php apply unchanged.
- A return whose driver has no `complete()` goes to the gateway code: the frozen Iyzipay block finishes the iyzico 3-D Secure return from the `iyzipay_3ds_state` row the driver writes before asking iyzico (`pg_payment_iyzico_threeds_keep()`).
- That return posts no card (`$card_number` is `''`) and asks for no new quote: after the amount check the order takes the row's total and charge (`pg_payment_threeds_state_totals()`, #PR). Before, `orders.installment_charges` got a quote at an empty BIN; the invoice was refused.
- The card's instalment charge goes the fewest kurus lower where it would leave a rate's total out of reach (`pg_order_installment_charge()`, #1269): submit_order.php passes `settle_rates`, `charged_lines` and `charges`; the 3-D Secure row and the order keep the lower figure (erp/erp-order-charges-vat.md).
- PWI state (token, conversation id, order id, base total) moved from four `pay_with_iyzico_*` session keys to `ecommerce.payment_state.pay_with_iyzico`; a token or order mismatch keeps it (iyzico not asked), any answer from iyzico ends it.
- A registered driver that cannot load refuses the payment; it never falls back to the gateway code (a card gateway with no legacy case would complete unpaid).
- Errors are escaped with `h()` before `mark_error()` (the old blocks did not).

## What is not on the drivers yet?
- The 3-D Secure return (`complete()` for the card driver).
- `iyzipay_cancel_payment()` (submit_order.php's frozen blocks call it).
- The order screen's refund and the cancellation's void go through the drivers (`commerce/payment-refunds.md`), card orders included.

## How was it verified?
- `tests/payment_iyzico_test.php`: a stand-in HTTP client (`\Iyzipay\ApiResource::setHttpClient()`) records the posted JSON, compared with requests built by the gateway code's own statements (`legacy_*()`), plus answers for 3DS start, instalments, protected currency, fraud, PWI return checks, refund and cancel. No running shop or iyzico sandbox was used.
- `tests/payment_iyzico_threeds_return_test.php`: the return's totals and their invoice.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/order-cancellation-config.md, docs/memory/plans/payment-driver-layer.md
