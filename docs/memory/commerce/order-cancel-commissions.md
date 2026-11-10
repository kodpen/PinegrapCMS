---
name: order-cancel-commissions
description: Affiliate commissions on order cancel and restore (#4174): voided to ineligible with status_before_cancel, profiles stopped with disabled_by_cancel, hand edit clears it, list badge
module: ecommerce, commission_list
---

## What does cancelling an order do to its affiliate commissions?
- `process_order_cancellation()`, after the guarded status write (so a double cancel runs it once), calls `pg_order_cancel_commissions($order_id, $user_id)`: the order's `pending`/`payable` commissions become `ineligible` with the old status in `commissions.status_before_cancel`; its enabled recurring commission profiles get `enabled = 0`, `disabled_by_cancel = 1`, so the periodic job writes no more commissions. Recurring commissions the job already wrote carry the `order_id` and are voided too.
- A `paid` commission stays. One site log line (`pg_order_commission_cancel_line()`), none when nothing changed.
- Symptom fixed (#4174): a cancelled order's commission stayed `pending` and counted in Pending Total, so it could be paid by mistake.

## What does restoring the order do?
- `pg_order_restore_cancelled()` calls `pg_order_restore_commissions()`: a commission still `ineligible` whose marker is `pending`/`payable` gets that status back and the marker is cleared; a profile still disabled with `disabled_by_cancel = 1` is enabled again. Log line `pg_order_commission_restore_line()`.
- A commission set ineligible by hand before the cancel has no marker and stays.

## How does a hand edit interact?
- Saving `edit_commission.php` clears `status_before_cancel`, saving `edit_recurring_commission_profile.php` clears `disabled_by_cancel`: a restore never undoes the operator's later choice.
- `edit_commission.php` saves only `pending`, `payable`, `ineligible` or `paid` (`pg_commission_posted_status()`, `includes/fn/commission_list.php`); anything else goes back to the screen with an error. Before #5113 an unknown value was stored as `''` (enum column, non-strict sql_mode) and the commission left every status total.

## What does the Commissions screen show?
- `pg_commission_list_cancelled_badge(orders.status)`: a "Cancelled order" badge beside the status on the commissions list and beside the order number on the recurring profiles list, for every status (a paid one is seen for clawback). Totals stay by status; no separate total.
- It reads `orders.status`, so commissions of orders cancelled before the upgrade are marked although their status did not move (no backfill).

## Which schema and edges?
- Columns: 2026.11.0 step 11.0.139 (`upgrade_2026_11_0_commission_cancel_marker()`). Every reader and writer probes `pg_schema_has()`; before the upgrade runs, the cancel changes nothing (a void that could not be restored would lose the status).
- No fifth status (the enum, API filter and My Account labels stay). A partial refund moves nothing; a full refund on the order screen cancels the order and so voids the commission (`order-status-moves.md`, PR #1500). An unpaid order cancelled by the job has no commission (written at completion). The voiding and the restore announce no webhook event; a hand edit or `POST /commissions/{id}` announces `commission.status_changed` (`api/api-commission-writes.md`).

Related: docs/memory/commerce/order-cancellation-flow.md, docs/memory/commerce/order-cancel-gift-cards.md
