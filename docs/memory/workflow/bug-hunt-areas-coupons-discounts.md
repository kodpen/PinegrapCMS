---
name: bug-hunt-areas-coupons-discounts
description: The bug hunt of coupons and discounts (area 31, 2026-11-24): offer dates, usage limits under two checkouts, a gift of a deleted product; what it fixed, what was read clean
---

## Which area was hunted?
- Area 31, coupons and discounts: offers and their codes, key codes, `apply_offers_to_cart()`, `validate_offer()`, `pg_offer_usage_counts()`, `get_best_offer_id()` in `includes/fn/ecommerce.php`, and the checkout's submit (`submit_order.php`).
- Looked at: start and end dates, the usage limit per code and per customer with two orders at once, a code that stacks where it must not, a discount larger than the basket, a removed product's discount, amounts in minor units.
- Left alone: `import_key_codes.php`, held by the CSV hunt (#11411).

## What did the hunt's PR fix?
- The submit charged the discount the cart worked out when last viewed and never asked again: two checkouts with the last use of a code both completed, a cart opened before an offer's end date was paid with the discount after it, a single-use key code went into two orders. Now `pg_order_offer_use_check()` asks again under a lock (`commerce/offer-use-at-submit.md`).
- A gift line of a deleted product stayed in the cart at price 0 with no product; it is now taken off (same file).

## What was read clean?
- Dates: every offer query compares with MySQL `CURRENT_DATE()`, and `init.php` sets the connection's time zone to PHP's, so the cart and `pg_offer_is_running()` agree at midnight.
- A discount above the basket: the order discount stops at the subtotal (`pg_offer_order_discount_value()`) and at today's charge (`pg_order_charged_discount()`); a product or gift discount stops at zero; percentages are 0 to 100 on the editor and the API.
- Stacking: offers sharing a code with "only apply the best offer" give one winner (`get_best_offer_id()`); one order discount is chosen; an item takes the lowest price of the product offers, not their sum; an order holds one special offer code.
- Minor units: `pg_percent_of()` / `pg_percent_off()`, `(int) round()`; no `(int)` cut on money.
- A discounted line whose product was deleted is removed; a removed line's discount goes with it, since every cart change runs `apply_offers_to_cart()`.

## What was left?
- A payment completed on a later request (3-D Secure, a callback) is checked at the submit but is not under the lock until it completes (`commerce/offer-use-at-submit.md`).
- The per-customer limit of a signed-in customer counts only that account's orders, not guest orders with the same e-mail; it reads as meant (`pg_offer_usage_counts()` comment), so no fault.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-5.md
