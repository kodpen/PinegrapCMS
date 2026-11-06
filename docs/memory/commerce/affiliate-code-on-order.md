---
name: affiliate-code-on-order
description: Which affiliate code an order is credited to: the visitor's session/cookie code, else the code the cart stored on the order; pg_order_submit_affiliate_code()
module: ecommerce
---

## Where does an order's affiliate code come from?
- `?a=` on a page: `init_tracking()` stores it in the session and the `software[affiliate_code]` cookie (marketing consent), only while visitor tracking is on.
- An approved affiliate's code typed as an offer code in the cart or express order: written to `orders.affiliate_code`; also to the session and cookie when visitor tracking is on.
- The cart row is opened with `get_affiliate_code()` (session, then cookie).

## Which code does the submit credit?
- `submit_order.php` writes `pg_order_submit_affiliate_code(get_affiliate_code(), orders.affiliate_code)`: the visitor's code wins (last click), else the code already on the order.
- Before this, the submit wrote `get_affiliate_code()` alone, so with visitor tracking off a code typed in the cart was wiped and no commission was written on an on-site payment, while the off-site completion (`pg_order_complete_from_payment()`) credited it from the order.
- The rate and approval are checked by `pg_order_affiliate_rate()`; an unknown or unapproved code earns nothing.

Related: docs/memory/workflow/decisions-made-product-15.md (commission voided on cancel)
