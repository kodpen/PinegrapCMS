---
name: audit-trail-recycle-bin
description: How Recycle Bin work appears in audit_log: the daily purge recorded as a job (pg_audit_as_job), not as the operator whose visit ran it
---

## Who is the actor of the daily purge?
- `pg_recycle_purge_if_due()` (`view_folder_and_files_f.php`) runs once a day on the first file manager list (`explorer_list`) or catalog list (`explorer_catalog_list`) of the day and deletes up to 30 bin entries past the retention window (pages, files, products, short links, styles).
- Its lines are written with `source = 'job'`, `user_id = 0`, no username: the loop runs inside `pg_audit_as_job(true)` and restores the previous state in `finally` (PR #PRNUM).
- Before, every purged record was written as deleted by whoever opened the screen that day (an admin who only listed a folder showed up as the one who deleted, a KVKK/GDPR accountability error).
- A manual "Delete permanently" or "Empty Recycle Bin" (`explorer_hard_delete`) stays `panel` with the operator.
- The site log (`log_activity()`: "N item(s) were purged…", "file (…) was deleted") still names the visitor; it is free text, not the trail.

## How is other housekeeping recorded as a job?
- `$was = pg_audit_as_job(true);` before the work, `pg_audit_as_job($was);` after it (in `finally`). `pg_audit_actor()` checks the switch before the API app and the session, so the switch wins everywhere.
- From the command line (`job.php`) or a request without a user the actor is `job` anyway.

## Tests?
- `tests/audit_log_test.php` `test_audit_as_job_switch()`: off by default, returns the previous state, actor is `job` while on. The panel-user case needs a request; it was run on the sandbox (list after backdating a bin entry → `job`, manual delete → `panel`).

Related: docs/memory/core/audit-trail-writers.md, docs/memory/core/consent-log-and-audit-trail.md, docs/memory/commerce/short-link-recycle-bin.md
