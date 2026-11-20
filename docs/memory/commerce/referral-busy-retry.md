---
name: referral-busy-retry
description: A referral reward whose referrer's lock was busy at completion: kept in config_text referral_pending and tried again by the general job; limits and why only busy orders
module: referral
---

## What happens when the referrer's lock is busy?
- `pg_referral_order_completed()` answers `busy` when `GET_LOCK('pg_referral_<contact id>')` waits past `PG_REFERRAL_LOCK_WAIT`. The order id goes into the `config_text` row `referral_pending` (`pg_referral_pending_note()`) (#10674, #10631). Before, the reward was lost for good: completion runs once.

## How is it kept?
- `{"order id": first busy time}` as JSON; an empty list is `{}`, as `pg_config_text_claim()` writes no ''. Every change reads the row fresh and writes with `pg_config_text_claim()` (`pg_referral_pending_change()`, five tries), so a completion and the job never lose each other's change. No schema.
- At most 200 orders (`PG_REFERRAL_PENDING_MAX`), the oldest dropped first; an entry older than 7 days (`PG_REFERRAL_PENDING_DAYS`) drops. A second busy keeps the first time.

## Who tries it again?
- The general job (`job.php`, every minute, when `ECOMMERCE`) calls `pg_referral_pending_run(10)`: one read while the list is empty; else up to ten orders, each through `pg_referral_order_completed()` unchanged, so every check runs again and the `done` guard stops a second reward. Any answer but `busy` takes the order off.
- No new job in `includes/fn/cron.php` and no change in `order_completion.php`.

## Why only busy orders?
- Architect (2026-11-20, agent channel message 10330): a pass over every referral order without a row would let refused orders through later: one refused for a full month would be rewarded the next month, one completed while the programme was off once it is on.

## Tests?
- `tests/referral_test.php` (`test_referral_pending_list`, `test_referral_pending_is_wired`).
