---
name: cart-link-order-owner
description: Whose account an order belongs to when a saved-cart link (?r=) is opened by someone else: whoever completes it (pg_cart_link_take_over(), PR #11463, #11435)
module: cart_link_owner
---

## Whose order is a cart opened through its link?
- Whoever completes it. Owner (2026-11-24): a signed-in opener takes it over, a guest opener leaves it with no account; the builder no longer lists it or sees the buyer's billing details. Recorded in decisions part 109.
- `pg_cart_link_take_over()` (`includes/fn/cart_link_owner.php`) writes `orders.user_id` / `contact_id`; `pg_cart_link_owner()` is the pure rule. It runs in `retrieve_order()` (`retrieve_order.php`, cart / quote / express order pages) and in `_eo_restore_cart_from_reference_code()` (designer cart and express order widgets), only when the order was not already in the session.

## What stays unchanged?
- The same account opening the link on another device: nothing is written.
- A ghosting staff member: the order is not moved, as `initialize_order()` does not rebind it either.
- A builder who completes their own cart after someone else opened the link: the checkout's `initialize_order()` rebinds a session order to the signed-in visitor, so it lands on the builder's account.
- A guest order is bound at a later sign-in by `connect_user_to_order()` as before.

## What was wrong before?
- `retrieve_order()` cleared the billing fields but kept `user_id` / `contact_id`, and `connect_user_to_order()` refuses an order that has an account. The order stayed in the builder's order history, and their View Order showed the opener's billing name, address, phone, e-mail and card type with last digits (#11435). The designer widgets left a guest opener's order on the builder too.

Test: `tests/cart_link_owner_test.php`. Related: commerce/order-reference-code-link.md, commerce/cart-order-creation.md, plans/storefront-api-cart-token.md
