---
name: db-q-converted-campaigns-visitor-report
description: E-mail campaign files and the visitor report converted to db_q() (PR #2331): how values are passed, what stayed raw and why, the guard test.
---

## Which files are converted, and what stayed raw?
- PR #2331: `add_email_campaign.php` 13, `view_visitor_report.php` 10, `send_email_campaign.php` 8, `edit_email_campaign.php` 7, `email_campaign_job.php` 5 calls with values.
- `(string)` where the old SQL quoted a value, also for values written unescaped into quotes (`'$email_campaign_id'`, recipient ids, the generated `reference_code`), now escaped once: same text for ids and codes. `(int)` for the recipient's contact read (`WHERE id = " . (int) $contact_id`), which wrote the id bare.
- `email_campaign_job.php` runs some of its queries between `LOCK TABLES` and `UNLOCK TABLES`; `db_q()` sends only the one query, so it touches no unlocked table.
- Raw stay the queries without values, the job's table locks (`$sql_calendar_event_locks`), its batch read with the configured `LIMIT $number_of_emails` (a constant, not a request value), the campaign update completed by `$sql_body` / `$sql_bcc_email_address`, and the visitor report's own queries completed by `$where` (one unbuffered with `MYSQLI_USE_RESULT`, which `db_q()` does not take).
- The saved report rows of `view_visitor_report.php` (`visitor_reports`, `visitor_report_filters`) convert like those of `view_order_report.php`; the report still reads `visitors` rows, as before (`visitors/` key rules).

## What guards it?
- `tests/db_q_campaigns_visitor_report_test.php`: no `escape()` / `e()` in a `db_q()` SQL, no value written into a remaining raw query, one value per `?`, no quoted `?`, and bind examples.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-commerce.md
