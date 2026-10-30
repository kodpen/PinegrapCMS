---
name: subscriptions-later-start
description: Recurring lines that start after the order day on the driver gateways (PR #3378, decided in #3310): their row on today's card, the first charge on the start date, the nothing-today order refused
module: subscriptions
---

## Which lines get a subscription row?
- On a driver gateway in store mode (iyzico with stored-card renewals on, Stripe PaymentIntents) every recurring, priced line of the paid order, whatever its start date (`pg_subscription_checkout_lines()`). The card is registered whenever the cart has a priced recurring line, so today's payment stores it for the later lines too.
- A later line's row: `active`, the card of today's payment, amount as for any row (line price × quantity + its tax), `next_charge_at` = the first second of `order_items.recurring_start_date` (`pg_subscription_later_start_at()`; 0 for no date, today or earlier).

## Who makes the first charge?
- The renewal job, on the start date, as any due row (`subscriptions-renewal.md`). The series then runs from the start date, not the order day: `pg_subscription_renewal_plan()` takes the first order's line (`recurring_start_date`, read by `pg_subscription_renewal_first_order()`) and anchors on it when it is after `created_at`.

## Which order is refused?
- One that charges nothing today and whose priced recurring lines all start later, on those drivers only: no payment, no stored card (`pg_subscription_checkout_starts_later_only()`). `submit_order.php` marks the payment method with "Nothing is charged today, so your card cannot be saved for the subscription. Please choose today as the start date." among its payment-method checks, before any payment call.
- A recurring line starting today, or any amount charged today, lets it through.

## What stays unchanged?
- Legacy gateways (classic `Stripe` Charges too) and their profiles, iyzico with the switch off, existing orders, express-order totals. A card-only Stripe SetupIntent checkout (option b of #3310) may follow as its own slice.

## Tested?
- `tests/subscriptions_test.php` (start date, mixed order rows, refusal on store mode only), `tests/subscriptions_renewal_test.php` (series from the start date). No live gateway run.

Related: commerce/subscriptions.md, commerce/subscriptions-iyzico.md, commerce/subscriptions-stripe.md, plans/subscriptions-iyzico-stripe.md
