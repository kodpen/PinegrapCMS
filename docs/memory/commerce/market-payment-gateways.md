---
name: market-payment-gateways
description: How PayPal Checkout, PayTR and iyzico charge and refund a market's order in its own currency (MK8): the order settings, the webhook amount, iyzico's protected currency left out of such an order
module: payments
---

## In which currency do they charge an order?
- In the one its amounts are kept in (`pg_order_amount_currency()`), as Stripe does (`market-payment-methods.md`). Each driver has a pure `pg_payment_<key>_order_settings($settings, $order)` that sets `currency`; nothing is converted, the order's minor units are sent as they are.
- PayPal: called first in authorize, complete, the capture's answer, refund and cancel. The capture is checked against the order's currency; a lira order is still refused ("PayPal does not take payments in this currency."), a euro market of a lira store is not.
- PayTR: called in authorize (the get-token `currency`, TRY as `TL`). A refund names no currency: PayTR gives it back in the payment's own.
- iyzico card and Pay With Iyzico: called in authorize, complete (the 3-D Secure return, the PWI return) and the card's refund.

## What does the PayPal webhook report now?
- `notify()` gives the capture's amount in whatever currency PayPal names, with the currency in `raw.currency`; the callback completes an order only when that is the order's own currency (`payment-callback.md`). Before, an amount in another currency than the base was dropped.

## What happens to iyzico's protected currency?
- For a market's order (charged_currency not the base) `pg_payment_iyzico_order_settings()` clears `protected_currency` and `protected_rate`: the order is charged in its own currency unconverted, and its card refund is sent like any other.
- A base order keeps today's behaviour: converted at today's rate into the protected currency, its card refund refused (`payment-driver-iyzico.md`). A store that wants the protected currency's refunds gives that currency's buyers a market.

## Tests?
- `tests/payment_gateways_order_currency_test.php`.
