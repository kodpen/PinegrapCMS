---
name: content-translation-jobs-duplicates
description: Translation jobs (includes/translate/jobs.php): why translate-on-save opens no second job while one is open, and why a job skips texts translated meanwhile
---

## When does translate-on-save open a job?
`pg_tr_auto_update()` (a page saved in the visual editor, languages with "translate on save" and a ready engine) extracts the scope once and opens one job per language with the pending texts. Not when a job of the same language and scope is still open: status `queued`, `sent` or `running`. Texts the later save added are picked up by the next save or "Update translations" after that job ends.
The check and the insert run under `GET_LOCK('pg_tr_auto_' . md5(language|scope), 5)`, so two saves at the same moment do not open two jobs; a save that cannot get the lock in 5 s leaves that language to the other one.

## What does a job do with a text translated in the meantime?
`pg_tr_job_waiting()` first calls `pg_tr_job_skip_translated()`: every waiting item whose string now has a translation in the job's language (another job, the screen, the API, an import) is closed as `skipped` and counted in `done_count`. Every consumer reads items through it: the server run (`pg_tr_job_run_server()`, Google and Pinegrap AI), the browser package (`pg_tr_job_package()`) and Claude's items (`translate_api_jobs_items()`). A job whose texts are all translated finishes without an engine call: when skipping leaves nothing waiting, `pg_tr_job_skip_translated()` closes the job (`pg_tr_job_close_if_complete()`), and Claude's items endpoint also closes a job it finds empty. Before, Claude's routine got `[]` and stopped, the job stayed `running`, `pg_tr_jobs_run_due` queued it again every 30 minutes (each dispatch refreshed `claimed_at`, so the one-day limit never came) and translate-on-save opened no new job for that language and scope.

## Why?
Before #852 (PR #1031) the duplicate check looked at `queued` / `sent` only. A save while the first job was `running` (Pinegrap AI works three texts a call, a job takes several turns) opened a second job with the same texts, and the server run sent them to the paid engine again without looking whether they had been translated since: every extra save cost another round of the same texts.
