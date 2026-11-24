---
name: layout-type-converter-order-form
description: An order form page's layout file into the Order Form widget's design elements (LE7): what each piece becomes, the map options it brought, the new flags and token, the starting-site texts
module: layout_convert
---

## What does the order form's map do?
- `_pg_lc_order_form_map()` in `includes/fn/layout_convert.php`, read through `pg_layout_piece_map('order form')` ([[layout-type-converter]]). The products loop is the widget's list with the layout's own row (no starter row), so the theme's table and cells stay.
- The layout's `<form>` tags go (`forms: strip`): the widget wraps its tree in its own form to order_form.php and appends the currency form after it ([[checkout-currency-picker]]).

## What does each piece become?
- `$messages`, `$system`, `$attributes`, `$currency_attributes`, `$currency_system`, `$product['edit']`: nothing.
- Row values: `$product['id']` `__id`, `name` `__name`, `short_description` `__short_description`, `image_url` `__image_url` (bound as `src`), `price_info` `__price_formatted`, `full_description` `__description` then `__out_of_stock_message` (the classic page adds the out-of-stock words to the description).
- Row conditions: `selection_type` donation, checkbox, quantity: `order_form_donation`, `_checkbox`, `_quantity`; the donation's "otherwise" (the price) is kept; `$product['available']` is kept (every control under it already sits under a flag that needs it).
- `$product['image_url']` (opens and closes the image's row in two blocks): both kept; the wrapper the image alone fills, its column, is shown under the row flag `order_form_has_image` (`media`). A product with no image keeps the description in its half column, where the classic page drew it full width.
- `$product['recurring_schedule']`: the node bound to the section `recurring_schedule`; the widget draws its own schedule fields, so the theme's fieldset and `$start_date`, `$number_of_payments_*` inside it are not read.
- Controls `product_<id>` and `donation_<id>` are renamed `product` and `donation` (`controls`): the widget names and fills them per row. Ids keep `^^__id^^`.
- `$product_layout == 'list'` `is_list_layout` (it may cover the list, `flag_list`), its "otherwise" `is_dropdown_layout`; `$recipient` `has_recipient_choice`; `$quantity` `has_quantity`; `$currency` `has_currency_choice`; `$available_products` `has_products`.
- `$skip_button_url`: `has_skip_button`; after "else" (no product can be ordered) `no_products` then `has_skip_button`. A link to `h($skip_button_url)` becomes a submit button named `submit_skip` with the link's classes (`submits`): order_form.php sends the visitor on.
- Column headings: `only_donations` / `not_only_donations`; `selects_by_checkbox_and_quantity`, then after "else" `selects_by_checkbox_only`, `selects_by_quantity_only` (alone, `$checkbox_selections` is dropped). `$currency_code` `has_currency_code`, `h($currency_code)` the token `__currency_code`.

## What did the widget gain?
- `pg_of_flags()`: the five heading flags and `has_currency_code` (its last argument, `VISITOR_CURRENCY_CODE_FOR_OUTPUT`). `pg_of_row_flags()`: `order_form_has_image`. `pg_of_tokens()`: `__currency_code`. The editor's flag list, token palette and canvas sample (`USD`) carry them ([[order-form-widget]]).
- A donation row's `__price_formatted` is empty, ordered or not, as on the classic page (before, an out-of-stock donation showed its price).

## What did the starting sites give?
- 20 file pages, 11 texts (en 283, 266, 251, 496; tr 283, 266, 251, 394, 418, 639, 496): all convert with nothing dropped, so each converts in the move run. `tests/layout_convert_test.php` runs every text and a converted row through `pg_of_row_html()`.
