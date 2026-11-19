---
name: visitor-push-order
description: Visitor notifications for an order (kind 3, VP6, VP6b): the button "Notify me on this device", the door's order action, who may follow, shipped and delivered sent once, text and link
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

## Where is the button "Notify me on this device"?
- Under the receipt page (`order receipt`, the session's `completed_order_id`) and the order view (`view order`, `?id=`), added in `get_page_content.php` after the screen, never in an e-mail. `pg_site_push_order_button()` draws it only while the switch is on, the 11.0.229 table exists and the order is placed with at least one address (pure `pg_site_push_order_button_shown()`); who may follow is still the door's check.
- Markup (pure `pg_site_push_order_button_html()`, Bootstrap 5.3.8, in `bs6_site_functions()`): `data-pg-push-order="<id>"` and `hidden`, the button `data-pg-push-order-on` (`btn btn-outline-primary btn-sm`), the status lines `data-pg-push-order-done` (focused after the yes), `-blocked`, `-failed`.
- The mark brings `pg_site_push.js` like the block (`pg_site_push_order_on_page()` in `pg_site_push_block_inject()`). Shown where the block would show. The press subscribes the browser first when it has no subscription (the browser's question), then posts `order`; a secret the door no longer knows is replaced once. A "Notify me" block on the same page follows.
- Not kept across a reload: the button shows again; a second press writes nothing new (INSERT IGNORE).
- Not on designer order widgets: only the two page types' system regions.
- Tests: `tests/site_push_order_test.php` (markup, when shown, wiring), `tests/site_push_block_test.php` (the press under Node).
