---
name: scheduled-job-resume
description: Resumable dispatched passes (#4744): tick budget inside job.php's time limit, pg_cron_due_again(), the _done completion markers the health screens read, minute locks
module: cron
---

## How long may a dispatched pass work in one tick?
- `job.php` notes its start and limit (`pg_cron_clock()`): `JOB_TIME_LIMIT` in `data/config.php` when above 0, else `max_execution_time` (none on the command line). A job run alone uses `REQUEST_TIME_FLOAT`.
- `pg_cron_time_left($budget)` caps a pass's own budget (`SEO_ANALYZE_TIME_BUDGET`) by the time left, less a margin for the closing writes (a fifth of the limit, at most 15 s), never below 1 (`pg_cron_tick_budget()`, pure). The structure and accessibility jobs use it.
- Why: a hosting job runner or a Windows task stops `job.php` after a fixed time; a pass that worked past it lost its tick and kept its lock.
- `max_execution_time` does not know the runner's limit: such a host sets `JOB_TIME_LIMIT` below it (the host's setting). Without it the tick budget (`SEO_ANALYZE_TIME_BUDGET`, 120 s) does not run out before the kill, and every tick of the search index ends inside an item (`designer/search-index-item-parts.md`).

## What does an unfinished pass do at the end of its tick?
- `pg_cron_due_again($name, 60)`: `last_run_at` moved back to `now - interval + 60` (`pg_cron_due_again_stamp()`, never later than now), so the job is due in a minute instead of after its interval. `pg_cron_dispatch_finished()` keeps that time instead of stamping the tick.
- The job due longer (last run plus interval) goes first, in its lane (`pg_cron_pick()`, catalogue order on a tie; the legacy dispatcher too) and between lanes (`pg_cron_lane_choice()`, light on a tie). Compared on last run, a pass asking to come back every minute carried a day-old stamp and won every tick: first over the light jobs, then (until the bug-hunt fix) over the hourly search index in the heavy lane for the whole structure or accessibility pass.
- `seo_a11y_job` and `seo_analyze_job` lock for 600 s, not the default hour: a tick works one budget.

## Whose lock does `pg_cron_release()` clear?
- Only the one this request's `pg_cron_claim()` took: the claim keeps the `locked_until` it wrote (`pg_cron_lock_stamp()`) and the release clears the row only while it still carries that stamp (`pg_cron_release_query()`, pure). No owner column: a later claim can only take an expired lock and writes a stamp at least a minute ahead, so stamps never repeat.
- Why (bug hunt): a run that outlasted its lock cleared the lock of the second run that had claimed it after expiry, letting a third start beside it. A release without a claim in the same request now does nothing.

## How do the health screens tell a finished run from a stamped tick?
- The dispatcher stamps the job's row after every tick, finished, crashed or declined (`pg_cron_stamp()`), so the rotation moves. Only `pg_cron_ran()`, called by the job at its real end, also writes `<job>_done` (dispatched, not inline jobs: `pg_cron_done_marker()`).
- System Status, `pg_ops_alerts_check_cron()`, the accessibility screen's "Last full check" and `GET /meta` (#8548) read `pg_cron_health_last_runs()`: the `_done` time stands for the job when present (`pg_cron_health_runs()`, pure). Before, a crashed index run or an accessibility pass cut at its budget showed healthy and "No problem found".
- A job that has not finished since the update has no marker yet and still reads its plain row. `health.php` reads only the dispatcher's own row, which has no marker.

## Where does the accessibility pass keep its place?
- `data/temp/seo_a11y.json` written after every page, not only when the budget runs out: a killed process carries on after the last page checked.
- The structure and accessibility passes have no skip rule for a page that itself ends the run (alone longer than the runner's limit, or a fatal error PHP cannot catch): left out as rare. The search index has one (`designer/search-index-resumable-pass.md`).

Tests: `tests/cron_resume_test.php`, `tests/cron_test.php`.
