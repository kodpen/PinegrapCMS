---
name: decisions-made-product-15
description: Decisions list, product part 15 (2026-10-31 afternoon on): a cancelled order's affiliate commission is voided and comes back with the order
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## What happens to an affiliate commission when its order is cancelled? (#4174)
- Decided (architect, 2026-10-31): voided with the order. `process_order_cancellation()` sets the order's `pending`/`payable` commissions to `ineligible`, keeping the old status in `commissions.status_before_cancel`, and stops its enabled recurring profiles (`disabled_by_cancel = 1`); `pg_order_restore_cancelled()` puts both back where the marker is still set and the row is still `ineligible`/disabled; a hand edit clears the marker. A `paid` commission stays. The Commissions screen marks a cancelled order's commission with a badge read from `orders.status` (older cancellations too); totals stay by status. No backfill; a partial refund moves nothing; the four statuses stay (no `cancelled` status: an enum MODIFY and an API schema change). Reason: the cancellation's design already leaves no spendable gift card behind; a commission for a sale that did not happen is the same fault. Refused: a flag alone (still payable by mistake) and leaving it to the operator.
