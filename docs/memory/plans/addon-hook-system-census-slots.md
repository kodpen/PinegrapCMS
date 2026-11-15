---
name: addon-hook-system-census-slots
description: Add-on hook system AH1 census, UI slot places: how the order screen draws its actions and cards and the orders list its row actions, toolbar and bulk actions today
---

## Is there any extension point on these screens today?
- None: each screen builds `$output_*` HTML strings and echoes them once. The extension ways that exist (PHP regions, a page's `pre_save_hook_code` / `post_save_hook_code` eval'd in `submit_order.php`, `custom_form.php`, `shopping_cart.php`, webhooks, the API) do not reach panel screens.
- Shared helpers: `pg_screen_toolbar($slots, $opts)` (`includes/fn/output.php`, slots `primary`, `secondary`, `grow`, `search`, `view`, `overflow`); `pg_list_table_html()` / `pg_list_table_printed()` (`includes/fn/list_table.php`), whose `$actions` is raw markup for the bulk bar; `pg_list_cell()` (`includes/fn/list_paging.php`). No card helper and no row-action helper.
- Cards other modules hand the order screen as one function each: `pg_legal_documents_panel_card()`, `pg_returns_panel_card()`, `erp_order_flow_card()`, `ws_record_button()`.

## How does the order screen draw its actions? (Order screen › actions)
- `view_order.php`, one `echo pg_page_shell(...)` near the end. Its bar is `pg_screen_toolbar()`: `primary` Payment Received (`$output_mark_paid_button`), `secondary` Refund, Print (opens `print_order.php`), Cancel Order, `overflow` the three-dots menu.
- The three-dots menu is `$toolbar_more`, an array of `.menu-item` strings merged from `$output_gateway_menu_item`, `$output_parasut_menu_items`, `$output_erp_menu_items`, `$output_workspace_button`, filtered by `strlen`. A comment keeps at most four buttons beside the primary one, so integrations go into this menu.
- Elsewhere: refund and cancel modals built as strings, the sticky Save / Restore / Delete bar, label buttons inline in each ship-to card (Create Label, Print Label, Request Pickup ...) posting `#shipment-label-form` to `shipment_label.php`, the packing slip's Print button in the shipping card header.

## How does the order screen draw its cards? (Order screen › extra card)
- Left column (`lg:col-8`): Billing Information, custom billing fields, Customer Information, payment information, written inline.
- Right card column: `pg_order_view_card_column(array(...))` (`includes/fn/order_view.php`), an array of card strings (shipping details per ship-to, source, gift cards, affiliate, legal documents card, returns card); empty strings are skipped. The order lines follow (`pg_order_view_line()`); the status column (`lg:col-4`) holds one inline Order Information card.
- The ERP documents card sits under the bar. There is no timeline card on the panel screen.

## How does the orders list draw its actions? (Orders list › row action, toolbar)
- `view_orders.php`. Toolbar: a hand-written `nav#button_bar.pg-toolbar`, not `pg_screen_toolbar()`: Add Order, the Export menu with `$output_gateway_buttons`, Import Tracking Numbers, sort menu, status and type filters, advanced filters, period navigator.
- Row actions: each row is `['id' => ..., 'cells' => [...]]`; the `actions` cell (kind `actions` in `pg_order_list_columns()`, `includes/fn/commerce_list.php`) is `pg_list_cell()` of the Cancel button plus tracking icons. Cancel posts a shared form to `cancel_order.php` by JavaScript.
- Bulk actions: markup passed to `pg_list_table_html()` inside a form to `edit_orders.php`. A button's label is mapped to the hidden `action` by a hard-coded `switch` in `assets/js/backend.src.js`, and `edit_orders.php` switches on `$_POST['action']`: a new bulk action touches all three.
- The printed order's places are in `addon-hook-system-census-print.md`.
