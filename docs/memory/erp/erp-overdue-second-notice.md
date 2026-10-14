---
name: erp-overdue-second-notice
description: ERP overdue round 2 (migration 4.56): candidate states, second notice at threshold+30, snooze, bell/menu badge, customer e-mail switches and stamps.
---

## How does the second notice work? (migration 4.56)
- Candidates are `new / second / known / snoozed`.
- The second and final notice fires at delay ≥ threshold + 30 days (`erp_overdue_second_notice_days()`, constant 30, no setting); stamp `erp_invoices.overdue_second_notified_at`. A document already past threshold + 30 at its first notice gets both stamps at once.
- Bell row: `title` = new count, `form_id` = second count; the three sentences live in `pg_notification_display()`, and the summary mail headline also comes from there (`erp_overdue_notify_headline()`).
- Code on a site that has not run 4.56 falls back to single-notice behaviour (`erp_overdue_notify_followups_ready()`). Column existence is checked with `erp_overdue_column_exists()` (`WHERE Field =`, not LIKE).

## How does snooze work?
- `overdue_snoozed_until` (midnight INT). `erp_overdue_snooze()` accepts only an open sales invoice, a date after today, at most 365 days.
- A snoozed document does not appear in the summary at all.
- The invoice screen card reads the stage from `erp_overdue_invoice_state()`.

## What is the menu badge?
- `pg_erp_overdue_badge_count()` (`fn/ecommerce.php`): one COUNT on status + due date, same figure as the dashboard card, ignores the threshold. `output_menu()` has a general `badge` option; CSS `.pg-menu-badge`.

## How does the customer mail work?
- Store switch `erp_overdue_notify_customer` ships OFF; account switch `erp_accounts.overdue_notify_customer` ships ON.
- Sender/reply `ECOMMERCE_EMAIL_ADDRESS → EMAIL_ADDRESS`.
- A sent mail stamps `customer_notified_at`; a failed mail does not stamp and is never retried.

Related: docs/memory/erp/erp-overdue-reminders.md
