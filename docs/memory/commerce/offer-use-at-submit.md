---
name: offer-use-at-submit
description: The checkout's submit asks again whether the offers an order uses still hold (dates, usage limit, code), under a lock per limited offer and single-use key code (pg_order_offer_use_check(), PR #11414)
module: ecommerce
---

## What does the submit check?
- `submit_order()` calls `pg_order_offer_use_check()` just before the block that sends the shopper back on an error or notice, so before the payment. Not on a payment's return (`pg_payment_is_return_mode()`): the payment may already be taken.
- The offers it asks about are the ones the order carries: `orders.discount_offer_id`, `order_items.offer_id`, `ship_tos.offer_id` (`pg_order_used_offers()`), the same marks `pg_offer_usage_counts()` counts.
- An offer no longer holds (`pg_offer_use_refused()`, pure) when it is not running today (disabled, deleted, before its start, after its end), its customer conditions fail (`pg_offer_customer_conditions_met()`: usage limit, new customer, contact group), or it needs a code the order's special offer code no longer names (a single-use key code used by another order, a key code past its expiry).
- Then `apply_offers_to_cart()` takes it off and the shopper is sent back with the notice "An offer on your order has ended or reached its usage limit, so it was removed and the total has changed…".

## Why?
- The cart worked the discount out when it was last viewed, and `submit_order()` charged that discount and those line prices without asking again. Two checkouts with the last use of a code (in total or per customer) both completed; a cart opened before the end date was completed with the discount after it; a single-use key code went into two orders. Found by the coupons hunt (#11414).

## What is the lock?
- `pg_offer_use_lock_names()` (pure): `pg_offer_use_<id>` for each offer with a usage limit, `pg_key_code_use_<id>` for a single-use key code, sorted. `GET_LOCK(name, 10)`, held until the request ends, so through the payment and `pg_order_complete()`.
- A lock not got in ten seconds: the notice "Another order is using the same offer right now…" and nothing is charged.
- Not covered: a payment that completes on a later request (3-D Secure, a bank's page, a payment callback). The lock ends with the first request; the order is checked there, but two such checkouts can still both complete.
- On MySQL older than 5.7 a connection holds one named lock, so with two limited offers only the last one is held.

## What about a gift whose product is deleted?
- An offer's gift line (`added_by_offer = 1`) whose product row is gone is taken off the cart by `apply_offers_to_cart()` (`pg_offer_gift_line_product_gone()`). Before, it stayed at price 0 with no product, while a line an offer discounted was already removed when its product went.

Related: docs/memory/commerce/order-completion.md, docs/memory/commerce/offer-gift-lines.md, docs/memory/workflow/bug-hunt-areas-coupons-discounts.md
