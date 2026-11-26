---
name: layout-type-converter-view-order
description: View order layout files into the order view widget (LE9e, #11688): its map, the widget's new flags and tokens, what is reported, the known differences
module: layout_convert
---

## What does the map do?
- `_pg_lc_view_order_map()` (`includes/fn/layout_convert.php`) via `pg_layout_piece_map('view order')` ([[layout-type-converter]]); `record`: the widget's tree has no main loop.
- `$recipients as $recipient` is read once (`keep`); its "Ship to" row is under `has_shipping` with the first recipient's tokens (`__ship_to_name`, `__shipping_full_address`, which starts with the name), reported as `recipients` on a multi-recipient site (state `multi_recipient`).
- `$recipient['items'] as $item` → the list `order.items` (key 0: the whole order). Its rows' values are the row flags: `item_event_name`, `item_payment_period`, `item_payment_count`, `item_first_payment_date`, `item_shipped_qty`. The item's form and gift card blocks become `__item_form_data_html` / `__item_gift_card_html`, given the widget's parts.
- `$recipient['shipping_tracking_numbers']` → the list `order.parcels`, each number a link to `__tracking_link` (empty for an unknown carrier), no comma.
- `pg_layout_convert_state('view order')`: `full_description` is always false (the page has no description setting, the short one is drawn), `multi_recipient` from the site.

## Which flags and tokens came with it?
- `pg_ov_layout_flags()` (`includes/fn/widgets.php`), set in `_render_system_widget_order_view()` only when the tree names one (`pg_ov_layout_flags_named()`, one more query of the order's columns): `billing_has_company`, `billing_has_address_2`, `billing_has_fax`, `has_po_number`, `is_tax_exempt`, `has_gift_card_discount`, `shows_custom_field_1/2`, `shows_shipped_quantity` (a line with `show_shipped_quantity`). Same names as the order preview's; listed in the editor's order view flags.
- Tokens: `__reorder_url` (`order_history_reorder.php` with the order id and the form token) and `__my_account_url`, filled only when the tree places them, empty in an e-mail. The custom fields, PO number, fax and gift card discount use the order's existing values (`__order_custom_field_1`, `__order_po_number`, `__billing_fax`, `__order_gift_card_discount`).

## What is reported (dropped after the designer's confirm)?
- The stock template reports: the product receipt messages loop, `$recurring_items` twice (the "Today's Charges" heading and the whole recurring part with its totals), the recipient's custom shipping form, the "[no limit]" words of a schedule and the "-" of a line not shipped (two `else`), the applied offers, the custom billing form, the applied gift cards list.

## Known differences
- One items list: an item charged later is listed with today's items. A donation's quantity and price are shown. The subtotal row is always shown. The converted total is not shown (the widget prints the amounts charged).
- The reference code and the Retrieve / Delete buttons of an incomplete order are not drawn: the widget shows a completed order.
- No starting-site page has a view order file: `tests/layout_convert_test.php` (the stock template, multi-recipient) and `tests/view_order_layout_test.php` (the flags).

Related: docs/memory/designer/layout-type-overview.md, docs/memory/designer/layout-type-converter-order-preview.md
