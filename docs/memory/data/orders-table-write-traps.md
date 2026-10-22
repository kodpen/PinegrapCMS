---
name: orders-table-write-traps
description: Three traps when INSERTing into orders (reference_code, payment_method ENUM, order_date), the next_order_number table lock and where it is taken, and the orders.type values and list filter.
---

## What three traps exist when writing `orders`?
- `reference_code` is UNIQUE with default `''`: a second order without one collides. `generate_order_reference_code()` is mandatory.
- `payment_method` is an ENUM: an out-of-range value silently becomes `''`, because every connection pins the non-strict `sql_mode = 'NO_ENGINE_SUBSTITUTION'`. (Marketplace order numbers are written to `transaction_id` instead.)
- If `order_date` is empty the order NEVER appears in the list or in reports.

## How is the order number taken?
- `LOCK TABLES next_order_number WRITE`, read, increment, `UNLOCK TABLES`. The lock binds the whole connection: until the unlock it may touch only the locked table, and any other query fails (MySQL error 1100, "Table was not locked with LOCK TABLES"), which through `db()` or `or output_error()` ends the request. Never add a query between the lock and the unlock.
- Taken in `submit_order()` (`submit_order.php`), `local_sale_next_order_number()` (`includes/local_sale.php`), `mp_order_number()` (`includes/api/outbound/orders.php`, called on its own after the order lines), and the Commerce settings save that sets the next number (`includes/settings/commerce.save.php`).

## What are the orders.type values?
- `online` / `marketplace` / `local`.
- `view_orders.php`'s default filter was `'online'` and now reads `IN ('online','marketplace')`. Giving marketplace orders their own type and leaving the filter alone would hide them behind a filter nobody changes.

Related: docs/memory/commerce/marketplace-order-import.md
