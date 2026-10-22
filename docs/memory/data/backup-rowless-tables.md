---
name: backup-rowless-tables
description: Tables every backup dumps without rows, the SEO, search and log tables that keep theirs and why, and how Search Console refills after a restore.
---

## Which tables does a backup dump without rows?
- `pg_backup_rowless_tables()` in `includes/fn/backup.php`: `visitors`, `visitor_texts` (its referer/user agent list, #489), `visitor_content_hourly`, `submitted_form_views`, `submitted_form_view_stats` (#434), `waf_log`, `perf_log`, `api_request_log` (#438), `seo_search_daily` (#480).
- Their CREATE TABLE is dumped, so a restored site has them empty and fills them again. The `sql.sql` note, the size estimate (`pg_backup_estimate_bytes()`) and the screen notes (Backup Manager, Settings › General › Backups, File Manager › Backups) all read or describe this list; change them together.

## Why is a table rowless?
- Its rows grow with traffic, not with content, nothing else reads them, and they can be produced again: visits and requests arrive again, Search Console keeps 16 months at Google.
- Symptom it fixes: a client's 950 MB backup could not be restored (incident 2026-10-14); the bulk was rows like these.

## Which similar tables keep their rows, and why?
- `visitor_stats_daily`, `visitor_stats_hourly`, `perf_stats`: summaries the reports and dashboard read; small.
- `log`, `erp_audit_log`, `erp_edoc_log`, `audit_log`, `consent_log`: who did what (a consent row is the proof the consent was given). `cron_runs`: the jobs' watermarks.
- `seo_issue`, `seo_link`: findings and link graph behind the `seo_*_score` columns on every page and product; without rows the scores would point at nothing until each item is checked again. They grow with content.
- `search_items`: the advanced site search index, rebuilt only by `update_search_index.php`. `search_results_pages`: page settings.
- There is no 404 or site-search query log table.

## How does Search Console refill after a restore?
- The rows are gone but `config.search_console` still says how far the download had come (`synced_to`). `pg_gsc_sync()` asks `pg_gsc_resume_day()`: an empty `seo_search_daily` means start over, so the next `seo_search_job.php` run (or "Download now") reads the whole 90-day window, as on first link.
- Days older than 90 are not fetched again; the screen offers 7/28/90 days, so only the 90-day "previous period" comparison is short for a while.

## What must a change to the list check?
- Before adding a table here: check every reader, and whether anything (a watermark, a score column, a cache) assumes its rows exist. If unsure, keep the rows.
- Tests: `tests/backup_test.php` (list and kept tables), `tests/search_console_test.php` (resume after restore).

Related: docs/memory/visitors/visitor-retention-and-cleanup.md, docs/memory/platform/backup-upload-extraction.md
