---
name: job-script-claims
description: Mailing job scripts (membership, ERP overdue, ERP accountant) take the cron_runs lock when started by crontab or by hand; which job scripts were left without one
module: cron
---

## Which job scripts take the job's lock themselves?
- `update_search_index.php`, `auto_backup.php`, `membership_job.php`, `erp_overdue_job.php`, `erp_accountant_job.php`, `erp_stock_alert_job.php`: unless dispatched (`PG_CRON_DISPATCH`, the dispatcher has claimed already) they call `pg_cron_claim('<name>')` before any work and `register_shutdown_function('pg_cron_release', '<name>')`. A held lock ends the request; the ERP scripts send a panel request to their screen.
- Why (bug hunt): each marks its work done only after the mail (`contacts.warning_expiration_date`, `config.erp_overdue_notify_sent_at` and `erp_invoices.customer_notified_at`, `erp_accountant_packages.sent_at`), so a crontab line beside the dispatcher, or a hand run during a scheduled one, mailed the same members, customers or accountant twice (the accountant pack was also built twice).
- The low stock and quote notices (`erp_stock_alert_job`) stamp their rows after the bell row: two runs rang the bell twice (scheduled-tasks hunt, #11394).

## Which ones were left without a lock, and why?
- `product_feed_job.php`: each run writes its own `.tmp-<random>` file and renames it over the feed (no half file); the Merchant Center mapping is `INSERT IGNORE`. Overlap only repeats the work.
- `seo_score_job.php`, `seo_analyze_job.php`, `seo_a11y_job.php`, `waf_ranges_job.php`: recalculations and refreshes whose result is the same when repeated.
- `email_campaign_job.php` and `recurring_payment_job.php` were not looked at here (other open work held them).
- Read for overlap and left without one (scheduled-tasks hunt): `push_job` (a second wake of a device shows the same banner, its tag collapses it), `workspace_recurring_job` (recurrences, reminders and the overdue mark are claimed row by row), `workspace_mail_job`, `translation_job`, `shipment_tracking_job` (an arrival is recorded once per address), `api_sync_job`.
- Also left without one: `erp_invoice_recurring_job` and `erp_expense_recurring_job` (each date claimed by moving `next_date`), `return_reminder_job` (stamps claimed).
- Not yet audited for overlap: `seo_search_job`, `iys_job`, `google_analytics_job`.

Test: `tests/job_script_claim_test.php`.
