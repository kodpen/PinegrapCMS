---
name: layout-type-converter-cart
description: A shopping cart page's layout file into the Cart widget (LE9a, #11466): two tables as one list, row and layout flags, blocks printed whole, the type row's seed and state
module: layout_convert
---

## What does the cart's map do?
- `_pg_lc_cart_map()` (`includes/fn/layout_convert.php`) via `pg_layout_piece_map('shopping cart')` ([[layout-type-converter]]). The layout's `<form>` tags go (`forms: strip`): the widget's empty cart form is named by each control's `form=`.
- `$recipients` loop: `keep` (read once). Each `$recipient['items']` loop is the widget's list (`lists`): today's and the recurring table are two places of one list; `pg_cart_extra_loops()` takes the second out and the row loop draws it with the first.

## What does each piece become?
- Row values: the cart loop's `__item_*` tokens (`amount_info` is `__item_total`, `payment_period` `__item_recurring_period`).
- Totals: `__cart_subtotal`, `__cart_discount`, `__cart_total`; words: `__shopping_cart_label`, `__update_button_label`, `__checkout_button_label`, `__retrieve_order_url`; `__currency_symbol`, `__currency_code`.
- Printed whole by the widget (if value `token`): a row's gift card, form and schedule fields, the tax words, the offer code box (`has_special_offer_code`), offline payment, the currency form. Sections: `pending_offers`, `quick_add`, `applied_offers`, `payment_periods` (`pg_cart_payment_periods_html()`).
- Controls: `quantity[…]` and `donations[…]` bound to `cart_qty` (a donation row's box becomes its amount box), `submit_update` and `submit_checkout` bound to the actions `cart_update` / `cart_checkout`; `_apply_shopping_cart_bindings()` wires a semantic `<button>` as it wires a button component.
- `!$recipients`: its words the empty message; the rest kept. A tree without `has_items` shows the widget's empty state and quick add box.
- A layout's `<script>` is kept as a raw block (`scripts: keep`): the English text's classic cross-sell loads as on the classic page (frontend.js is on every page).

## Which flags?
- Rows (`pg_cart_row_flags()`): `cart_item_today` (not charged later), `cart_item_recurring`, `cart_item_recurring_only`, donation, offer, quantity and amount flags per table, image, out of stock, calendar event. A subscription starting today stands in both tables, edited in the first.
- Around (`pg_cart_layout_flags()`): `has_today_items`, `has_today_non_donations`, `has_recurring_products`, `has_recurring_non_donations`, `has_discount`, `has_special_offer_code`, `has_currency_code`.
- `pg_cart_row_visibility()` resolves a row's own flags; the surround's pass resolves the rest.

## Where do the page's settings come from?
- `_pg_widget_type_facts()` reads `shopping_cart_pages`. `pg_layout_convert_state('shopping cart')`: `full_description` (the description branch; the other is `unless`), `multi_recipient` (the site's recipient mode). On a multi-recipient site the ship-to heading per recipient is dropped (`recipients`), so the page converts only after the designer's confirm.
- `pg_layout_widget_seed('shopping cart')`: the button and cart words when set, the next pages, the quick add group and its label, the offer code label and message (empty hides the box, as on the classic page). Before, a moved cart page had none of them.

## Known differences
- An only-recurring cart shows today's table empty; a row's picture keeps the description in its half column. The offer code box, the gift card, form and schedule fields are the widget's markup. The cart's own total (`__cart_total`) holds its tax estimate.

## Starting sites and tests?
- 3 file pages, 3 texts (en 248, tr 248, tr 497): all convert with nothing dropped. The stock template drops two sentences with the page's words.
- `tests/layout_convert_test.php`, `tests/cart_layout_test.php`.
