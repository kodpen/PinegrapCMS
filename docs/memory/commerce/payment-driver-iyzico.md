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
- A return whose driver has no `complete()` goes to the gateway code: the iyzico 3-D Secure return is still finished by the frozen Iyzipay block, which reads the `iyzipay_3ds_state` row the driver writes at the start (`pg_payment_iyzico_threeds_keep()`, same columns and values, written before iyzico is asked, as before).
- PWI state (token, conversation id, order id, base total) moved from four `pay_with_iyzico_*` session keys to `ecommerce.payment_state.pay_with_iyzico`; a token or order mismatch keeps it (iyzico not asked), any answer from iyzico ends it.
- A registered driver that cannot load refuses the payment; it never falls back to the gateway code (a card gateway with no legacy case would complete unpaid).
- Errors are escaped with `h()` before `mark_error()` (the old blocks passed iyzico's message raw; the text shown is the same).

## What is not on the drivers yet?
- The 3-D Secure return (`complete()` for the card driver).
- Refund and cancel callers: view_order.php and process_order_cancellation() still call iyzico themselves (plan step 5). process_order_cancellation() tries iyzico only when `payment_method` is `Iyzipay` or `Pay With Iyzico`, and a card order stores `Credit/Debit Card`, so a card order's automatic refund never runs today.

## How was it verified?
- `tests/payment_iyzico_test.php`: a stand-in for iyzico's HTTP client (`\Iyzipay\ApiResource::setHttpClient()`) records the posted JSON, compared with requests built by the gateway code's own statements (copied as `legacy_*()`), plus answers for 3DS start, instalments, protected currency, fraud, PWI return checks and cancel, refund and cancel. No running shop or iyzico sandbox was used.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/order-cancellation-config.md, docs/memory/plans/payment-driver-layer.md
