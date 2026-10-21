---
name: order-discount-charged-today
description: Order discount vs recurring lines not charged today: the cap at today's subtotal (pg_order_charged_discount), which lines are charged, why the cap sits in apply_offers_to_cart()
---

## Which cart lines are charged when the order is placed?
- `pg_order_line_charged_today()`, the test `submit_order()` puts its lines through: every line but a recurring one that starts after today, and every recurring line on the ClearCommerce gateway (it charges recurring payments itself). `submit_order()`'s `$subtotal` holds only these.
- A recurring line's `order_items.recurring_start_date` is written when the order is submitted (`express_order.php` after its `apply_offers_to_cart()`, `shopping_cart.php` on update); until then it is '0000-00-00' and the line counts as starting `products.start` days after today, the date it will be given.

## How much of the order discount is taken today?
- The cart works the order discount out over every line (`get_order_subtotal()`, recurring lines included). `pg_order_charged_discount($discount, $subtotal)` caps it at the subtotal charged today, never below 0.
- The cap is applied at the source, at the end of `apply_offers_to_cart()` against `pg_order_subtotal_charged_today()`, so the session's `order_discount` read by the cart, the frozen express order and order preview pages and `submit_order()` is the same number. A discount that comes to 0 is removed and the order's `discount_offer_id` cleared, so it uses up no offer.
- `submit_order()` caps it again against its own `$subtotal` and divides by the subtotal (tax share, affiliate commission) only when it is above 0.

## Why not only cap in submit_order()?
- The express order page posts the total it showed and `submit_order()` refuses an order whose total is higher ("the total has changed"). Capping only at submit made the server total higher than the page's (negative) total, and the order could never be placed.

## What was the symptom? (#729, PR #929)
- Only a recurring product that starts later in the cart and any order discount: `$discount / $subtotal` with a 0 subtotal, DivisionByZeroError, every checkout a 500. A one-time line next to it: the discount worked out over the subscription too came off the one-time charge whole, and the order was placed with a negative total (₺10 + ₺100 subscription, 50% off: total −₺45).

## What is not decided?
- Whether an order discount on a mixed cart should be worked out over today's lines only (50% of ₺10 = ₺5) rather than capped (₺10 off the ₺10 line). The cap changes only carts whose discount is larger than today's charge.

Related: docs/memory/commerce/offer-order-discount-ladder.md, docs/memory/commerce/cart-widget-payment-plan-and-gates.md
