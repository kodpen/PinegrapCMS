---
name: checkout-arrival-date-offered
description: The shipping address step accepts only an arrival date the list offers (enabled, started, not ended): pg_arrival_dates_offered_sql() shared by the list count and the posted id check
module: shipping
---

## Which arrival date may a visitor post?
- Only one the list shows: `status = 'enabled'`, `start_date` today or earlier, `end_date` + `ECOMMERCE_END_OF_DAY_TIME` still ahead. `pg_arrival_dates_offered_sql()` (`includes/fn/shipping.php`) builds that condition.
- `shipping_address_and_arrival.php` (classic page and the Shipping Address widget) reads the posted id through it; an id outside the window (disabled, not yet started, ended, deleted) gets "The selected arrival date is not available. Please select another one." and the recipient row keeps no arrival date id.
- Express order already checked the posted id against its own list of active dates.

## Why?
- The step read any posted id, so a hand-made post saved a disabled or ended arrival date (with its code and date) on the recipient and searched shipping methods for it, though the store no longer offered it.

## What does the helper accept as the end-of-day time?
- `H:i` or `H:i:s`; anything else reads as `23:59:59`, so the setting never reaches the SQL unchecked.
