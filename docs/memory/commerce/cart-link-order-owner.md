---
name: cart-link-order-owner
description: Whose account an order belongs to when another person opens a saved-cart link (?r=) and completes it; today it stays with the builder (#11435, open decision)
---

## Whose order is a cart completed through its link?
- Today: still the account that built the cart. `retrieve_order()` (`retrieve_order.php`) puts the order in the opener's session and clears its billing fields, but keeps `orders.user_id` and `contact_id`.
- `connect_user_to_order()` refuses an order that already has an account, so a signed-in opener does not take it over.
- Every owner check reads `orders.user_id`: `pg_order_view_owner_matches()`, the order_view widget, My Account order history.

## What does the builder see?
- The order in their order history, and on View Order the opener's billing name, address, phone, e-mail and card type with last digits. The opener's own order history does not list it (#11435).

## Is the behaviour decided?
- Not yet: whether the order moves to the opener (their account when signed in, none for a guest) or stays with the builder (an assistant preparing an order for someone else to pay) is product behaviour, asked in the PR fixing #11435.

Related: commerce/order-reference-code-link.md, commerce/cart-order-creation.md, plans/storefront-api-cart-token.md
