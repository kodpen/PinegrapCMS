---
name: order-returns-refund-columns
description: The refund kept on a return (order_returns.refund_*, step 11.0.34, owner 2026-10-24): columns, the one writer, the backfill from audit trail and order_refunds, OrderReturn.refund, panel
---

## Why is the refund kept on the return?
- Owner decision (2026-10-24): `OrderReturn` must carry what `order.return.refunded` carries (api/api-endpoint-rules.md: no field readable in the event but missing on the object). Before, the amount and the method reached only the event, the audit trail and an `order_refunds` note; a subscriber that lost the event could not read them back.

## Which columns?
- Step 11.0.34 (`upgrade_2026_11_0_order_return_refund()`; a general label, 11.0.80-11.0.89 are the Workspace's): `refund_amount` INT UNSIGNED (minor units; 0 is a refund recorded as 0), `refund_method` VARCHAR(10) (`driver`, `manual`; '' = nothing known), `refund_reference` VARCHAR(255) (the service's refund id or the typed reference), `refund_unclear` TINYINT (1: no clear answer from the service, commerce/payment-unclear-answers.md).

## Who writes them?
- Only `pg_return_refund()`, the one path to `refunded`: one UPDATE after the outcome stands (success, manual or unclear) and the `order_refunds` row, before the lock is let go, `WHERE status = 'refunded'`. A refusal goes back to `received` first and keeps nothing. Probed with `pg_schema_has('order_returns', 'refund_method')`: before the step the refund works as it did. Event payload, audit line and mail are unchanged.

## How were older refunds filled in?
- The step reads only returns with `refund_method = ''` and `refunded_at > 0` (re-runnable; one pass in chunks of 500 ids, the table is small). `pg_return_refund_backfill_values()` (pure): the latest audit line of the return with `refund_method` gives amount, method and reference (a reference left empty is not in the line: it only keeps changes); the `order_refunds` note gives `unclear` ("Return request #N (unconfirmed"). Without an audit line the `order_refunds` row gives them (`return` = driver, `return_manual` = manual, `transaction_id`).
- A row is the return's only by its exact note: `Return request #N` or `Return request #N (unconfirmed`; a `LIKE 'Return request #1%'` takes #12's refund.
- Found nowhere (a 0 refund whose audit line is gone): left empty; the API shows `method` null, the panel "not kept".

## What do the API and the panel show?
- `OrderReturn.refund` (`OrderReturnRefund?`): null until refunded or before the step; else `amount`, `method` (null when unknown), `reference` (null when ''), `unclear`. No extra query: `SELECT *` already reads them.
- `view_return.php` status card: amount, "Through the payment service" / "Recorded, made another way", the reference, a warning when unclear; "not kept" for an unknown one.
- The personal data export reads `order_returns` with `SELECT *`, so the four columns go into it, the reference too: the refunds section already carries the same id as `order_refunds.transaction_id`.

## Tests and sandbox?
- `tests/returns_refund_columns_test.php` (backfill parser, the #1/#12 trap, writer placement, the step's label), `tests/api_order_returns_test.php` (presenter, schema). Sandbox runs (before and after the step, run twice, new refunds): PR body.
