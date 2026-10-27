---
name: db-q-converted-order-front
description: Order preview, receipts, shipping address step, reorder, retrieve order and remove item converted to db_q(): special cases, what stayed raw, guard test.
---

## Which files are converted, and what stayed raw?
- PR #2319: `get_order_preview.php` 12, `get_order_receipt.php` 11, `get_order_receipt_in_plain_text.php` 12, `get_shipping_address_and_arrival.php` 5, `order_history_reorder.php` 10, `order_history_retrieve_order.php` 7, `remove_item_from_cart.php` 9 calls with values. `(string)` where the SQL quoted, also for ids, session order ids and `$offline_payment_allowed` written unescaped into quotes (digits, same text).
- `(int)` for the ship to lookup of the preview and both receipts, which wrote `ship_tos.id = $ship_to_id` bare; the id comes from `SELECT DISTINCT ship_to_id FROM order_items` (0 for items not shipped), so the text is the same.
- `order_history_reorder.php` wrote the product name of a copied order item into quotes unescaped: a name with `'` made that INSERT fail. It is escaped now, the one place where the text sent differs.
- Values with `??` keep their parentheses: `(string) ($_SESSION[...] ?? '')`.
- The retrieve order update carries its clause values beside them (`$sql_tracking_code_values`, `$sql_utm_values`, `array_merge()`).
- Raw stay the five queries of `get_shipping_address_and_arrival.php` without values, the arrival date list (`ECOMMERCE_END_OF_DAY_TIME`) among them.

## How was it checked?
- As for the front-end files (`db-q-converted-front.md`): each old and new query rendered with a marker per value and compared, the `array_merge()` update run old against new for every branch. `tests/db_q_order_front_test.php` guards the files.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-front.md
