---
name: subscriptions-resume-charge-date
description: Resuming a paused store-run subscription whose charge date passed moves next_charge_at to the next series date; no charge on resume (PR #11447, #11426)
module: subscriptions
---

## What does a resume do to a charge date that passed while paused?
- `pg_subscription_transition_fields()` (`includes/fn/subscriptions.php`), on paused -> active with `next_charge_at` > 0 and <= now, writes `next_charge_at` = `pg_subscription_renewal_next_at(created_at, period, now)`: the first date of the subscription's own series after now. Nothing is charged on resume; the days between are free and the billing day the customer signed up with stays.
- Example: monthly from 1 January, paused before 1 February, resumed 28 March: next charge 1 April.
- A date still to come and a `next_charge_at` of 0 are left as they are. past_due -> active (a paid retry) is not a resume: the renewal sets its own date.
- One path for My Account, the operator's Subscriptions screen and the API: `pg_subscription_store_move()` -> `pg_subscription_set_status()`, inside the renewal's row lock (`subscriptions-move-lock.md`); `set_status` reads `next_charge_at`, `created_at`, `period` for it.
- Owner (2026-11-24): option (a) of #11447; no charge on resume, no schema change.

## What went wrong before?
- Pause kept `next_charge_at` and resume only set `active`, so a passed date was due at once (`pg_subscription_renewal_due_sql()`): charged on the resume day and again on the next series date a few days later (#11426).
- Running sites: rows already paused past their date get the new date only when resumed.

## Tested?
- `tests/subscriptions_test.php` `test_subscription_resume_moves_a_passed_charge_date`. Not run against a database.

Related: commerce/subscriptions-move-lock.md, commerce/subscriptions-renewal.md, commerce/subscriptions.md
