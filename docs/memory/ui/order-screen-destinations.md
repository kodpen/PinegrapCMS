---
name: order-screen-destinations
description: Where View Order's Save, Delete and back arrow go (send_to, else All Orders), and how its Paraşüt and label reloads keep send_to
---

## Where do View Order's Save and Delete go?
- To `send_to` through `pg_safe_redirect_path()` with the fallback `view_orders.php`; the "has been saved/deleted" notice is queued when the resolved target's path is `view_orders.php`.
- Symptom fixed: an order opened without `send_to` (Create Order, the notification bell, the dashboard cards, Ctrl+K, All Orders for Contact) landed on the public site's front page after Save or Delete, because `pg_safe_redirect_path()` falls back to `PATH`.

## Where does its back arrow go?
- `pg_send_to_url('view_orders.php')`: where Save goes (`back-arrow-targets.md`). Before, it was always All Orders, so an order opened from an Order Report or a gift card went back to All Orders. The breadcrumb stays on All Orders.

## How do the reloads keep send_to?
- The two Paraşüt mini forms and `shipment-label-form` carry a hidden `send_to` (`pg_send_to_url('')`); the Paraşüt redirects and `shipment_label.php`'s returns to the order append `&send_to=`.
- The ERP order-flow and gift card reloads still drop `send_to`; their next Save now lands on All Orders.

## Where does All Orders for Contact go back to?
- Its arrow goes to `edit_contact.php?id=<id>`, its opener and breadcrumb parent (was `view_contacts.php`); its row links pass `send_to` (the list) to View Order.
- Pinned by `tests/order_screen_destinations_test.php`.
