---
name: orders-table-write-traps
description: Three traps when INSERTing into orders (reference_code, payment_method ENUM, order_date), the order-number lock, and the orders.type values/filter.
---

## What three traps exist when writing `orders`?
- `reference_code` is UNIQUE with default `''`: a second order collides. `generate_order_reference_code()` is mandatory.
- `payment_method` is an ENUM: an out-of-range value silently becomes `''` because sql_mode is empty. (Marketplace order numbers are written to `transaction_id` instead.)
- If `order_date` is empty the order NEVER appears in the list or in reports.

## How is the order number taken?
- With `LOCK TABLES next_order_number WRITE`. The lock applies to the whole connection: no query to another table may run in between, so it lives in its own function.

## What are the orders.type values?
- `online` / `marketplace` / `local`.
- `view_orders.php`'s default filter was `'online'` and now reads `IN ('online','marketplace')`. Giving marketplace orders their own type and leaving the filter alone would hide them behind a filter nobody changes.

Related: docs/memory/commerce/marketplace-order-import.md
