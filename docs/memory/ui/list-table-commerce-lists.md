---
name: list-table-commerce-lists
description: Orders, gift cards, key codes, shipping methods (TD8a) and the ERP account prices, minimum stock and stock lists (TD8b) on the list table driver: printed whole, quick edit, bulk actions as ids
module: commerce_list
---

## Which lists are these and how are they drawn?
- `view_orders.php`, `view_gift_cards.php`, `view_key_codes.php` (template `includes/templates/view_key_codes.php`) and `view_shipping_methods.php`. Each printed every row on DataTables before and still prints every row, now in browser mode (`pg_list_table_printed()`, `ui/list-table-printed.md`): the browser sorts and searches, a heading click keeps the sort in the session (all four are in `pg_list_table_printed_lists()`).
- Columns come from `includes/fn/commerce_list.php` (`pg_order_list_columns()`, `pg_gift_card_list_columns()`, `pg_key_code_list_columns()`, `pg_shipping_method_list_columns()`), finished by `pg_commerce_list_specs()`: a key the screen's `$sort_columns` lacks is not sortable (the session could not keep it), a key of the screen's quick edit columns gets that field's input. Keys are the sort codes, so the toolbar's sort menu and a heading click agree; the orders list gained the `type` sort code for its Type column.
- The first column is the record's link (`pg_commerce_list_open_link()`, `data-pg-open`): the order number (billing name, else `#id`, for a cart), the gift card code, the key code, the method's name. The old Edit buttons are gone. Money, date and flag cells carry `data-pg-sort-value` (minor units, Unix time, 0/1).

## How does quick edit work on them?
- Each passes `edit` => its screen and its `pg_*_edit_columns()`; cells get `pg_list_edit_cell_attributes()` under the same rules as before (a gift card's notes on several lines or store credit, a variable base rate and an order status outside complete/exported stay text).
- The answers are unchanged; their `cells` (`last_modified`, a key code's `code`) find the cells by `data-pg-col`. The key code answer's `code` replaces the whole cell, so `view_key_codes.php` wraps it in the row's link again before replying.

## How do the orders bulk actions get the selection?
- The bulk buttons (Paraşüt export, Remove Card Data, Mark Shipped, the label buttons, Cancel, Delete) sit in the selection bar inside `form[name=form]`, which still posts to `edit_orders.php` (or `shipment_label.php` by `formaction`). A browser-mode payload is always `{ids}`, so the screen's script writes them as hidden `orders[]` inputs into `#pg_orders_selected`; neither endpoint changed. The per-row cancel button and the tracking buttons are the Action column.

## How did the ERP lists move (TD8b)?
- `erp_account_prices.php`, `erp_stock_minimums.php` (both quick edit) and the costed products of `erp_stock.php` print whole in browser mode, with their columns inline. They never kept a sort in the session, so they are not in `pg_list_table_printed_lists()`: the browser sorts them for the page only, as their `data-pg-sort` tables did. `pg_commerce_list_specs()` turns an `integer` edit field into a number input.
- Column keys `product` and `stock` match the cells the quick edit answers redraw. The minimum stock list's selection fills `#erp_order_form` with `products[]` for `add_erp_purchase_order.php` ("Purchase order for the selected" in the bar); the old tick boxes are gone. The stock list's product opens its movements; its total stays in the toolbar line.
- The "Click a … to change it" hints went: the Quick edit button (F2) opens the fields.

## What else moved?
- The gift card balances (active, expired, total) are a line above the list, since the list fills the window to its bottom.

Related: docs/memory/ui/list-table-driver-lists.md, docs/memory/ui/list-table-quick-edit.md
