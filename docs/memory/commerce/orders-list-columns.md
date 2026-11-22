---
name: orders-list-columns
description: All Orders (view_orders.php) columns: the default set, Customer, Payment and Shipping pills from View Order's function, Products with "+N more", one query each (#11142)
module: order_list
---

## Which columns does All Orders show by default?
- Order Number, Order Date, Customer, Status, Payment, Shipping, Products, Total, Action (`pg_order_list_columns()`, `includes/fn/commerce_list.php`).
- First Name, Last Name, Type, User, Tracking Code, Member ID, Affiliate Code, Card Data and Profit are in the column chooser with `'hidden' => true`. Action stays last.
- A person's saved columns stay: the browser keeps only the columns a person showed or hid; any other column follows the default (`pg_list_table.js`).

## What does each new cell show?
- Customer: billing first and last name, else the billing company (`pg_order_list_customer_text()`). It sorts by the same text in SQL (sort code `customer`).
- Payment and Shipping: the pills of View Order's head, from `pg_order_view_states()` (`order-screen-head.md`), drawn with `pg_pill()`. No pill, an empty cell.
- Products: the first active line's product name and "+N more" for the other lines (`pg_order_list_items_html()`).
- The Status cell is unchanged (its quick edit, Awaiting Payment and Paraşüt badges).

## How is the data read?
- `pg_order_list_row_states()` reads the page's rows once per query: the first line and line count (`pg_order_list_items_sql()`, saved-for-later lines out), the lines with their address dates (`pg_order_list_ship_lines_sql()`, addresses taken from the lines as View Order takes them), the parcels (`pg_order_list_parcels_sql()`).
- `pg_order_list_addresses()` builds View Order's address shape: a parcel with no number is left out, an unknown status reads as created (as `pg_shipment_view()`).
- Without `orders.paid_at` the awaiting state is off, as on View Order; `refunded_amount` and `erp_on_account` are probed and read as 0 before their steps.

Test: `tests/order_list_columns_test.php`.
