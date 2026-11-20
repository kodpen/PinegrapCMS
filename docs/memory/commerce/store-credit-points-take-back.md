---
name: store-credit-points-take-back
description: An order's reward points taken back on cancel, full refund and line refund (#6256, decision #6306): recomputed from the lines, balance first, the converted rest off the newest points cards
module: store_credit_points
---

## When are an order's points taken back?
- On a cancellation (the order screen's full refund included) and on a line refund (owner decision on #6256, 2026-11-03, issue #6306). Not retroactive (only from this release on), no schema, no "pending points".
- The amount is recomputed from the order's lines (`pg_credit_points_order_lines()`, lines saved for later left out) with the grant's own `pg_order_line_reward_points()` through `pg_credit_points_of_lines()`, at today's product points and today's program switch: a product whose points changed is taken back at today's figure; a program switched off takes nothing back.
- `pg_credit_points_take_back()`: one guarded `UPDATE … - N WHERE user_reward_points >= N` (retried on a race), never below zero; the points no longer there were converted, so their worth at today's rate (`pg_credit_points_take_back_plan()`, rounded up in integer sums like the conversion (#8595), never less than a conversion gave) comes off the open `points` cards, newest first, then the one before it (`pg_credit_points_card_cuts()`), through `pg_credit_reduce()` (store-credit-model.md): a note line naming the order, the audit trail (`update`, or `revoke` when brought to 0). No rate set: the card part is skipped. One activity line.

## Where is it hooked?
- Hooks: `process_order_cancellation()` → `pg_credit_points_order_cancelled()` (not a cart, not a guest); `pg_return_refund()` → `pg_credit_points_lines_refunded()` with the return's quantities; `view_order.php`'s refund by lines (ERP) maps the ERP lines back to order lines (`pg_credit_points_erp_quantities()`, `pg_return_erp_line_map()`) and notes `Reward points taken back: N` on its `order_refunds` row (only when the `notes` column exists); without an invoice the lines given back follow it (`lines:<id>=<qty>`, `erp/erp-order-refund-lines-noted.md`).

## How is a double take-back avoided?
- Cancellation: takes the order's points less the lines of its refunded returns (`refunded_at > 0`) and less the points in those notes (`pg_credit_points_order_refunded()`); a line refund of a cancelled order takes nothing. The cancel count did not change with #10671.
- Line refunds (architect 2026-11-20, #10671, PR #PRNUM): each unit's points are taken once. `pg_credit_points_units_taken()` counts a line's taken units as min(quantity, paid + max(screen, recorded)): `screen` the order screen's refunds by lines (their `lines:` notes, `pg_credit_points_screen_noted()`), `paid` returns refunded with money, `recorded` returns recorded as refunded with 0, which match the screen's units first.
- A new refund takes only the units above that count (`pg_credit_points_units_to_take()`), in either order.
- Symptom fixed: order screen by lines A x 2 (200 points), then the return of A x 2 recorded with 0 took 200 points again.
- `pg_return_refund()` passes the return's id and amount; the order screen passes none (`view_order.php` unchanged). Counts come from `pg_credit_points_lines_counted()`; before the refund columns (11.0.34) a return counts as paid.

## What is not covered?
- An invoiced order's refund by lines notes no lines (its return invoice may never be issued and does not say which refund it came from), so its units count as none: a 0 recorded return of those lines still takes the points again. Open question to the architect in PR #PRNUM.
- A restore of a cancelled order does not give the points back (staff edit the balance on the user screen); returns refunded before this release are still subtracted on a later cancel.

## Tests?
- Tests: `tests/store_credit_points_take_back_test.php`.
