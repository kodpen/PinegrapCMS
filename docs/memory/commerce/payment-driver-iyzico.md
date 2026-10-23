---
name: payment-driver-iyzico
description: iyzico drivers (Iyzipay card, Pay With Iyzico redirect), their submit_order.php branch, the 3-D Secure return in the Iyzipay block, no card refund under a protected currency, tests
---

## Which drivers are there?
- `iyzico` (card, gateway `Iyzipay`) and `pay_with_iyzico` (redirect, method `Pay With Iyzico`), one file, two prefixes, both `'settings' => 'config'`: the Ecommerce card's config columns (keys, mode, 3DS, instalment limit, protected currency, PWI switch); no `_fields()`.
- Card: `authorize` (direct payment or 3-D Secure start), `refund`, `cancel`; no `complete`. PWI: `authorize`, `complete`, `refund`, `cancel`. Capabilities: installments, partial_refund (`threeds` waits for the card's `complete()`).
- Requests carry the gateway code's values: CreatePaymentRequest (instalment price from RetrieveInstallmentInfo at the BIN; protected currency converts every amount at today's rate), PWI initialize (base currency), AmountBaseRefund (view_order.php, fn/returns.php), Cancel (process_order_cancellation(), iyzipay_cancel_payment()). Same visitor sentences and log lines.

## Why is a card refund refused with a protected currency?
- The card is charged in it at the day's rate; AmountBaseRefund reads its price in the payment's currency, so 20.00 TRY would go back as 20.00 USD. `pg_payment_iyzico_card_refund_with()` refuses before sending (#1415): refund in the iyzico panel, then record it on the return (view_return.php, "Record a refund made another way"). The order screen cannot (its "Mark refund as processed" only clears a cancelled order's flag).
- No charged currency on the order: card orders charged in the base currency before the switch was on are refused too, and a USD payment refunded after it is off is sent unconverted. PWI (base currency) still refunds.

## How does submit_order.php use them?
- One branch before the non-recurring charge switch: `pg_payment_checkout_driver(method, ECOMMERCE_PAYMENT_GATEWAY, mode)` non-empty → `authorize` (or `complete` on the driver's return mode) through `pg_payment_call()`; else the switch runs. The answer fills the gateway code's variables (`pg_payment_checkout_totals()`: amount above the total = instalment charge; `card_brand` → `card_type`).
- Return modes keep the old addresses (`pg_payment_return_mode()`): `iyzipay_threedsecure_return`, `pay_with_iyzico_return`; the checks atop submit_order.php and order_preview.php still apply.
- A return whose driver has no `complete()` goes to the gateway code: the frozen Iyzipay block finishes the 3-D Secure return (commerce/payment-iyzico-threeds-return.md).
- The card's instalment charge is lowered where a rate's total would be out of reach (`pg_order_installment_charge()`, #1269, erp/erp-order-charges-vat.md); the 3-D Secure row and the order keep that figure.
- PWI state (token, conversation id, order id, base total): `ecommerce.payment_state.pay_with_iyzico`; a token or order mismatch keeps it (iyzico not asked), any iyzico answer ends it.
- A registered driver that cannot load refuses the payment, never falls back to the gateway code (a card gateway with no legacy case would complete unpaid). Errors go through `h()` before `mark_error()`.

## What is not on the drivers yet?
- The 3-D Secure return (the card's `complete()`) and `iyzipay_cancel_payment()` (frozen submit_order.php blocks).

## How was it verified?
- `tests/payment_iyzico_test.php`: a stand-in for iyzico's HTTP client records the posted JSON, compared with the gateway code's own statements (`legacy_*()`), plus answers for 3DS start, instalments, protected currency (card refund not sent), fraud, PWI return checks and cancel, refund and cancel. No running shop or iyzico sandbox.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-refunds.md, docs/memory/commerce/order-cancellation-config.md, docs/memory/plans/payment-driver-layer.md, docs/memory/commerce/iyzipay-sdk-php71-patch.md (the bundled SDK is patched for PHP 7.1)
