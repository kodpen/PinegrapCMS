---
name: order-oversold-warning
description: Oversold warning (#10682): a sale line short of stock with back orders off raises one oversold bell line per order; the order screen notes "Taken from stock: a of b"
module: stock_sales
---

## What was wrong? (#10682, PR #11077)
- The stock is read at submit and taken after the payment; nothing holds the units between. Two orders of the last unit (a bundle or a plain product) were both paid and completed, the take stopped at 0, and only the `out_stock` bell (once per product) showed it.
- Decided (`workflow/decisions-made-product-86.md`): flag it now; no lock, no automatic refund. Holding units is `plans/stock-holds.md`.

## When is it raised?
- After the takers ran: `pg_stock_oversold_warn()` (`includes/fn/stock_sales.php`), called at the end of `pg_order_complete_lines()` (checkout and counter sale) and after `mp_order_take_stock()` in the marketplace import.
- It reads the order's `order_items` and `order_item_components` rows with `stock_taken` recorded (`order-stock-taken.md`); a component row asks per-bundle × bundles sold.
- `pg_stock_oversold_products()` (pure) keeps a row whose `stock_taken` is below what it asked (`pg_stock_taken_short()`) and whose product has `products.backorder = 0`. Back orders on raise nothing; nothing recorded (untracked, older order) raises nothing.

## What is the bell line?
- One per order: action `oversold`, type warning, `title` = order number, `order_total` = how many products were short (each product once), `order_id`. No new column.
- Reaches whoever the `out_stock` line reaches (commerce on, `manage_ecommerce`); an API caller also needs `orders:read`. Module `shop`; no device push, like `out_stock`. API subject: the order.
- Words: "Sold beyond stock #<number>", text "Products short of stock: n"; the push sentence "An order took more units than the stock held."; opens `view_order.php`.

## What does the order screen show?
- Under a line whose `stock_taken` is recorded and below its quantity, back order lines too: "Taken from stock" "a of b" (`pg_order_view_stock_taken_fact()`, `includes/fn/order_view.php`). A bundle line's own row keeps NULL, so its components' shortfall shows only on the bell.

## What does it not do?
- No money moves; nothing is cancelled, refunded or held by the software.

Tests: `tests/stock_oversold_test.php`.
