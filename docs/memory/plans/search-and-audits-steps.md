---
name: search-and-audits-steps
description: Steps SA1-SA5 of plans/search-and-audits.md: index isolation, overlapping runs, time limit and honest health, resumable index, one background renderer; claim as [plan:search-and-audits] SA<n>
---

## Which steps are there?
- S under 400 product lines, M about 1,000. SA1, SA3 and SA5 touch different files and may run side by side; SA2 and SA4 follow SA1 (`update_search_index.php`), SA6 follows SA3 and SA5. SA6-SA13: `search-and-audits-steps-2.md`.

- SA1 (M) Index survives a bad item. Done (#4733).
  - Pages, calendar events and classic form records drawn as designer records are (`pg_error_throws(true)`, `try`/`catch (Throwable)`/`finally` putting back `$_GET`, the buffer level, the flag). A failed item keeps its row (its `timestamp` set to the run's, so the sweep spares it), counted and named by `pg_search_index_failure_note()`, which gains the item's kind.
  - Files: `shell_exec` only after `function_exists()` (a name in `disable_functions` is "Call to undefined function" on PHP 8); `.doc` and `.ppt` read only an existing file with bytes (`fread()` on a failed `fopen()` is a `TypeError`, on 0 bytes a `ValueError`); each file in its own `try`, a failure keeps the old row.
  - A shutdown function writes one Site Log line when the run stops before its end: stage, last item started, `error_get_last()`. The finish window counts every kind.
  - `tests/search_index_isolation_test.php`. Memory: `designer/search-index-run-failures.md`.

- SA2 (S) Overlapping runs. Done (#PR).
  - The sweep deletes `timestamp < $timestamp`: a run started later keeps its rows when an earlier one ends first.
  - Button, crontab and dispatcher claim one `cron_runs` lock row through `pg_cron_claim()` (`includes/fn/cron.php`, the dispatcher's claim moved into it); a second start says a run is going (button) or leaves quietly (job).

- SA3 (M) Time limit, due again, honest health. Done (#4744).
  - `job.php` notes its start and its limit: `JOB_TIME_LIMIT` in `data/config.php`, else `max_execution_time` when set. `pg_cron_time_left()` caps every dispatched pass's tick, with a margin for the closing writes.
  - `pg_cron_due_again($name, $seconds)` moves `last_run_at` so the job is due again then; `pg_cron_dispatch_finished()` keeps it instead of stamping now. Jobs that waited longer still go first.
  - `pg_cron_ran($name . '_done')` only at a real end; System Status, `ops_alerts.php` and the accessibility screen's "Last full check" read it when present.
  - The accessibility pass writes its cursor after every page; resumable passes lock for minutes, not hours.
  - `tests/cron_resume_test.php`. Memory: `platform/accessibility-report.md` (its "handed out on the next tick" was not true), the `pg_cron_jobs()` comment.

- SA4 (M) Resumable index. After SA1-SA3 and SA5.
  - Stages (pages, events, classic records, designer records, files, sweep); a cursor in `data/temp/search_index.json` written after every item with the run's start time; the sweep only after the last stage, against that time. The item a killed tick left unfinished is skipped and named next time. Renders through `pg_background_render()`; `UPDATE_SEARCH_INDEX` stays set.
  - The panel window drives the same pass with short requests and shows progress.
  - Tests: the stage and cursor logic pure (`pg_search_index_next()`), the skip rule.

- SA5 (S) One background renderer. Done (#4734).
  - `pg_background_render()` taken out of `pg_seo_render_page()`: `catch (Throwable)` (today `Exception`: an `Error` ends the pass, and the queue and cursor never get past that page), output-buffer level restored. The structure and accessibility passes use it.
