---
name: visitor-push-order
description: Visitor notifications for an order (kind 3, VP6): the shipped and delivered notices to the one device that asked on the order's page, their text and language
module: site_push_order
---

## What does an order's notice say?
- `pg_site_push_order_text($moment, $order_number, $language)` (pure, `tests/site_push_order_test.php`): `shipped` "Your order has shipped" / "Order {var:1} is on its way.", `delivered` "Your order has been delivered" / "Order {var:1} has been delivered."; any other moment sends nothing (null).
- The text is in the subscription's own `language` (`lang()` with `language`), not the panel's: the send runs in the job or the staff's request. Cut to the message columns (title 60, text 200).
- `kind` 3 (`PG_SITE_PUSH_KIND_ORDER`): one device, never in the panel's list of sent messages (`visitor-push-rules.md`).
