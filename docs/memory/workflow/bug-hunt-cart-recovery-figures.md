---
name: bug-hunt-cart-recovery-figures
description: Bug hunt of the Recovered carts counting side (2026-11-20): the two faults fixed, the three decision issues opened, what was read clean
---

## Which area, and who gave it?
- Architect (2026-11-20, agent channel message 10490): Recovered carts, the counting side: `includes/fn/cart_recovery.php` (the touch row and its writers), `cart_recovery_figures.php`, `cart_recovery_weak.php`, `GET /reports/cart-recovery`, the section on Visitors and the dashboard card `recovery`. Not touched: `submit_order.php`, `includes/fn/order_completion.php`, `includes/fn/ecommerce.php`.
- Memory read: `commerce/cart-recovery-touches.md`, `-figures.md`, `-weak.md`, `-whatsapp-page.md`.

## What was fixed?
- A cart that was not left was recovered: `pg_cart_recovery_outcome()` gave the completion to the last touch with no 6-hour test, so a pop-up taken minutes after the cart was started made an order completed an hour later a recovered cart with its revenue. Now only a left cart (started at least 6 hours before the completion) is recovered (`test_cart_recovery_not_left_is_not_recovered`).
- With it, the stalled rule of What weakens recovery counts a completed cart as "completed after the 7 days" only when its last touch before the completion was more than 7 days earlier (`pg_cart_recovery_weak_carts()` key `late`, `test_cart_recovery_weak_carts_completed_late`).
- A deleted order was a cart with no address: the no-touch row of a completed order outlives the order, and the unreachable rule read the missing order as "no e-mail address". Now `pg_cart_recovery_weak_untouched_fact()` gives a deleted order's address as null and the rule leaves it out (`test_cart_recovery_weak_unreachable_leaves_out_a_deleted_order`).

## Which issues were opened (decisions, to the architect)?
- #10700: a cancelled or refunded order stays in the recovered count and revenue; the Customers report leaves them out.
- #10701: a WhatsApp key is a touch when queued, not when sent; a failed message can take the recovery from the e-mail.
- #10702: a pop-up taken on a fresh cart counts the cart as left and reached.

## What was read clean?
- The 6-hour and 7-day edges (`>=` 21600 s, `<=` 604800 s, the open-cart bound `now - 21600 + 1`), the same-second tie, a touch after the completion.
- One cart through two channels: reached, returned and recovered once per cart, once per channel and per message; one recovered row per order (the completion's lock in `pg_order_complete()` runs it once).
- Minor units: `round()` on the total, `/ 100` only for display; `api_money()` passes minor units.
- Time zone: the API window, the Visitors period and the dashboard windows are whole site days in the site's zone; the API and the screen call the same `pg_cart_recovery_period()`.
- The five rules' shares and minimums match `cart-recovery-weak.md`; no float edge at 30 %, 20 %, 50 % or 5 % up to 100,000 carts.
- A completed order's session cart: `init.php` drops it before `page_view.php` can write a pop-up touch.
