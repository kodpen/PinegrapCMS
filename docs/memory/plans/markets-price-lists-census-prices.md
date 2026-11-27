---
name: markets-price-lists-census-prices
description: Markets and price lists MK1 census, prices: where the currencies are set up, where a price is converted for the visitor, and where each kind of price is stored and read
---

## Where are the currencies set up?
- `currencies` (`data/backups/english_default/sql.sql:25-37`): `code` varchar(3), `symbol`, `exchange_rate` decimal(10,5) (visitor units for one base unit), `base`. Panel rules: `commerce/currency-form-rules.md`.
- `init.php:1140-1179`: with `ECOMMERCE_MULTICURRENCY` on and a session currency that is not base, `VISITOR_CURRENCY_*` come from that row (1165-1170), else from base (1173-1178). The choice is saved by `update_currency.php:25-56`.
- A currency in the session makes the page personal, so it is never served from the kept copy (`includes/fn/page_cache.php:141`).
- The ERP's dated rates are apart: `currency_rates` (`includes/migrations/2026.4.4.php:2773`, DECIMAL(18,8) base units for one unit, a row a day); the store does not read them.

## Where is a price converted?
- `get_currency_amount()` `includes/fn/forms.php:4619`; `pg_visitor_amount()` `includes/fn/ecommerce.php:9050`; `pg_visitor_money()` 9067; `prepare_price_for_output()` 9259 (264 calls in 28 files). Rate read directly: `cart_action.php:146-147, 611-612`, `get_catalog_detail.php:740, 1296`, `get_express_order.php:515`.
- A donation typed in the visitor's currency is divided back into base: `express_order.php:272, 431`, `order_form.php:229`, `shopping_cart.php:157, 298`.
- Under a converted total the order preview, receipt and View Order print the base total and "based on our current currency exchange rate", at the viewer's rate of today: `get_order_receipt.php:1417-1428`, `get_order_preview.php:1591`, `get_view_order_screen_content.php:1581`.

## Where is each kind of price stored and read?
- Product: `products.price` INT, `compare_at_price` INT NULL (`2026.11.0.php:317, 344`); into the cart by `add_order_item()` `includes/fn/ecommerce.php:100` (210), refreshed by `update_order_item_prices()` 2101.
- Variant: a product of its own with its own `price`; no variant table (`commerce/products-variant-model.md`).
- Bundle: `bundle_pricing` fixed or sum, `bundle_discount_bp` (`2026.11.0.php:3322-3326`); `pg_bundle_price()` `includes/fn/bundles.php:212`.
- Subscription: `subscriptions.amount` INT and `currency` CHAR(3) (`2026.11.0.php:3845`), written with `BASE_CURRENCY_CODE` (`includes/fn/subscriptions.php:729-730`).
- Shipping: `shipping_methods.base_rate` to `base_rate_4`, weight and item rates, zone rates; `get_shipping_method_base_rate()` `shipping.php:1545`. A carrier quote in another currency is divided by today's rate (`includes/fn/shipping_label_rates.php:126`).
- Discount: `offer_actions` amounts, `offer_rules.required_subtotal`; `apply_offers_to_cart()` `includes/fn/ecommerce.php:6282`.
- Gift card, store credit: `gift_cards.amount`, `balance`, no currency; `pg_credit_checkout_plan()` `includes/fn/store_credit.php:591`.
