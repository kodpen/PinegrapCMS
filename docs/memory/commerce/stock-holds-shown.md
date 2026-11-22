---
name: stock-holds-shown
description: What people see of stock holds (SH4 #11166): the cart notice's words when other checkouts hold the units, and "Held in checkouts: n" under Edit Product's stock
module: stock_holds
---

## What does the visitor read?
- `check_inventory()` and `pg_bundle_check_inventory()` keep their three notices (removed, not available; removed, limited; quantity reduced) and add "Other shoppers are checking out with some of its units; they may free up in a few minutes." when holds took units the line needed.
- The rule, `pg_stock_hold_line_held($product_ids, $stock, $held)`, pure: a product of the line (a bundle: any component) has units other orders hold and had stock before the holds came off. No holds or no stock: the notice reads exactly as before.
- The words come from `pg_stock_hold_notice_words()` ('' when not held). The stock before the holds is `$product['stock_before_holds']` in `check_inventory()`, `$stock_before_holds` in the bundle check.

## What does the shop see on Edit Product?
- Under Inventory Quantity (or under Total with stock locations), in the Inventory Tracking panel: "Held in checkouts: n" and "Still in stock until the order completes or the hold runs out in a few minutes.", `#pg_pb_stock_held`, drawn by `pg_pb_stock_held_line()` in `product_builder.php`.
- Shown only while n > 0 (`pg_stock_hold_screen_line()`, pure); "None free for new checkouts." in bold once n reaches the product's total stock.
- n is `pg_stock_hold_units_held()`: unexpired rows of every order and location. A product that stopped tracking or takes back orders counts 0 (the checkout reads no hold for it); a bundle draws no line (its components are held, never itself). Not on the create screen.
- No other screen shows holds (plan feature H9).

## What was seen?
- Sandbox, 1440 and 390 px: no hold (no line), two of five held, tracking off with a leftover row (no line), three of three held (bold "None free"). The cart notice is pinned by tests only.
- Guards: `tests/stock_holds_test.php` (`test_stock_holds_line_held`, `_screen_line`, `_notice_callers`).
