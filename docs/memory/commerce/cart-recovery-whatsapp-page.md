---
name: cart-recovery-whatsapp-page
description: Recovered carts: a WhatsApp single-use offer key as a touch and its use as clicked/restored, a pop-up taken up on a page (channel page, source popup), the left-cart test of 6 hours to 30 days
module: cart_recovery
---

## When is a WhatsApp message a touch?
- When it carries a single-use key (`platform/whatsapp-offer-codes.md`). `pg_whatsapp_enqueue()`, after a new row with `key_code_id`, calls `pg_cart_recovery_whatsapp_key_sent()`: channel `whatsapp`, source `template` + the template's id.
- The cart: the order given as enqueue option `order_id`, else the `contact_id` option's last changed incomplete order with active lines (`pg_cart_recovery_left_cart()`).
- A shared code is no touch: nothing links its use to the message. A duplicate enqueue writes nothing.

## Which cart may a message touch?
- Pure `pg_cart_recovery_is_left($status, $last_modified, $now)`: `incomplete`, `last_modified_timestamp` 6 hours (the abandoned-order mail's rule) to 30 days (`PG_CART_RECOVERY_LEFT_HORIZON`) ago.

## What does the key's use mark?
- `pg_order_complete()`, after the single-use key is used up, calls `pg_cart_recovery_key_used()`: the order's WhatsApp touch of the template whose outbox row holds the key gets `clicked_at` and `restored_at` (first time kept), as `?r=` marks one.
- A key used on another order than the touched one marks nothing; the completion rule still decides recovery by the completed order's own touches.

## Which senders pass a cart today?
- Only the inbox's template reply (`mail_phone.php`) passes `contact_id`. The flow box and the order moments pass neither, so their keys write no touch until a cart-left flow start (a later plan) names the order.

## How does a pop-up become a touch?
- The pop-up plan (PU9) calls `pg_cart_recovery_touch($order_id, 'page', 'popup', <pop-up id>)` when a visitor with a cart takes one up. The writer accepts it as it is; pure `pg_cart_recovery_touch_args()` checks the names (`test_cart_recovery_touch_args` in `tests/cart_recovery_test.php`).

## How was it verified?
- `tests/cart_recovery_test.php` (touch arguments, left test). No database in the cloud session: the enqueue touch, the left-cart query and the key's use were not run against MySQL.
