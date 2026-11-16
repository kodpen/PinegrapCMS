---
name: visitor-push-order
description: Visitor notifications for an order (kind 3, VP6): the door's order action, who may follow an order, shipped and delivered sent once per address beside the SMS, the text, language and link
module: site_push_order
---

## Who may follow an order?
- `site_push.php` `action=order` with the secret and `order_id` (a write under the door's rate): `pg_site_push_order_watch()` writes one `site_push_watch` row (kind 3) per `ship_tos` row of the order (`visitor-push-watch.md`). Answers `watching`, or `off` (404), `subscription`, `order`, `nothing` (an order without an address).
- Pure `pg_site_push_order_may_watch()`: a placed order (not `incomplete`) that is the session's `completed_order_id` (the receipt page) or the signed-in account's own (My Account's order view).

## When is a notice sent?
- `pg_site_push_order_moment($order_id, $ship_to_id, $moment)` is called beside the order's SMS and WhatsApp: `update_order()` for shipped (inside the guard that keeps the customer's notice back), `pg_shipment_delivered()` for delivered (only with `notify`). So a marketplace's order and a save that keeps the notice back send nothing, as for the SMS. Not a listener in `pg_event_record()`, which hears both.
- Pure `pg_site_push_order_moment_bit()`: shipped sets bit 1, so a parcel number added later sends nothing again; delivered is the last moment and deletes the row.

## What does it say and open?
- Pure `pg_site_push_order_text($moment, $order_number, $language)`: "Your order has shipped" / "Order {var:1} is on its way.", "Your order has been delivered" / "Order {var:1} has been delivered.", in the subscription's own `language` (`lang()` with `language`), cut to 60 and 200.
- The link (`pg_site_push_order_url()`): the "view order" page type with `?id=`, as a path; the home page when the site has none.

## What is not built yet?
- The button "Notify me on this device" on the receipt page and the order view: it needs the subscribed browser's secret, kept by the site's script and worker of VP3. The door's action waits for it.
