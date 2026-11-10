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
- A list row built from the renderer's money: `html_entity_decode($money, ENT_QUOTES | ENT_HTML5, 'UTF-8')` in the row builder (`pg_sw_list_cart_rows()`, `pg_sw_list_checkout_rows()`, `pg_sw_list_product_related_rows()`, the account list's `__order_total`).
- The symbol alone: decoded, then `h()` (`pg_of_tokens()`).
- Decoded first, a symbol typed as markup on the currency screen shows as text; it never runs.
- A text token also going to `pg_civ_variants.js` under a key it writes as HTML (`HTML_FIELDS`) is escaped for it: `reference_price` = `h()` of `__reference_price`. Its other keys are written as text ([[variant-switch-text-values]]).

## What did #8393 fix?
- All read `&#8378;` on a TRY shop. The order form's `__currency_symbol` (the donation box), was `h(VISITOR_CURRENCY_SYMBOL)`.
- Catalog listing and detail, shown only with an offer, a reference price or a bundle saving: `__original_price_formatted` (product and group cards, detail), `__discount_amount_formatted`, `__reference_price` (`pg_price_reference_tokens()`), `__bundle_saving_formatted` (`_pg_catalog_bundle_values()`).
- Lists: the cart list's `__item_price` / `__item_total` (cart widget, express order, the mini cart in the cart link) and the express order's `__gift_card_amount` / `__gift_card_balance`.

## What holds it?
- `tests/widget_currency_symbol_test.php`: `pg_visitor_money_text()`, the catalog tokens (the five map entries pinned to it), the list rows, a mini cart drawn end to end.
- `tests/order_form_widget_test.php`: `pg_of_tokens()`. `tests/panel_currency_symbol_text_test.php` also refuses `h(defined('…_CURRENCY_SYMBOL') ? …)`.
