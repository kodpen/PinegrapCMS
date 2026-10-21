---
name: order-returns-reminders
description: Daily refund reminder of customer returns (return_reminder_job.php): the two stages, the once-only stamps on order_returns (step 11.0.25), how the job is scheduled
---

## How are staff reminded?
- `return_reminder_job.php` (daily, light lane; registry row in `pg_cron_jobs()`): `pg_return_remind_refunds()` rings `order_return` with `order_total` `refund_due` (`PG_RETURN_REMIND_DAYS` = 3 days before `pg_return_refund_due()`) and `refund_overdue` (after it), once per return and stage, statuses `shipped` and `received`. `create_notification()` also queues it for subscribed devices.
- Stage from `pg_return_reminder_stage($deadline, $today)`; the column that records a stage from `pg_return_reminder_stamp_column($stage)`.

## Why is the stage stamped on the return?
- `order_returns.refund_due_notified_at` / `refund_overdue_notified_at` (unix time, 0 until rung; step 11.0.25, PR #1137, issue #971). The job claims a stage with `UPDATE … SET <col> = UNIX_TIMESTAMP() WHERE id = … AND <col> = 0` and rings the bell only when that changed one row, so two overlapping runs cannot both ring; a bell that could not be written sets the column back to 0 and the next run tries again.
- Before, "already reminded" was read from the `notifications` rows. The bell's "Delete all notifications" and a single delete (`pg_notification_delete()`) remove the row for everyone, so the next day's run wrote the same reminder again for every staff member and device: once per clear in the due stage, and in the overdue stage until the refund was made. Sandbox, return shipped 12 days earlier: development rang `due` again on day 2 and `overdue` again on day 5 after a clear; with the stamps each stage rang once.
- A database that recorded 2026.11.0 before the step has no columns: the job rings nothing until `config.version` is set back and the upgrade rerun (release/release-status.md).

## How does the job get scheduled?
- `pg_return_move()` to `shipped`/`received` calls `pg_return_reminder_job_switch_on()`: appends the job to `config.job_dispatch` when the general job dispatches (the product feed pattern).

## Tests?
- `tests/returns_test.php`: `test_returns_reminder_stage()`, `test_returns_reminder_stamp_column()`. The job itself needs a database: the sandbox run above.
