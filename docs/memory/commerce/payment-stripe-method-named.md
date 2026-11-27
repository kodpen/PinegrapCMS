---
name: payment-stripe-method-named
description: The Stripe method that paid (iDEAL, Klarna, SEPA, BLIK, Link) kept in orders.card_type; View Order, the order mail and API payment.paid_with show it
module: payments
---

## Where is the method kept?
- In `orders.card_type` (varchar 50), the place a card's brand is kept. `orders.payment_method` stays `Credit/Debit Card`: no column, no new value of the method list (plan rule, `payment-driver-layer-decisions.md`).
- `pg_payment_stripe_card_details($intent)` reads the intent's expanded `payment_method.type`: a listed method gives `card_brand` = its label from `pg_payment_stripe_methods()` (untranslated service name), `link` gives `Link`, and the card fields stay empty. A card keeps brand, last four digits and expiry as before.
- `submit_order.php` writes `card_brand` into `card_type` for every hosted card driver, so authorize, the redirect return and BLIK's waiting page all store it. SEPA's IBAN digits are not stored as a card number.
- Pure helpers: `pg_payment_stripe_paid_method_name($payment_method)` and `pg_payment_stripe_paid_method_names()` (the list of names it can answer).

## How do the screens tell a method from a brand?
- `pg_order_paid_with_method($payment_method, $card_type)` (`includes/fn/payments.php`): true only for a card order whose `card_type` equals one of the Stripe driver's names exactly; it loads the driver with `pg_payment_load('stripe')`. A brand (Visa, ...) never matches.
- View Order, Payment card: the grey line under the method says "Paid with iDEAL", the tile shows a wallet, and no card number, expiry, Cardholder or Verification Number row is drawn.
- The order mail (`get_order_receipt_in_plain_text.php`): "Paid with: iDEAL" instead of Card Type and Card Number.
- API order (every answer that carries an order): `payment.paid_with` = `card_type` for a Credit/Debit Card order (brand or method), `''` otherwise; `api_order_select()` reads `orders.card_type`.

## What still says Card Type?
- The order receipt page, the account's order page, Print Order, the orders list filter and the order report still label the value "Card Type"; the value itself is the method's name.

## How is it verified?
- `tests/payment_stripe_methods_test.php` (names, card details, the return's answer, `pg_order_paid_with_method()`), `tests/api_order_present_test.php` (`paid_with`).
- No Stripe call: there were no test keys; the first live try is the owner's.

Related: commerce/payment-stripe-method-list.md, commerce/payment-stripe-later-result.md, commerce/payment-stripe-blik-deferred.md
