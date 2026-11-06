---
name: gift-card-recipient-check
description: The order preview refuses an order while a gift card unit has no recipient row with a valid e-mail; why a card used to be issued with no recipient and a 1971 end date
module: gift_card_recipients
---

## What does the order preview check before it submits?
- `order_preview.php` (not on a gateway return) loads `includes/fn/gift_card_recipients.php` and calls `pg_order_gift_card_recipients_missing($order_id)`: for every active gift card line, each unit `1 .. pg_order_gift_card_count(quantity)` needs its `order_item_gift_cards` row with a recipient address that `validate_email_address()` accepts. Any unit short: the page comes back with "Please enter a valid recipient email address for every gift card in your cart." and nothing is charged.
- Pure part: `pg_gift_card_recipients_missing($lines, $rows)` (`tests/gift_card_recipients_test.php`).
- The express order already required them (`express_order.php` validates every unit when submitted); the legacy cart only on its own Checkout button.

## What was the symptom?
- The designer cart (`cart_action.php`) saves only the recipient fields posted. Raising a gift card line's quantity and pressing Proceed to Checkout in the same post left the new units without a row; a visitor could also open the checkout without the legacy cart's Checkout button. The order then completed and `pg_order_complete_line_gift_cards()` read a null row: the card had no recipient (the e-mail campaign went to an empty address) and, with `ECOMMERCE_GIFT_CARD_VALIDITY_DAYS` set, `pg_order_gift_card_expiration(null, …)` gave `1971-01-01`, so the bought card was expired at once.
- `pg_order_gift_card_expiration()` now also counts a null, empty or invalid date from today (#7133, `commerce/gift-card-order-expiry.md`).
