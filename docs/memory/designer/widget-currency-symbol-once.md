---
name: widget-currency-symbol-once
description: System widgets print the stored currency symbol (&#8378;) once: raw in markup, decoded before a token map, list row or h() escapes it (pg_visitor_money_text()); spots fixed, tests
module: widgets_order_form, widgets_catalog, widgets_lists_cart, widgets_lists_checkout, price_history
---

## What does a widget get?
- `VISITOR_CURRENCY_SYMBOL` (`currencies.symbol`) is HTML: `&#8378;` for TRY, `&euro;`, `&pound;` ([[currency-symbol-as-text]]). `pg_visitor_money()`, `pg_format_money()`, `prepare_price_for_output(…, 'html')` and `_pg_format_price_with_discount()` return markup with it.

## Where may that markup go?
- Straight into the page: a key of `pg_sw_raw_token_keys()` (`__price_formatted`, `__price_block_html`, `__price_range`) or a map's `raw_extra` key (`__result_price`, the wishlist's `__original_price_formatted`), the express order's `^^__cart_*^^` and `^^__currency_symbol^^`, the cart widget's `__cart_*` and `__item_*` tokens.
- Never into a value escaped afterwards: any other key of a map `pg_sw_escape_token_values()` escapes, a list row (the finish pass escapes every row value, `widgets_lists.php`), `h()`. The entity is escaped a second time and the visitor reads `&#8378;12,50`.

## How is a value escaped later written?
- Money: `pg_visitor_money_text($base_amount, $symbol)` (`includes/fn/ecommerce.php`), the text twin of `pg_visitor_money()`; `pg_money_text()` for an amount already in the shown currency (the entity fields).
- A list row built from the renderer's money: `html_entity_decode($money, ENT_QUOTES | ENT_HTML5, 'UTF-8')` in the row builder.
- The symbol alone: decoded, then `h()`.
- Decoded first, a symbol typed as markup on the currency screen shows as text; it never runs.
