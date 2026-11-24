---
name: job-abandoned-orders-lock
description: The general job's abandoned-order pass runs one at a time under its own cron_runs lock (job_order_abandoned), so two ticks never make two order_abandoned campaigns for one order
module: cron
---

## What does the pass do?
- In `job.php`, while an enabled `order_abandoned` campaign profile exists: the incomplete orders 6 hours to 7 days old with a contact address, no campaign of their own and an enabled product get `create_auto_email_campaigns()`, unless the contact had an `order_abandoned` campaign in the last week or completed a later order.
- An order with a campaign drops out through `NOT EXISTS email_campaigns.order_id`; nothing else marks an order as handled.

## How does it keep to one run?
- `pg_cron_claim(PG_ORDER_ABANDONED_LOCK, 600)` (`job_order_abandoned`, a row of its own in `cron_runs`, no catalogue job) before the orders are read; `pg_cron_release()` after the loop and from a shutdown function. A tick that finds the lock held skips the pass; the run that holds it covers the same orders.
- Before the 2026.4.8 upgrade (no `locked_until`) the claim answers true: no lock, as before.

## Why?
- The general job is not locked against itself: a tick running past the next minute (four passes of 20 s each run before this one), a second cron entry or a manager's run read the same orders, none with a campaign yet, and each made one: the customer got the abandoned-cart mail twice. Fixed in the scheduled-tasks bug hunt (#11394).

## What was left as it is?
- The order read has no LIMIT: an order the pass skips (contact mailed this week, opted out of a commercial profile) is read again on every tick for its week, and a LIMIT would let such orders crowd out new ones. A window or a cursor would change which orders get mail: not done in a hunt.

Test: `tests/cron_job_overlap_test.php`.
