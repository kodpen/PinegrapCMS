---
name: payment-driver-iyzico
description: iyzico drivers (Iyzipay card, Pay With Iyzico redirect), their submit_order.php branch, the 3-D Secure return in the card's complete() (PR #1710), no card refund under a protected currency, tests
---

## Which drivers are there?
- `iyzico` (card, gateway `Iyzipay`) and `pay_with_iyzico` (redirect, method `Pay With Iyzico`), one file, two prefixes, both `'settings' => 'config'`: the Ecommerce card's config columns (keys, mode, 3DS, instalment limit, protected currency, PWI switch); no `_fields()`.
- Card: `authorize` (direct payment or 3-D Secure start), `complete` (the 3-D Secure return), `refund`, `cancel`; capabilities installments, partial_refund, threeds. PWI: `authorize`, `complete`, `refund`, `cancel`; installments, partial_refund.
- Requests carry the gateway code's values: CreatePaymentRequest (instalment price from RetrieveInstallmentInfo at the BIN; protected currency converts every amount at today's rate), CreateThreedsPaymentRequest, PWI initialize (base currency), AmountBaseRefund (view_order.php, fn/returns.php), Cancel (process_order_cancellation(), the amount check's void). Same visitor sentences and log lines.

## Why is a card refund refused with a protected currency?
- The card is charged in it at the day's rate; AmountBaseRefund reads its price in the payment's currency, so 20.00 TRY would go back as 20.00 USD. `pg_payment_iyzico_card_refund_with()` refuses before sending (#1415): refund in the iyzico panel, then record it on the return (view_return.php, "Record a refund made another way"). The order screen cannot (its "Mark refund as processed" only clears a cancelled order's flag).
- No charged currency on the order: card orders charged in the base currency before the switch was on are refused too, and a USD payment refunded after it is off is sent unconverted. PWI (base currency) still refunds.

## How does submit_order.php use them?
- `commerce/payment-iyzico-checkout.md`: the driver branch, return modes, state, the removed inline code (PR #2770).

## How was it verified?
- `tests/payment_iyzico_test.php`, `tests/payment_iyzico_threeds_return_test.php`: a stand-in HTTP client records the posted JSON, compared with the gateway code's statements (`legacy_*()`), with canned answers. No shop or iyzico sandbox run.

Related: docs/memory/commerce/payment-iyzico-checkout.md, docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-refunds.md, docs/memory/commerce/order-cancellation-config.md, docs/memory/commerce/payment-driver-layer-decisions.md, docs/memory/commerce/iyzipay-sdk-php71-patch.md (the bundled SDK is patched for PHP 7.1)
