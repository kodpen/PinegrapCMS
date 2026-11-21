---
name: order-stock-taken
description: order_items.stock_taken and order_item_components.stock_taken (step 11.0.269, #10684): what a sale took from the stock, who writes it, how a marketplace cancel puts back only that
module: stock_sales
---

## What was wrong? (#10684)
- A marketplace cancel put back a line's whole quantity, and a bundle line's whole quantity of every component. A sale made with no stock or too little (back orders, a marketplace selling more than the shop holds) took less, so the cancel left stock the shop never had and pushed it to every marketplace. Example: C stock 0, bundle = 2 × C, one bundle sold and cancelled → C stock 2.

## What is stored? (owner, poll 171; `workflow/decisions-made-schema-33.md`)
- `order_items.stock_taken`, `order_item_components.stock_taken`: INT UNSIGNED NULL, step `upgrade_2026_11_0_stock_taken()`, label 11.0.269. NULL = not recorded (a line from before the step, an untracked product).

## Who writes it?
- `pg_stock_taken_record()` (`includes/fn/stock_sales.php`), each taker with what it took:
  - `pg_order_complete_line_stock()` (checkout, counter sale): 0 at no stock; without locations min(stock before, quantity), the unsigned column stopping at 0; with locations `pg_stock_sale_take()`'s `taken`.
  - `pg_bundle_take_stock()` (checkout, counter sale, marketplace): per component row, the same rule on per-bundle × quantity.
  - `mp_order_take_stock()` plain line: min(stock before, quantity), or with locations `pg_stock_total_write()`'s delta.

## Who reads it?
- `mp_order_return_stock()` and `pg_bundle_return_stock()`: put back `pg_stock_taken_put_back()` = the smaller of taken and asked (all asked when NULL); 0 → nothing written and nothing announced. After a write, `pg_stock_taken_returned()` lowers the row's figure, so a cancel applied twice gives the stock back once.
- Not changed: a customer return's restock (it counts goods in hand). The oversold warning that reads these columns is #10682.

Tests: `tests/stock_taken_test.php`.
