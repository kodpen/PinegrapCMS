---
name: gift-card-order-expiry
description: How a gift card bought in an order gets its expiration date (validity days on the calendar from the delivery date or today), and the 1971-01-01 fault it fixed
module: order_completion
---

## How is the expiry of an order's gift card computed?

`pg_order_gift_card_expiration($delivery_date, $validity_days, $now)` in
`includes/fn/order_completion.php`: `ECOMMERCE_GIFT_CARD_VALIDITY_DAYS`
counted on the calendar (`pg_return_add_days()`, no clock, so no DST step)
from the recipient's delivery date. When the card is sent at once
(`0000-00-00`), the date is NULL (no `order_item_gift_cards` row for that
unit), empty or not a real date (`pg_return_date()`), it counts from today
in the site time zone. No validity days: `''`, the card never expires.

## What did it fix?

The old code passed a missing date to `strtotime()`, got `false`, and
counted from 1970: with 365 days the card got `1971-01-01` and was expired
the moment it was issued (on PHP 8.1+ also a deprecation for NULL).

## What about the other issuing paths?

- Panel create screen: `pg_gift_card_form_default_expiration()` only
  pre-fills the field from `time()`; the stored value is what the staff
  member submits (`pg_gift_card_form_expiration()`).
- API: `expires_on` is a validated `datetime` parameter (router), so it is
  never 0 or false.
- Store credit: `pg_credit_expiration_date()` maps `expires_at = 0` to
  `0000-00-00` (never).
