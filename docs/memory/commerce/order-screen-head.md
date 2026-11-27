---
name: order-screen-head
description: View Order's head under the breadcrumb (#11098): the order number with a copy button, the order, payment and shipping pills worked out from the stored order, the grey line, the toolbar at its right
module: order_view
---

## What does the head show?
- Under the breadcrumb, whose last crumb is now "#1171" ("Incomplete #428" without a number): "Order #1171" (`pg_order_view_head_title()`), a copy button copying the number without "#", then the pills. The grey line: order date, type, the contact's name (a link when `pg_can_open_contact()`), "2 items, 3 units" (`pg_order_view_counts_text()`), and "Cancellation reason: …" on a cancelled order.
- The toolbar (`pg_screen_toolbar()`, unchanged slots) sits at the head's right; on a phone under it. The top bar keeps "View Order". Banners and notices stay over the head.
- Drawn by `pg_record_head()` in `includes/fn/output.php` (Design examples "Record head"); its CSS is the "Look A: record head" block of `backend.src.css`. Edit Product uses it too, with the optional `media` picture before the title (`product-insights-card.md`).

## How are the pills worked out?
- `pg_order_view_states($order, $addresses, $today)` is pure: the screen hands it the orders row (taken as `$head_order` before `$row` is reused), and per ship-to its dates, lines and parcel statuses, gathered in the loops that already read them (`$head_addresses`).
- Order: Incomplete grey, Complete and Exported teal, Cancelled rose.
- Payment (none on an incomplete order or a zero total): awaiting payment with the Order Information badge's words (On account, Payment pending on PayPal or on Stripe, Awaiting bank transfer) amber; Refunded teal when the refund reaches the total; Partially refunded amber; Put on account blue; else Paid teal.
- Shipping, only with an address (`pg_order_view_address_shipping()`): Delivered when the delivery date is today or before or all parcels are delivered; Shipped when the ship date is today or before, a parcel exists, or every line with a shipped quantity is full; Partially shipped when one is above zero; else Not shipped.
- The order: all delivered, all shipped, any begun (Partially shipped), else Not shipped. A cancelled order shows it only when something left. `addresses` keeps each ship-to's pill for its shipment card (a later step).

Test: `tests/order_view_head_test.php`.
