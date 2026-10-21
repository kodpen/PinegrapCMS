---
name: order-returns-refund
description: Refund of a received return (PR #936): driver or recorded, suggested amount, the lock and revert, what is written, the ERP return invoice hand-off, the refund mail, the daily reminder job
---

## How is a return refunded?
- `view_return.php` draws the Payment Refund card only on `received`; post `action=refund` → `pg_return_refund($id, amount, method, reference)`.
- `driver`: the order's own driver (`pg_payment_refund_driver()`) under the order screen's conditions (`transaction_id`, not cancelled/incomplete, money left); `pg_payment_call(…, 'refund', [orders row, minor units, order_items rows])`, its `log` lines written, a refusal shows the service's words. `manual`: money given back another way (bank transfer, cash), optional reference; 0 marks the return refunded when the order screen already paid it back.
- Amount: `pg_return_amount_cents()` (dot or comma, two decimals), more than 0 for a driver, never above `total - refunded_amount`.

## What does the card suggest?
- ERP off: `pg_return_refund_estimate()` from order_items (unit price, the line tax's share, the order discount spread by line value with the tax taken on what is left). ERP on: `erp_order_refund_amount()` on `erp_order_refund_lines()`, the return invoice's own figure. Shipping is never included; staff add it for a whole return.
- Invoice lines are matched to order lines by position and product (`pg_return_erp_line_map()`; `erp_order_lines()` writes goods first, in order line order). No match → ERP amount and hand-off skipped, estimate kept.

## How is a double payment prevented?
- Under `GET_LOCK(pg_return_lock_name(order))`: re-read, require `received`, move to `refunded`, then call the driver; a refusal puts it back to `received` (`refunded_at = 0`). A second click sees `refunded` and is refused.

## What is written?
- Money moved (amount > 0): `orders.refunded_amount += amount`, `order_refunds` row (`refund_type` `return` or `return_manual`, `transaction_id` = driver's `refund_reference` or the typed reference, `notes` "Return request #N"). Order status and totals stay (a return is not a cancellation; the order screen's full refund still cancels).
- `pg_audit('update', 'order_return', …)` with refund_amount, refund_method, refund_reference; one activity line; the customer's mail `pg_return_send_refund_mail()` (amount > 0; "the money goes back the way you paid" only for a driver).

## What about the ERP return invoice?
- An order with an issued invoice and a person with ERP rights (`pg_return_erp_hand_off($user)`: ERP on and role < 3 or `manage_erp`) go on to `add_erp_return.php?invoice_id=…&back_order=…&qty[line]=…`, filled in with the same lines, as the order screen's refund does; nothing is issued until they save.
- The note under the Payment Refund card asks the same helper (PR #1135, issue #973): it promises the invoice screen only to them; anyone else reads that someone who uses the ERP must issue the return invoice after the refund. Before, every user was promised a screen that only opened for ERP users, and nobody was told the invoice was still to be issued.

## How are staff reminded?
- `return_reminder_job.php` (daily, light lane; registry row in `pg_cron_jobs()`): `pg_return_remind_refunds()` rings `order_return` with `order_total` `refund_due` (from `PG_RETURN_REMIND_DAYS` = 3 days before `pg_return_refund_due()`) and `refund_overdue` (after it), once per return and stage, checked against the notification rows. Statuses `shipped` and `received`.
- `pg_return_move()` to `shipped`/`received` calls `pg_return_reminder_job_switch_on()`: appends the job to `config.job_dispatch` when the general job dispatches (the product feed pattern).

## Tests?
- `tests/returns_test.php` (amount, estimate, line map, reminder stage, refund mail). Sandbox runs, the iyzico driver with a stand-in HTTP client and the ERP hand-off: PR #936 body.
