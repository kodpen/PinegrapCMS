---
name: stock-locations-pickup
description: Pickup at a location: a shipping method naming a stock location, no rate, offered only while the location holds the cart; the place on checkout, order screen, receipt, mails
module: stock_pickup
---

## What makes a shipping method a pickup?
- `shipping_methods.stock_location_id` > 0 (column from 11.0.162, `stock-locations-model.md`); no separate kind column. The form's "Pickup at a location" select shows only on a site with locations (`pg_stock_pickup_location_options()`).
- Saved through `pg_stock_pickup_columns()` (pure): an unknown or forged id stores 0; a location also zeroes every rate, clears the service and the real-time rate, and allows street and PO box addresses, so the method is never charged or hidden by the address. Zones, days and times still apply. Without locations the column is not written.
- The order takes its units from that location through `pg_stock_order_location()` (the first recipient's method location), so completion and `check_inventory()` already count there (`stock-locations-completion.md`).

## When does the checkout offer it?
- `pg_stock_pickup_methods_filter($ids, $order_id)` drops a pickup method whose location is off or does not hold every unit of the cart: the active lines of tracked, non-backorder products summed per product over all recipients (`pg_stock_pickup_cart_needs()`, `pg_stock_pickup_covers()`, units read with `pg_stock_available_at()`). Nothing counts from another location (#5042 question 2).
- Called in both layouts of `get_shipping_method.php`, in `get_shipping_methods()` (express order, widgets, panel, API: an emptied list answers an error) and in `check_shipping_method()` (a chosen pickup that no longer fits is refused on submit).
- A site without locations: the filter answers the list unchanged without a query.

## Where is the place named?
- `pg_stock_pickup_describe($method_id, $description, $html)` adds "Pickup at: <name>, <address on one line>" after the method's description: checkout lists, `get_order_receipt.php` and `get_order_preview.php` (system layout escaped, custom layout arrays plain because the templates escape), `get_order_receipt_in_plain_text.php` (so the order confirmation mails carry it).
- `view_order.php` shows it under the shipping method link.
- order_shipped gets `^^pickup_place^^` (`pg_stock_pickup_mail_fields()`, empty for a delivery). There is no ready-for-pickup status: a pickup order marked shipped gets the shipped mail's text until a later plan adds its own mail.

## What guards it?
- `tests/stock_pickup_test.php`: the location pick, the cart cover, the place text and escaping, the saved columns and forged ids, the options, source pins of every caller. Not run in a browser.
