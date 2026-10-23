---
name: order-page-stored-figures
description: Order view (My Account) and receipt page/e-mail show a placed order's own orders.discount, tax and total (pg_order_page_figures), never the session cart's order_discount; what an unplaced order shows
---

## Which pages does this cover?
- `get_view_order_screen_content.php` (My Account's order view, both the system and the custom layout; also `order_history_view_order.php`), `get_order_receipt.php` (the receipt page, both layouts) and `get_order_receipt_in_plain_text.php` (the receipt e-mail).
- The designer order view widget (`includes/fn/widgets.php`) and the panel's order screen already read the stored columns. The classic express order and order preview pages are not touched: their arithmetic is frozen (workflow/decisions-made-product.md).

## Where do the discount, tax and total come from?
- `pg_order_page_figures($order, $_SESSION['ecommerce'])` (includes/fn/ecommerce.php, pure, `tests/order_page_figures_test.php`). A placed order (any status but `incomplete`) gives `orders.discount`, `orders.tax` and `orders.total`, the figures `submit_order()` charged and wrote.
- The pages still list the lines, the subtotal, the shipping of each recipient, the gift cards, the surcharge and the installment charge from their own rows; only the discount, the tax and the total line are the order's.
- The tax row shows when tax is on or the order was charged tax, so a store that switched tax off later still shows what the total holds.

## What does an order that is not placed show?
- The visitor's cart (the session's `order_id`) shows the session's `order_discount`, which `apply_offers_to_cart()` worked out for it, and its tax and total are worked out from the lines (`pg_order_items_charged_tax()`) as before.
- Any other `incomplete` order, a cart saved on the account and opened from My Account, shows no discount: offers are worked out again when it is retrieved as the cart.

## What was the symptom? (#1361, PR #1366)
- The pages took the order from `?id=` or `completed_order_id` but the discount from `$_SESSION['ecommerce']['order_discount']`, and worked the tax out again from it. A placed discounted order showed no discount and the undiscounted tax once the session's discount was gone; a visitor with a discounted cart open saw that cart's discount on every order and receipt.
- Checked on the sandbox by rendering the pages from the command line against its database: order 1182 (129.95, 13.00 off, 9.98 shipping) showed tax 25.99 and total 165.92 with no discount, or 50.00 off, tax 15.99 and total 105.92 with another cart's discount in the session. It now shows 13.00 off, tax 23.39 and total 150.32 either way, in both layouts and the e-mail.

Related: docs/memory/commerce/order-discount-charged-today.md, docs/memory/commerce/express-order-widget-totals.md
