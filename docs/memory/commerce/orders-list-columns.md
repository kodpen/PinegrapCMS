---
name: orders-list-columns
description: All Orders (view_orders.php) columns: the default set, Customer, Status, Payment and Shipping pills from View Order's functions, Products by name with "+N more", one query each (#11142, #11208)
module: order_list
---

## Which columns does All Orders show by default?
- Order Number, Order Date, Customer, Status, Payment, Shipping, Products, Total, Action (`pg_order_list_columns()`, `includes/fn/commerce_list.php`).
- First Name, Last Name, Type, User, Tracking Code, Member ID, Affiliate Code, Card Data and Profit are in the column chooser with `'hidden' => true`. Action stays last.
- A person's saved columns stay: the browser keeps only the columns a person showed or hid; any other column follows the default (`pg_list_table.js`).

## What does each new cell show?
- Customer: billing first and last name, else the billing company (`pg_order_list_customer_text()`). It sorts by the same text in SQL (sort code `customer`).
- Payment and Shipping: the pills of View Order's head, from `pg_order_view_states()` (`order-screen-head.md`), drawn with `pg_pill()`. No pill, an empty cell.
- Products: the first active line's product by the name people know, `products.short_description` with tags stripped, and "+N more" for the other lines (`pg_order_list_items_html()`). A deleted product falls back to `order_items.product_name`, the SKU, which the cell showed before #11208 ("20M" for "Yeşil Ofis Sandalyesi").
- Status: View Order's order pill (`pg_order_status_pill()`: Incomplete grey, Complete and Exported teal, Cancelled rose) through `pg_order_list_status_html()`, with an amber "Awaiting Payment" pill (the Payment column may be hidden) and the Paraşüt marks as before; its quick edit is unchanged. It was a filled blue badge until #11208.
- Customer and Products give way beside the open Filters panel: one line cut with an ellipsis at 180 px, the whole text as the title (`pg_order_list_clip_html()`, `.pg-orders-clip`), so the nine default columns fit 1030 px.

## How is the data read?
- `pg_order_list_row_states()` reads the page's rows once per query: the first line and line count (`pg_order_list_items_sql()`, saved-for-later lines out), the lines with their address dates (`pg_order_list_ship_lines_sql()`, addresses taken from the lines as View Order takes them), the parcels (`pg_order_list_parcels_sql()`).
- `pg_order_list_addresses()` builds View Order's address shape: a parcel with no number is left out, an unknown status reads as created (as `pg_shipment_view()`).
- Without `orders.paid_at` the awaiting state is off, as on View Order; `refunded_amount` and `erp_on_account` are probed and read as 0 before their steps.

Test: `tests/order_list_columns_test.php`.
