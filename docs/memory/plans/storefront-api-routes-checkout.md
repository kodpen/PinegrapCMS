---
name: storefront-api-routes-checkout
description: Storefront API design note: checkout routes (create from cart, totals, payment intent through the payment driver layer, confirm), the totals functions they call, guest checkout
---

## Which checkout routes?
| Id | Method, path | Scope | Idem. | Dry-run |
|---|---|---|---|---|
| `storefront.checkout.create` | POST /storefront/checkout | storefront:checkout | yes | yes |
| `storefront.checkout.get` | GET /storefront/checkout | storefront:checkout | - | - |
| `storefront.checkout.update` | POST /storefront/checkout/update (also PATCH) | storefront:checkout | yes | yes |
| `storefront.payment_methods` | GET /storefront/checkout/payment-methods | storefront:read | - | - |
| `storefront.checkout.payment_intent` | POST /storefront/checkout/payment-intent | storefront:checkout | required | no |
| `storefront.checkout.confirm` | POST /storefront/checkout/confirm | storefront:checkout | required | yes |

- One checkout per cart: the checkout is the cart's `incomplete` order plus billing, recipient addresses, e-mail, legal acceptances; `create` from a cart that has one returns it (200, not 201).
- `create`/`update` take `email` (required for a guest), billing and shipping address, phone, the legal checkbox ids (`commerce/checkout-legal-checkboxes.md`), the KVKK notice acceptance (`checkout-kvkk-notice.md`), a note. Address checks are the express order's (`express-order-tax-address.md`): the tax pass runs on the given address.

## Which code makes the totals?
- The same chain as the express order widget, never a copy: `update_order_item_prices()` → `apply_offers_to_cart()` → the tax pass on the checkout address → `pg_express_order_charged_totals()` (`includes/fn/widgets_cart.php`, `$settle_rates = true`), with `pg_order_line_charged_today()`, `pg_order_charged_discount()`, `pg_percent_of()` inside it.
- The totals block: `subtotal`, `discount`, `tax`, `shipping`, `gift_card`, `surcharge`, `total`, `total_today`, currency, in minor units (`api_money()`). `get_express_order.php` and `submit_order()` keep their own copies (frozen, `payment-driver-layer-decisions.md`); a test in the checkout slice pins the API's totals equal to the widget's on the fixtures of `tests/express_order_totals_test.php`.

## How does payment work?
- `payment-methods` lists `pg_payment_checkout_methods()` filtered to what the API can drive: a driver method with `prepare` or `authorize` that `pg_payment_can()` confirms, offline payment where the site allows it, no legacy gateway (they take raw card fields in `submit_order.php`; the API never accepts a PAN).
- `payment-intent` calls `pg_payment_call($key, 'prepare', …)` for the confirmed total and answers what the driver gave: a hosted-fields client secret or token, or a `redirect` URL. The driver `state` that the website keeps in `ecommerce.payment_state.<key>` is kept on the order (sealed), since no session survives between calls.
- `confirm` takes `total` (the shopper's agreed figure) and the payment token or `payment_intent_id`; `submit_order()` refuses a higher figure than posted, the API answers 409 `total_changed` with the new totals. A 3-D Secure or redirect step answers 202 with `next_action.url`; the driver's return and webhook complete the order through `pg_order_complete_from_payment()` exactly once (`order-completion-from-payment.md`).
- Placing the order runs `submit_order()`'s checks and `pg_order_complete()`; the `$_POST` reads in `submit_order()` (`payment_token`, `threedsecure`, `sms_code`) become an input array the page fills from `$_POST` (split in the checkout slice).

## What does confirm answer?
- 201 with the `StorefrontOrder` (number, status, totals, the guest's order status link of `guest-shipment-lookup.md`); `order.created` fires as from the website.

## Guest checkout?
- Yes, with a cart token and `email`; auto-registration follows the site's checkout setting (`order-auto-registration-set-password-link.md`) as on the website.

Related: plans/storefront-api-routes-cart.md, commerce/payment-drivers.md
