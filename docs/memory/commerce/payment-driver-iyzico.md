---
name: payment-driver-iyzico
description: iyzico drivers (card for Iyzipay, redirect for Pay With Iyzico), the one submit_order.php branch, the 3-D Secure return still in the Iyzipay block, behaviour tests
---

## Which drivers are there?
- `iyzico` (card, gateway `Iyzipay`) and `pay_with_iyzico` (redirect, method `Pay With Iyzico`), one file, two prefixes, both `'settings' => 'config'`: keys, mode, 3DS, instalment limit, protected currency and the PWI switch stay in the config columns the Ecommerce card already draws; no `_fields()`.
- Card: `authorize` (direct payment or 3-D Secure start), `refund`, `cancel`; no `complete`. PWI: `authorize`, `complete`, `refund`, `cancel`. Capabilities: installments, partial_refund (`threeds` waits for the card's `complete()`).
- Every request is built with the same values as the gateway code: CreatePaymentRequest (instalment price from RetrieveInstallmentInfo at the BIN; protected currency converts price, paid price and basket at today's rate), PWI initialize (base currency, unconverted), AmountBaseRefund (view_order.php; card: see below), Cancel (process_order_cancellation(), iyzipay_cancel_payment()). Same visitor sentences and log lines.

## Why is a card refund refused with a protected currency?
- The card is charged in it at the day's rate, which the order does not keep; AmountBaseRefund reads its price in the payment's currency, so 20.00 TRY went back as 20.00 USD (#PR). `pg_payment_iyzico_card_refund_with()` refuses before sending: refund on iyzico's own screen. PWI (base currency) still refunds; a USD payment refunded after the switch is off is not caught.

## How does submit_order.php use them?
- One branch before the non-recurring charge switch: `pg_payment_checkout_driver(method, ECOMMERCE_PAYMENT_GATEWAY, mode)` non-empty → `authorize` (or `complete` on the driver's return mode) through `pg_payment_call()`; the switch runs only when it is empty. The answer is recorded in the variables the gateway code set (`pg_payment_checkout_totals()`: amount above the total = instalment charge; `card_brand` → `card_type`).
- Return modes keep the old addresses (`pg_payment_return_mode()`): `iyzipay_threedsecure_return`, `pay_with_iyzico_return`, so the checks atop submit_order.php and order_preview.php still apply.
- A return whose driver has no `complete()` goes to the gateway code: the iyzico 3-D Secure return is still finished by the frozen Iyzipay block, which reads the `iyzipay_3ds_state` row the driver writes before iyzico is asked (`pg_payment_iyzico_threeds_keep()`).
- The card's instalment charge goes the fewest kurus lower where it would leave a rate's total out of reach (`pg_order_installment_charge()`, #1269): submit_order.php passes `settle_rates`, `charged_lines` and `charges`; the 3-D Secure row keeps the lower figure (erp/erp-order-charges-vat.md).
- PWI state (token, conversation id, order id, base total) is in `ecommerce.payment_state.pay_with_iyzico`; a token or order mismatch keeps it (iyzico not asked), any answer from iyzico ends it.
- A registered driver that cannot load refuses the payment; it never falls back to the gateway code (a card gateway with no legacy case would complete unpaid).
- Errors are escaped with `h()` before `mark_error()`.

## What is not on the drivers yet?
- The 3-D Secure return (`complete()` for the card driver).
- `iyzipay_cancel_payment()` (submit_order.php's frozen blocks call it).

## How was it verified?
- `tests/payment_iyzico_test.php`: a stand-in for iyzico's HTTP client (`\Iyzipay\ApiResource::setHttpClient()`) records the posted JSON, compared with requests built by the gateway code's own statements (copied as `legacy_*()`), plus answers for 3DS start, instalments, protected currency (card refund not sent), fraud, PWI return checks and cancel, refund and cancel. No running shop or iyzico sandbox was used.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-refunds.md, docs/memory/commerce/order-cancellation-config.md, docs/memory/plans/payment-driver-layer.md
