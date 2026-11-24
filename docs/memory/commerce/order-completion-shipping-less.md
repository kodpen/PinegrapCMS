---
name: order-completion-shipping-less
description: The recipient that gives up the shipping's minor units is lowered by a compare-and-set in the checkout and on a payment callback, so a return and a message lower it once
module: order_completion
---

## What is the shipping step on a payment callback?
- `pg_order_charges_reachable()` can charge the shipping a few minor units lower than the ship_tos rows add up to. The order keeps `orders.shipping`; one recipient gives the difference up so the rows match it. `submit_order()` does it for its own request; `pg_order_complete_from_payment()` does it before `pg_order_complete()`.

## Why a compare-and-set?
- Two payment callbacks of one order (a redirect and a server notice) both pass the `status = 'incomplete'` check, and the step ran before `pg_order_complete()`'s lock with a relative `shipping_cost = shipping_cost - ?`: both lowered the recipient, so the rows came out under the order's shipping.
- Now one SELECT reads the rows; `pg_order_shipping_less_step()` (pure) picks the newest recipient that covers the difference and its cost before and after; the UPDATE sets the new cost `WHERE id = ? AND order_id = ? AND shipping_cost = <cost read>`. The second callback either reads the lowered sum (nothing to do) or its write matches no row.
- `submit_order.php` does the same since #11472: it reads the recipient's id and cost together and writes the new cost under the same condition. Its relative `shipping_cost - ?` ran after the driver's `complete()` call, so a Stripe message completing the order during that call made the visitor's return lower the recipient a second time.

## Tests
- `tests/order_completion_test.php`: `test_order_completion_shipping_less_step` (the pick, and the UPDATE's shape in the function's source), `test_order_completion_shipping_less_checkout` (the checkout's UPDATE). Not run against two live callbacks.
