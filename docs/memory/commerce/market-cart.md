---
name: market-cart
description: The cart in its market's currency (MK6): the order row's market, currency and rate; lines, offers, shipping, donations converted; cart pages printed as they are; the rate sealed at submit
module: market_cart
---

## Which order is in a market?
- An incomplete order follows the visitor's market through `pg_market_cart_sync()` (`includes/fn/market_cart.php`), once a request, from `add_order_item()`, `update_order_item_prices()`, a typed donation and the cart pages. It writes `orders.market_id`, `charged_currency` ('' for a market in the base currency) and `charged_rate` (`1 / currencies.exchange_rate`).
- On a change of market or currency it works the cart out again: `update_order_item_prices()`, every complete recipient's `update_shipping_cost_for_ship_to()`, then `apply_offers_to_cart()`.
- The row is the record: every conversion reads `pg_market_cart($order_id)` (the order's market, its rule, the currency's rate), never the visitor, so a payment's return or callback works the order as the cart did. A site without a market never writes the row.

## How is each amount converted?
- A product's list price: `pg_market_cart_price()` = the product's fixed price in the market when it has one (`market-prices.md`), else `pg_market_price_minor()` (rate, then the market's rule), so a line costs what the catalog printed (`market-site-prices.md`).
- At the rate only, `round()` half away from zero, never the rule: shipping rates (the total of each of the five formula copies, `shipping-rate-paths.md`, and `order_items.shipping`), a ladder's rungs (`pg_offer_tiers()`), a member's agreed price (`pg_market_cart_member_prices()`).
- Offers: `pg_market_cart_offer_row()` on every offer read of the cart; `product_price`, `add_product_price` as list prices, `discount_*_amount`, `required_subtotal`, `upsell_trigger_subtotal` at the rate.
- A shipping band compares the recipient's subtotal back in base (`pg_market_cart_base()`).
- Tax, surcharge and percentages need nothing: they are rates of amounts already in the currency.
- A donation typed in a market cart is kept as typed (`pg_market_cart_typed_minor()`); outside one it is divided back into base as before.

## How do the cart pages print?
- The classic cart, express order and shipping method pages and the designer cart and express order widgets (`pg_market_cart_drawn()` in `designer.php`) run inside `pg_market_cart_display_begin()`: amounts as they are, the currency's symbol and code (`order-currency-screens.md`).
- `pg_order_display_begin()` also sets a display for an order with `market_id` in the base currency (rate 1, base symbol), so a total is never rounded again by the market's rule.
- The express order script gets rate 1 and the display's symbol while a display is in force.

## When is the rate fixed?
- `submit_order.php` calls `pg_market_cart_seal()` before the payment (not on a payment's return): `charged_rate` from the rate the lines were priced at in that request.

## What is left after this PR (#12208)?
- Gift cards and store credit spent at the order's rate and a contact group's minimum order converted. Until then a gift card's base balance is taken as units of the cart's currency.
- Stripe (MK7), PayPal Checkout, PayTR and iyzico (MK8) charge the order's currency, each only in the currencies it takes (`market-payment-methods.md`).
- Product prices shown inside a cart page outside its lines (an upsell's product, a quick-add list) are base figures printed in the cart's currency.

## Tests?
- `tests/market_cart_test.php`: the state, the conversions, an offer row, the ladder and member prices, the row a visitor's market asks for, the wiring.
