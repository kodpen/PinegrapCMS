---
name: layout-type-converter-express-order-render
description: What the Express Order widget gives a converted classic layout (LE9b, #11486): tokens, row values, flags, the type row's state, known differences, starting sites
module: widgets_express_order
---

## Which values?
- Tokens: totals `__cart_*`, `__cart_total_charged`, `__currency_disclaimer`, `__currency_code`, `__currency_selector`, `__shopping_cart_label`, `__update_button_label`, `__purchase_now_button_label`, `__special_offer_code_label/_message` (cfg first, then the `express_order_pages` row), `__retrieve_order_url`, `__opt_in_label`, `__tax_exempt_label`. The layout's `-` before the discount values is removed (`replace`): the express order's values carry it.
- Row values (`_eo_compute_item_tokens()`): `__item_form_data_html`, `__item_gift_card_html`, `__item_recurring_schedule_html` (the parts of `__item_form_html` alone), and `pg_eo_item_layout_values()`: period, out of stock message, calendar event.
- Applied gift cards: the list `checkout.gift_cards` (loop `source:`), `__gift_card_code`, `__gift_card_balance`, `__gift_card_remove_url`.

## Which flags?
- `pg_eo_layout_flags()`: the cart's layout flags plus `has_no_recurring_products`, `has_subtotal_line` (a discount, tax, shipping or gift card line), `has_payment` (total > 0). In the renderer also `has_gift_card_code`, `can_update_contact`, `has_referral_sources`.
- Row flags are the cart's (`pg_cart_row_flags()`): kept by `_eo_apply_visibility_bindings()` and resolved per row; the second table is drawn row by row with the first (`pg_cart_extra_loops()`).

## Where do the page's settings come from?
- `_pg_widget_type_facts()` reads `express_order_pages`. `pg_layout_convert_state('express order')`: `full_description`, `multi_recipient`, `quick_add`, `billing_form`, `po_number`, `custom_field_1/2` with their `_label` (echo `state:`) and `_required`.

## Known differences
- No pending offers (the classic page's gifts with an Add button); no quick add box. Opt-in box shown even to an opted-in contact. Gift card plurals ("Gift Cards", the list as `<ul>`) always plural. Shipping, payment methods and terms are the widget's markup. Recurring totals give the subtotal per period only.

## Starting sites and tests?
- 5 file pages, 5 texts (en 256, 476, 497; tr 256, 476): all convert with nothing dropped. The stock template drops the store credit box, the SMS label and a few words built in PHP.
- `tests/layout_convert_test.php`, `tests/express_order_layout_test.php`.
