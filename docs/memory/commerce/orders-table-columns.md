---
name: orders-table-columns
description: Real column names of orders and order_items (tax, shipping, user_id, order_date, no SKU column), optional columns, status enum, and the shape the external API returns for an order.
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
- A counter sale (`type = 'local'`) writes the cashier into `user_id` (`local_sale_complete()`); its buyer is `contact_id` / `erp_account_id`. A reader that finds a buyer's orders by `user_id` adds `type <> 'local'` (`ws_customer_order_where()`).
- `order_items` has NO `item_number` (SKU): take `products.name` ("Product ID / SKU") via a JOIN. `products.code` is NOT a code: it holds the product's image block HTML (`^^image_url^^` loop, the "Code" box of the product screen).

## Which columns exist only on upgraded installs?
- `notes` (order notes): absent in legacy schema; probe with `SHOW COLUMNS FROM orders LIKE 'notes'` (`api_orders_has_notes()` does it).
- `member_id` is NOT on orders: it is `contacts.member_id` (FK via `orders.contact_id`).
- Status enum: `'incomplete', 'complete', 'exported', 'cancelled'` (`'cancelled'` added 2026.1.26).

## What does the external API return for an order?
- The single source is `api_order_present()` (`includes/api/resources/orders.php`), declared in `api_order_schema()`; read the fields there. Shape: `id`, `order_number`, `status`, `placed_at` (+ `placed_at_unix`), `customer`, `billing_address`, `totals` (`subtotal`, `discount`, `tax`, `shipping`, `surcharge`, `total`), `payment`, `offer_code`; a single read adds `items[]` and `shipments[]`.
- `items[]`: two names answer two questions: `name` = the line's own copy (`order_items.product_name`, written at sale time, goes on the invoice); `catalogue_name` = today's catalogue (`products.name`, `null` if the product was deleted). `price` is per unit; `tax` is the LINE's tax (`order_items.tax_total`), never multiplied by the quantity. `ship_to_id` = the line's delivery (`shipments[].id`), `null` for a line sent to no address (POST /orders/{id}/returns names lines of one delivery).

## What did the item_number bug teach?
- `item_number` was written into `items[]` despite the "no such column" rule above, so `GET /orders/{id}` never worked (fixed 2026-09-06). When one document has two contradicting lines, verify the column against the SCHEMA.

Related: docs/memory/commerce/product-tax-rate.md
