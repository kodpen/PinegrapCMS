---
name: order-preview-receipt-warnings
description: Why the built-in Order Preview and Order Receipt layouts write no PHP warnings on an empty cart, an order without shipping, items that are not shipped or a receipt page without its settings row
---

## Which values does the built-in Order Preview start before the cart is read?
- In `get_order_preview.php`, at the top of the `layout_type == 'system'` branch, before `if ($ship_tos)`: `$recurring_products_exist` and `$recurring_transaction` `false`, `$applied_offers` and `$charged_item_ids` `array()`, `$output_grand_tax`, `$output_grand_shipping` and `$output_recurring_products` `''`.
- Why: an empty cart skips the item loop, an order with no shipping recipient never builds the shipping row, and tax may be off, yet the totals, the applied offers and the payment part read all of them. The owner's PHP 8.5 log had them as "Undefined variable" up to 2 November 2026 (plan php-error-log-clean, EL5).
- The same names were set inside `if ($ship_tos)` before; they are set only once now, so nothing the loop sets is reset.

## Why does each recipient start an empty shipping row?
- `get_order_preview.php` and `get_order_receipt.php` set `$output_shipping = ''` beside `$output_products = ''` for each recipient. Items that are not shipped sit under ship to 0, which gets no shipping row, and the recipient block printed `$output_shipping` anyway.
- The plain-text receipt did the same already (`cart-express-order-warnings.md`).

## What does an Order Receipt page without its settings row read?
- `get_page_type_properties()` returns `null` when the page has no `order_receipt_pages` row. `get_order_receipt.php` reads `?: array()` and `product_description_type` `?? ''`, which is the short description, as before.

## What was left as already fixed?
- Census lines marked "set since" were read: `get_order_form.php` (`$edit_button`, `$output_add_button_value`), `get_express_order.php` (`$output_po_number`), `view_orders.php` (the `…_selected` filter values), `get_order_receipt_in_plain_text.php` and the preview's `$subtotal`, `$grand_tax`, `$grand_shipping`, `$output_discount` are now set on every path.
- Tests: `tests/order_pages_warnings_test.php`.
