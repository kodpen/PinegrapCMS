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
- Only the inbox's template reply (`mail_phone.php`) passes `contact_id`. The flow box and the order moments pass neither, so their keys write no touch until a cart-left flow start (a later plan, `core/campaign-profile-flow-gaps.md`) names the order.

## How does a pop-up become a touch?
- A take-up ("taken") of any pop-up, not only a cart-audience one, by a visitor whose session order holds a line not saved for later (PU4's cart test, `pg_auto_dialog_session_has_cart()`) writes `pg_cart_recovery_touch($order_id, 'page', 'popup', <pop-up id>)` (PR #9926).
- Place: `page_view.php`'s pop-up event branch calls `pg_cart_recovery_popup_taken()` right after the count, behind the count's gates (rate limit, crawler, staff, consent, signature): an event that is not counted writes no touch.
- The decision is pure `pg_cart_recovery_popup_touches($event, $order_id, $line_count)` (`test_cart_recovery_popup_touches`). The same order and pop-up are written once (the writer's rule).
- Architect (2026-11-16, agent channel message 6312): the 6-hour left-cart test does not apply at touch time; it is the rule of a sent message. Whether the cart counts as recovered is decided by the completion rule.

## How was it verified?
- `tests/cart_recovery_test.php` (touch arguments, left test, the pop-up decision). No database in the cloud session: the enqueue touch, the left-cart query, the key's use and a live pop-up take-up were not run against MySQL.
