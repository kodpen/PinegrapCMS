---
name: order-screen-toolbar
description: The order screen's toolbar (view_order.php): which buttons stay on the bar, which are rows of the three-dots menu, why menu rows lost their colours
---

## Which buttons stay on the order screen's bar? (#6518)
- Primary: Payment Received (`$output_mark_paid_button`). Secondary, at most four: Refund (`$output_refund_button`), Print, Cancel Order (`$output_cancel_button`, `btn-outline theme-warning`).
- Everything else is a row of the `overflow` slot (`$output_toolbar_more`, `ul.menu.pg-menu-end`): the payment provider's panel (`$output_gateway_menu_item`), Paraşüt Create Invoice / E-Shipment (`$output_parasut_menu_items`), the ERP invoice, delivery note or Issue the Invoice (`$output_erp_menu_items`, emptied when the ERP document card shows) and Workspace (`ws_record_button(…, 'menu-item pg-link-body-emphasis')`). No row, no menu.
- Before, every button sat in `secondary`; with all integrations on the bar held seven or more and wrapped onto several lines on a narrow screen.

## Why do the Paraşüt and ERP rows have no colour?
- Menu rows are all `link-body-emphasis` (`ui/admin-screen-toolbar-rules.md`). The old green/yellow button colours are gone; the "Sent" badges and the Order Information card's Paraşüt rows tell the state.
- The Issue the Invoice form is `form.d-contents.disable_shortcut` so its button lays out as a menu row (`tests/save_shortcut_test.php`).
- Pinned by `tests/view_order_toolbar_test.php`.
