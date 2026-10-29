---
name: order-form-widget
description: The Order Form widget (classic "order form" page type): handler and page row, list and drop-down layouts, row controls by name, flags, schedule section, what is left out.
module: widgets_order_form
---

## What is it?
- Kind `order_form` (`includes/fn/widgets_order_form.php`), the first type of the owner's order in [[system-widget-missing-types]] (5a, #2367). A product group's products go into the cart at once. It posts the classic names (`product_<id>`, `donation_<id>`, `recurring_payment_period_<id>`, `recurring_number_of_payments_<id>`, `recurring_start_date_<id>`, `product_id`, `quantity`, `ship_to`, `add_name`, `submit_add`, `submit_skip`, hidden `page_id`, `require_cookies`) to `order_form.php`; its checks, the items it adds and its redirects are unchanged.

## Where are the page's settings?
- In the page's `order_form_pages` row: the handler finds the page by it (no page type check). A designed page gets one at render (`INSERT IGNORE`, not in a background render).
- Settings `product_group_id`, `product_layout`, `add_button_label`, `add_button_next_page_id`, `skip_button_label`, `skip_button_next_page_id` are written only when the widget has the key; a group or next page of 0 means "the page's own" (`pg_of_page_row_changes()`). A converted classic page keeps its row as stored.

## How are the controls named?
- The tree names a row's control `product` (a check box or a quantity box, one block each, kept by the row flag) or `donation`; `pg_of_row_html()` renames them per product and fills them (default quantity on a new form, the posted value after a refused one). An unavailable product keeps no control.
- The drop-down layout: `product_id` (filled with every product and its price, out-of-stock words added) and `quantity`. The recipient: `ship_to` (`get_recipient_options()`) and `add_name`.
- `submit_add` / `submit_skip` buttons get the label as `value`; the skip button needs a label.

## Which flags and tokens?
- `pg_of_flags()`: `has_products`, `no_products`, `is_list_layout`, `is_dropdown_layout`, `has_quantity` (not only donations), `has_recipient_choice` (multi-recipient and an available shippable product), `has_skip_button`, `has_currency_choice`. Rows: `pg_of_row_flags()`.
- Row tokens use the catalog's names (`__name`, `__short_description`, `__description`, `__price_formatted`, `__image_url`, `__image_alt`, `__out_of_stock_message`, `__id`). Row section `recurring_schedule`: the classic schedule fields with the date picker.

## What is left out?
- Nothing of the classic custom layout (currency picker: [[checkout-currency-picker]]). No PHP default tree: like the checkout widgets, only the JS starter.
