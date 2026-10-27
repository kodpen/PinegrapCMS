---
name: privacy-log-retention
description: How long audit_log and consent_log keep lines: config.audit_log_retention_years (11.0.47), the Audit Trail settings card, the daily job pass, why the answer in force stays
module: privacy_retention
---

## What is kept, and for how long?
- `audit_log`: the years in `config.audit_log_retention_years` (2026.11.0, 11.0.47, `TINYINT UNSIGNED`, default 2; 0 keeps every line, otherwise 1-10, `pg_privacy_retention_limits()`). The owner approved the column (2026-10-27).
- `consent_log`: history past 3 years (the plan's fixed period; no setting). The newest line of each person + channel + purpose is never removed, however old: it is the answer in force and its proof (`pg_consent_log_current()` reads exactly that line). A cookie line with no person (no token, user or contact) proves nothing about anybody and goes at 3 years like the rest.
- "Same person" is the exact triple `person_token`, `user_id`, `contact_id`: a line written later with only the token does not supersede one with token and user, so in doubt a line is kept, never removed.

## Who removes them?
- `job.php` calls `pg_privacy_retention_due()` / `pg_privacy_retention_run(5)` (`includes/fn/privacy_retention.php`): once a day, five seconds a tick, audit lines first (`DELETE … WHERE created_at < cutoff LIMIT 1000` on `idx_created`), then consent ids from `pg_consent_log_trim_query()` deleted by id (MySQL refuses a DELETE whose subquery reads the same table). The state file `data/temp/privacy_retention.json` is written only when both are through, so a backlog goes over the next ticks. A failed statement ends the pass and it is asked again.
- A pass that removed lines writes one Site Log line. The removal itself is not an audit line.
- The cutoff is `strtotime('-N years')` from now in the site's time zone (`pg_privacy_retention_cutoff()`).

## Where is it set?
- Settings › Security › Audit Trail (`pgset-audit`, drawn only once the column exists; the card links to `view_audit_log.php`). `security.save.php` checks the field with `pg_audit_retention_years_from_input()` before anything is written and writes the column on its own; a change is itself in the settings audit line. `pg_audit_retention_years()` reads it back, clamped, the default before the step has run.

## Tests?
- `tests/privacy_retention_test.php`: the input rule, the cutoff, the consent query's conditions, the bounds.

Related: docs/memory/core/audit-trail-screen.md, docs/memory/core/consent-log-and-audit-trail.md, docs/memory/plans/privacy-data-pack-audit-log.md
