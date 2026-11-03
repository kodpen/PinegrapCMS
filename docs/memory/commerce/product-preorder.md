---
name: product-preorder
description: Product pre-order (11.0.151, PR #5550): columns, back order path, when it runs, tokens and notes, arrival date bound, hourly end, API field
module: preorder
---

## What is stored?
- `products.preorder` TINYINT (0/1) and `products.preorder_date` DATE NULL (step `upgrade_2026_11_0_product_preorder()`, 11.0.151). Every reader asks `pg_product_preorder_ready()` and selects through `pg_preorder_select_sql($alias, $ready)`, which reads `0 AS preorder, NULL AS preorder_date` before the upgrade.

## How is a pre-order product sold?
- On the back order path: the product screen (`pg_preorder_from_post()`) and the API turn `backorder` on with the pre-order, so every stock gate of cart and checkout lets it through unchanged. No totals code knows about pre-orders (plan decision).
- Turned on it needs a date after today (`pg_preorder_problem()`); the screen and the API refuse otherwise instead of saving it off.

## When does a pre-order run?
- `pg_preorder_active()`: flag set, date after today, and no stock in hand (a tracked product with quantity above 0 ships now; untracked: the date alone). Readers decide live, so an ended pre-order disappears at once.
- `pg_preorder_end_hourly()` (job.php) clears the flag where the date has come or stock is in, 500 rows a pass; the date and the back order stay (the operator turns the back order off).
- A copied product (duplicate) starts without a pre-order: the copy path does not carry the two columns.

## Where is the note shown?
- Catalog list and product page widgets: `__preorder` (0/1), `__preorder_date`, `__preorder_label` ("Pre-order, ships from <date>"), next to the `__out_of_stock` family; the product starter places `__preorder_label`.
- Cart line: `__item_preorder_label` (cart starter places it). Order view widget (the HTML receipt mail and account order page): `__item_preorder_label`. Plain-text receipt: appended to the line's description.
- Not in the express order (checkout) lines, the product feeds or marketplaces yet.

## How is the arrival date bound?
- `get_shipping_methods()` (shipping.php) answers `code => 'arrival_date'` when a real requested arrival date (custom or fixed choice) falls before the latest running pre-order date of the order's active lines (`pg_preorder_order_earliest_arrival()`).

## What does the API carry?
- `products.preorder_date`: the running pre-order's day or null; a write with a date after today starts it (back order on), null or '' ends it, 422 otherwise, 503 before the step.

## Why "Ön satış" in Turkish?
- "Backorder" is already "Ön Sipariş" in the panel; pre-order texts say "ön satış" so the two switches are not read as one.
