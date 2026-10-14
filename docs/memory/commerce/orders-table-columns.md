---
name: orders-table-columns
description: Real column names of orders and order_items (tax, shipping, user_id, order_date), optional columns, status enum, and the fields the external API returns for orders.
---

## What are the real column names? (guard against wrong guesses)
- `orders` reachable only when `ECOMMERCE === true`. Prices stored in kuruş; external API returns kuruş (`api_money()`); panel `/100`.
| Column | Not |
|---|---|
| `tax` | `tax_total` |
| `shipping` | `shipping_total` |
| `subtotal`, `discount`, `surcharge`, `total` | (correct) |
| `user_id` | `user` (FK to users, NOT orders.user) |
| `order_date` | int unix timestamp, not a datetime string: `(int)$order['order_date']`, not `strtotime()` |
- `order_items` has NO `item_number` (SKU): take `products.code` via JOIN (`p.code AS item_code`).

## Which columns exist only on upgraded installs?
- `notes` (order notes): absent in legacy schema; probe with `SHOW COLUMNS FROM orders LIKE 'notes'` (`api_orders_has_notes()` does it).
- `member_id` is NOT on orders: it is `contacts.member_id` (FK via `orders.contact_id`).
- Status enum: `'incomplete', 'complete', 'exported', 'cancelled'` (`'cancelled'` added 2026.1.26).

## Fields returned by the external API
- List `orders`: `id`, `order_number`, `order_date`, `status`, `billing_first_name`, `billing_last_name`, `billing_email_address`, `billing_company`, `subtotal`, `discount`, `tax`, `shipping`, `surcharge`, `total`, `payment_method`, `transaction_id`, `special_offer_code`, `user`, `member_id`.
- Single (by id) adds: `billing_address_1`, `billing_address_2`, `billing_city`, `billing_state`, `billing_zip_code`, `billing_country`, `billing_phone_number` + `items[]`.
- `items[]` per line: `id`, `product_id`, `name`, `catalogue_name`, `quantity`, `price`, `tax`, `shipping`.
  - Two names answer two questions: `name` = the line's own copy (`order_items.product_name`, written at sale time, goes on the invoice); `catalogue_name` = today's catalogue (`products.name`, `null` if the product was deleted).
  - `tax` and `shipping` are PER UNIT.

## Lesson from the item_number bug
- `item_number` was written into `items[]` despite the "no such column" rule above, so `GET /orders/{id}` never worked (fixed 2026-09-06). When one document has two contradicting lines, verify the column against the SCHEMA.

Related: docs/memory/commerce/product-tax-rate.md
