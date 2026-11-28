---
name: market-payment-methods
description: A market's order at the checkout (MK7, MK8): Stripe, PayPal, PayTR, iyzico in its own currency; the methods it is offered (order_currency, each driver's list)
module: payments
---

## In which currency does Stripe charge an order?
- In the currency its amounts are kept in: `pg_payment_stripe_order_settings($settings, $order)` sets `currency` to `orders.charged_currency`, else the base one (`pg_order_amount_currency()`). Every `*_with()` of prepare, authorize, BLIK, complete, the intent check, refund and cancel calls it first.
- The amount is the order's as stored (the cart priced it in that currency, `market-cart.md`); nothing is converted. A zero-decimal currency counts whole units (`pg_payment_stripe_amount()`).
- The return refuses an intent in another currency than the order's; the fee is compared with the order's currency.
- The methods beside the card follow the order's currency (`pg_payment_stripe_method_types()`), so the Payment Element and the intent agree.

## Which methods is a market's order offered?
- An order in the base currency: every method, as before.
- Another currency: Offline Payment (nothing is charged on the site) and a method whose driver declares the capability `order_currency` (`pg_payment_method_takes_currency_decide()`, pure; `pg_payment_method_takes_order($method, $order)`). Stripe, PayPal Checkout, PayTR, the iyzico card (gateway Iyzipay) and Pay With Iyzico have it; a legacy gateway's card and PayPal Express Checkout are never offered to such an order.
- A driver may name its currencies in an optional `pg_payment_<key>_currencies()` (`pg_payment_driver_currencies()`, null for none = any); a market's order in another one is not offered it. PayPal: `pg_payment_paypal_currencies()` (no lira); PayTR: TRY, EUR, USD, GBP, RUB; iyzico and Pay With Iyzico: TRY, EUR, USD, GBP, IRR, NOK, RUB, CHF. Stripe names none. How the three charge: `market-payment-gateways.md`.
- Applied in `pg_payment_checkout_methods()` (designer checkout and submit_order.php's allow-list, which refuses a posted method with "The selected payment method is not available."), `pg_payment_checkout_driver_methods()`, and the card, PayPal Express and Pay With Iyzico blocks of `get_order_preview.php` and `get_express_order.php`.
- The visitor's order is read each time (`pg_payment_session_order_currency()`): the cart's market can change it within a request.

## How does a webhook match such an order?
- `payment_callback.php` passes `pg_order_amount_currency($order, BASE_CURRENCY_CODE)` to `pg_payment_notify_outcome()`; `pg_payment_notify_order()` reads `charged_currency` once the column is there. A euro payment completes only a euro order.

## Which switches can the store turn on?
- Site Settings › Commerce, Payment methods card, Stripe: a method is drawn off only when it takes none of the store's currencies (base and switched-on markets, `pg_payment_stripe_store_currencies()`), `payment-stripe-method-list.md`.

## What is not done here?
- On Account and store credit compare the order's total with base limits and balances; converting them at the order's rate is left with gift cards (`market-cart.md`). The fee recorded for the ERP profit is in the order's currency (MK10 for the invoice).
- When only a method not offered to the order is switched on, the classic pages draw an empty payment block.

## Tests?
- `tests/payment_stripe_order_currency_test.php`: the settings, prepare and authorize in euro and yen, the return's check, the refund in yen, the switches for several currencies, the method decision.
- `tests/payment_gateways_order_currency_test.php`: PayPal, PayTR and iyzico (MK8) and the decision by a driver's list.
