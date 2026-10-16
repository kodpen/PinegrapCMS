---
name: cart-order-creation
description: When the visitor's order row is created (first real cart write, never a page view), and why the designer cart and express order widgets create none on view.
---

## When is a visitor's order row created?
- `initialize_order()` (`includes/fn/ecommerce.php`) inserts an `incomplete` row into `orders` when the session has no `$_SESSION['ecommerce']['order_id']`; with one it only rebinds `user_id` / `contact_id` to a visitor who signed in since.
- Only real writes call it: `cart_action.php` (designer cart forms), `add_to_cart.php`, `catalog_detail.php`, `order_form.php`, `shopping_cart.php`, `express_order.php`, `shipping_method.php`, `reserve_calendar_event.php` and the other checkout POST handlers. The legacy cart and express order views (`get_shopping_cart.php`, `get_express_order.php`) never call it.

## What do the designer cart and express order widgets do?
- `_render_system_widget_shopping_cart()` and `_render_system_widget_express_order()` (`includes/fn/widgets_cart.php`) call `initialize_order()` only when the session already holds an order: `!empty($_SESSION['ecommerce']['order_id'])`.
- The saved-cart link (`?r=CODE`, `_eo_restore_cart_from_reference_code()`) runs first, so a restored cart is the order brought up to date.
- Without an order both widgets show their empty cart (the cart's empty message, the express order's "Your cart is empty"); every order query in them is guarded by `$order_id > 0`. Pricing, offers and stock checks still run, as on the legacy cart.

## What was the symptom before PR #489?
- Each render called `initialize_order()` unconditionally, so every open of a designer cart or checkout page (/sepet, /odeme) without a session order wrote an empty `incomplete` order: no items, no buyer. Bots and other cookieless clients get a new session per request, so each of their hits left one row (dev.pinegrap.com orders 430-437, 2026-10-16). Signed-in visitors with an empty cart got one too, with their `user_id`.
- Rows already written stay; the fix deletes nothing.

Test: `tests/cart_widget_no_order_test.php`. Related: docs/memory/commerce/order-reference-code-link.md, docs/memory/commerce/cart-express-order-warnings.md
