---
name: content-translation-jobs-duplicates
description: Translation jobs (includes/translate/jobs.php): no second translate-on-save job while one is open, texts translated meanwhile skipped, one run at a time per server job
---

## When does translate-on-save open a job?
`pg_tr_auto_update()` (a page saved in the visual editor, languages with "translate on save" and a ready engine) extracts the scope once and opens one job per language with the pending texts. Not when a job of the same language and scope is still open: status `queued`, `sent` or `running`. Texts the later save added are picked up by the next save or "Update translations" after that job ends.
The check and the insert run under `GET_LOCK('pg_tr_auto_' . md5(language|scope), 5)`, so two saves at the same moment do not open two jobs; a save that cannot get the lock in 5 s leaves that language to the other one.

## What does a job do with a text translated in the meantime?
`pg_tr_job_waiting()` first calls `pg_tr_job_skip_translated()`: every waiting item whose string now has a translation in the job's language (another job, the screen, the API, an import) is closed as `skipped` and counted in `done_count`. Every consumer reads items through it: the server run (`pg_tr_job_run_server()`, Google and Pinegrap AI), the browser package (`pg_tr_job_package()`) and Claude's items (`translate_api_jobs_items()`). A job whose texts are all translated finishes without an engine call: when skipping leaves nothing waiting, `pg_tr_job_skip_translated()` closes the job (`pg_tr_job_close_if_complete()`), and Claude's items endpoint also closes a job it finds empty. Before, Claude's routine got `[]` and stopped, the job stayed `running`, `pg_tr_jobs_run_due` queued it again every 30 minutes (each dispatch refreshed `claimed_at`, so the one-day limit never came) and translate-on-save opened no new job for that language and scope.

## Why?
Before #852 (PR #1031) the duplicate check looked at `queued` / `sent` only. A save while the first job was `running` (Pinegrap AI works three texts a call, a job takes several turns) opened a second job with the same texts, and the server run sent them to the paid engine again without looking whether they had been translated since: every extra save cost another round of the same texts.

## Can two runs work the same Google or Pinegrap AI job at once?
No. The Translations screen's run calls, a second open screen and the scheduled job (`translation_job`, every 5 minutes, `pg_tr_jobs_run_due()`) all call `pg_tr_job_run_server()`. It takes `GET_LOCK(pg_tr_job_lock_sql($job_id), 0)` (database name and job id, hashed) before it reads the waiting texts and lets it go at the end.
A run that finds the job taken works nothing and answers `busy`; the screen looks again 5 s later and shows the other run's counts, the scheduled job goes on to the next job. A lock answer of NULL (the server cannot lock) still works the job.
Before, two runs read the same waiting texts and sent each to the paid engine twice. The `running` claim also no longer reopens a job cancelled or closed since it was read.
