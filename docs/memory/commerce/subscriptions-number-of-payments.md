---
name: subscriptions-number-of-payments
description: What a product's Number of Payments (recurring_number_of_payments) counts on store-run subscriptions (#4623): N charges in all, the order's own charge included; legacy gateway profiles differ
module: subscription_renewal
---

## What does N count on a store-run subscription? (#4623)
- Decided (architect, 2026-11-01): `order_items.recurring_number_of_payments` = N is the total number of charges the customer sees on the receipt ("Number of Payments: N"). A line charged in the order (today-start) counts that charge as payment 1 and the renewal job charges N−1 more; a later-start line (nothing charged in the order, `subscriptions-later-start.md`) gets N job charges. After the last paid charge the row is `ended` with `next_charge_at` 0 and an event; 0 stays "until cancelled".
- Why: the receipt is the customer's contract; charging once more than it says takes money the customer was never told about.
- Running sites: a store-run row already past N ends at its next due date instead of being charged. No schema: N is read from the subscription's first order line, the charges made are the order's own charge plus the row's `renewed` events.

## Where is it enforced? (#4622)
- `pg_subscription_renewal_payments_left($row, $item, $renewed)` (pure): N less the order's own charge (not for a later-start line) and the `renewed` events (io `paid_count`); -1 for N = 0.
- `pg_subscription_renewal_run_row()`: none left → not charged; the row is saved `ended` (`next_charge_at` 0, `ended_at`, the save's `status_ended` event), outcome `ended`, and no mail (`status_ended` would mail "cancelled", wrong for a contract that ran out).
- One left → the paid plan (`$last` argument) ends the row: the last renewal order is written and the row closes in the same run. `pg_subscription_renewal_confirmed()` ends it the same way when 0 or 1 is left (the money was already taken).
- Symptom fixed: a product sold for N payments renewed until cancelled.

## How do the legacy gateways count it?
- Their profiles keep their own reading, untouched: Authorize.Net ARB (`totalOccurrences`), Payflow Pro (`TERM`), PayPal Payments Pro (`TOTALBILLINGCYCLES`), Sage and First Data (`installments`) take N on a profile that starts one period after an order that charged the line, so a today-start line is charged N+1 times; ClearCommerce (`TotalNumberPayments`) never charges the line in the order and takes N. Only the store-run path follows the receipt.

Related: docs/memory/commerce/subscriptions-renewal.md, docs/memory/commerce/subscriptions-later-start.md, docs/memory/commerce/subscriptions.md
