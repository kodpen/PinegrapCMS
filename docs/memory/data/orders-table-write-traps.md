---
name: orders-table-write-traps
description: Traps when INSERTing into orders, the next_order_number lock and where it is taken, when the Commerce settings save writes the next number, and the orders.type values.
---

## What three traps exist when writing `orders`?
- `reference_code` is UNIQUE with default `''`: a second order without one collides. `generate_order_reference_code()` is mandatory.
- `payment_method` is an ENUM: an out-of-range value silently becomes `''`, because every connection pins the non-strict `sql_mode = 'NO_ENGINE_SUBSTITUTION'`. (Marketplace order numbers are written to `transaction_id` instead.)
- If `order_date` is empty the order NEVER appears in the list or in reports.

## How is the order number taken?
- `LOCK TABLES next_order_number WRITE`, read, increment, `UNLOCK TABLES`. The lock binds the whole connection: until the unlock it may touch only the locked table, and any other query fails (MySQL error 1100, "Table was not locked with LOCK TABLES"), which through `db()` or `or output_error()` ends the request. Never add a query on another table between the lock and the unlock.
- Taken in `submit_order()` (`submit_order.php`), `local_sale_next_order_number()` (`includes/local_sale.php`), `mp_order_number()` (`includes/api/outbound/orders.php`, called on its own after the order lines), and the Commerce settings save that sets the next number (`includes/settings/commerce.save.php`).

## When does the Commerce settings save write the next number?
- Only when the posted Next Order Number differs from the hidden `ecommerce_next_order_number_was` the pane was opened with: before, every save of the pane wrote back the value loaded at opening, so an order completed meanwhile got a number already used (`orders.order_number` has no unique index).
- A changed value must pass `pg_next_order_number_refusal()` (`includes/fn/order_completion.php`): digits only, at least 1, above `MAX(orders.order_number)`; checked before the config UPDATE, then written with UPDATE (INSERT when the row is missing), never DELETE + INSERT.
- Under the lock the save reads the counter again (`pg_next_order_number_set_back()`): when orders moved it since the screen was opened and the typed value is below it, nothing is written and a warning names the current counter after the unlock. The MAX() check runs before the lock and misses an order completed in between (or one that took its number and has not stored it yet), whose number would otherwise be given out again.

## What are the orders.type values?
- `online` / `marketplace` / `local`.
- `view_orders.php`'s default filter was `'online'` and now reads `IN ('online','marketplace')`. Giving marketplace orders their own type and leaving the filter alone would hide them behind a filter nobody changes.

Related: docs/memory/commerce/marketplace-order-import.md
