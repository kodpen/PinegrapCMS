---
name: orders-list-quick-filters
description: All Orders quick filters (Awaiting payment, To ship, Shipped, Delivered): SQL held equal to the pills by an SQLite test, counts over the other filters (#11208)
module: order_list
---

## What do the quick filters list?
- A segmented control (`pg-seg pg-seg-wrap`) under the bar, above the figure strip, each with its count (`pg_order_list_quick_html()`).
- Awaiting payment: `pg_order_awaiting_payment()` (bank transfer or a pending method, `paid_at = 0`, complete or exported) with a total above zero, as the Payment pill. Off (`1 = 0`) before `orders.paid_at`.
- To ship: complete or exported, not awaiting payment, with an address, no address shipped or delivered (a partly shipped address still counts as to ship).
- Shipped, Delivered: the order's Shipping pill reads Shipped (every address shipped, not all delivered), Delivered (every address delivered).

## How is the SQL written?
- `pg_order_list_quick_sql($code, $today, $paid_known, $status_ready)` (`includes/fn/order_list.php`): correlated `EXISTS` over `order_items` (the addresses the lines point to), `ship_tos` and `shipping_tracking_numbers`, written for MySQL and SQLite both.
- A parcel without a number is not one; an unknown parcel status reads as created; before the parcel `status` column no parcel is delivered, as `pg_order_list_addresses()`.
- `tests/order_list_quick_test.php` runs each filter and the counts query on SQLite over nineteen sample orders and compares them with `pg_order_view_states()`; a change to the pill rule must change the SQL with it.

## How does it work with the other filters?
- Kept as `quick` in the screen's session (`?quick=to_ship`; `?quick=` is All), as the status select is. Its condition is added to `$sql_type`, so the list, the strip, Summary and the exports follow it.
- The counts are of the period and the other filters without the quick one (`$orders_quick_base_sql`, one query, `pg_order_list_quick_counts_sql()`).

## Where else is "To ship" counted?
- The main menu's Orders row (MB3): a red count of the orders "To ship" keeps over every period and filter, hover "{var:1} order(s) to ship", and a "To ship" entry under All Orders while the count is above zero.
- `pg_order_to_ship_menu_count()` runs `pg_order_to_ship_menu_sql()` (the same `pg_order_list_quick_sql('to_ship', …)`, never a copy) once a page and keeps it in the session for 60 seconds: it reads every complete order.
- The entry opens `pg_order_to_ship_menu_url()`: `view_orders.php?reset=true&quick=to_ship`, plus a start date on the oldest order to ship when that is older than the default month, so the list shows what the menu counted.

Related: docs/memory/commerce/orders-list-columns.md, docs/memory/commerce/orders-list-figure-strip.md, docs/memory/commerce/order-screen-head.md
