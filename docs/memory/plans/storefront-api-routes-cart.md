---
name: storefront-api-routes-cart
description: Storefront API design note: the cart routes (create, read, add/update/remove line, coupon, shipping choice), their scopes and the cart code each one calls instead of copying it
---

## Which cart routes?
| Id | Method, path | Scope | Idem. | Dry-run |
|---|---|---|---|---|
| `storefront.cart.create` | POST /storefront/cart | storefront:cart | yes | no (creates nothing yet) |
| `storefront.cart.get` | GET /storefront/cart | storefront:cart | - | - |
| `storefront.cart.lines.add` | POST /storefront/cart/lines | storefront:cart | yes | yes |
| `storefront.cart.lines.update` | POST /storefront/cart/lines/{id} (also PATCH) | storefront:cart | yes | yes |
| `storefront.cart.lines.delete_post` | POST /storefront/cart/lines/{id}/delete (also DELETE) | storefront:cart | yes | yes |
| `storefront.cart.coupon` | POST /storefront/cart/coupon | storefront:cart | yes | yes |
| `storefront.cart.coupon.delete_post` | POST /storefront/cart/coupon/delete | storefront:cart | yes | yes |
| `storefront.cart.shipping` | POST /storefront/cart/shipping | storefront:cart | yes | yes |
| `storefront.shipping_methods` | GET /storefront/cart/shipping-methods | storefront:read | - | - |

- `cart.create` answers 201 with the cart and its `cart_token` (once; later reads never show it). With a customer token the customer's open cart is returned instead of a second one (the website's sign-in rebinding, `initialize_order()`).
- Every cart answer is the same `StorefrontCart` object: lines (product id, name, quantity, unit price, line total, tax, `saved_for_later`), offers applied, coupon, recipients with their shipping, the totals block (`storefront-api-routes-checkout.md`), `messages[]` (`storefront-api-errors.md`).

## Which existing code does each route call?
- Create: `initialize_order()` (the `incomplete` row, reference code, currency), never on a read (`commerce/cart-order-creation.md`).
- Add: the `add_to_cart` public action's handler path (`pg_panel_add_to_cart()`, `includes/panel/commerce.php`), split so the API calls its check-and-write core with the API's input, not `$_POST`.
- Remove: `pg_cart_remove_line()` (`commerce/cart-line-removal.md`), never a raw DELETE. Update: the cart widget's quantity path with the same stock check (`cart-widget-low-stock.md`).
- Lines read with `pg_order_item_active_sql()`: a saved-for-later line counts nowhere (`commerce/cart-save-for-later.md`).
- Coupon: the offer code path of `cart_action.php` (the cart form), then `apply_offers_to_cart()`.
- Shipping methods: `get_shipping_methods()` (`shipping.php`) for the recipient's address, as the `get_shipping_methods` public action; the choice writes `ship_tos.shipping_cost` the way the express order POST does (`commerce/express-order-widget-totals.md`, #3579).

## How does session-bound cart code run in a stateless call?
- `initialize_order()`, `apply_offers_to_cart()`, `update_order_item_prices()` and `submit_order()` read `$_SESSION['ecommerce']`; `initialize_order()` also reads the `USER_ID` / `USER_CONTACT_ID` constants, which cannot change inside a request. The cart slice adds `pg_storefront_with_cart($shopper, $callback)`: sets `$_SESSION['ecommerce']['order_id']` (and the visitor currency) for the callback and restores the previous value after, because MCP runs handlers inside another request. A function reading the constants gets the shopper's ids as optional arguments (default: the constants), so the website's calls stay the same.
- A function that cannot run so (reads `$_POST` or prints) is split into a core taking an array, the page keeping its wrapper; no second copy of the arithmetic (binding decision, plan file).

## What is not in the cart slice?
- Gift cards, recipient trees with several addresses, calendar reservations and donation amounts: a line or cart that needs them answers 422 `unsupported_cart` naming the product; the website keeps serving them. A later step may open them.

Related: plans/storefront-api-callers.md, plans/storefront-api-routes-checkout.md
