---
name: cart-recovery-weak
description: What weakens cart recovery (CR3): the five rules over a period's left carts, their shares and minimums, where the facts come from, the block in the Recovered carts section
module: cart_recovery_weak
---

## What do the rules find?
- Pure functions in `includes/fn/cart_recovery_weak.php`, tests in `tests/cart_recovery_weak_test.php`; `pg_cart_recovery_weak()` runs them in this order:
  - unreachable: untouched left carts with no contact e-mail address (a no-touch row whose order was deleted is left out: address unknown), or an address but no opt-in when an enabled `order_abandoned` profile is not transactional; over 30 % of the left carts, at least 10 left.
  - stalled: carts that came back (clicked or restored) and were not recovered, over half of at least 5 that came back; split into still open (the order's own `status = 'incomplete'`) and completed more than 7 days after the last touch before the completion; the furthest checkout step the open ones reached, the most common named.
  - late: the first message more than a day after the cart was started, over half of at least 10 carts. Only open carts count: the completion rewrites `orders.order_date`.
  - stock: left carts not recovered (open ones) holding an active line of a tracked product with no stock and no back orders; over 20 % of at least 10 left.
  - message: sent at least 20 times and under 5 % of the carts it reached came back; the three most sent.
- The touched carts come from the figures' `pg_cart_recovery_touch_rows()` (`cart-recovery-figures.md`), so a cancelled order is not recovered here either and an open cart under 6 hours is left out.
- Thresholds are constants at the top of the file. Nothing is stored; worked out on each render.

## Which checkout step?
- No step is recorded, so it is read from the order: `checkout_page_id > 0` payment (sent to the payment service), a `billing_email_address` billing, a `ship_tos` row with `address_1` shipping, else the cart (`pg_cart_recovery_weak_step()`).

## Where is it shown?
- A "What weakens recovery" block in the Recovered carts section of the Visitors overview (`cart-recovery-figures.md`), under the tiles, one alert line per finding (the alerts section's `pg-visitors-alert` look, no link); nothing when no rule fires. Not on the dashboard card or the API.
