---
name: scheduled-job-resume
description: Resumable dispatched passes (#4744): tick budget inside job.php's time limit, pg_cron_due_again(), the _done completion markers the health screens read, minute locks
module: cron
---

## How long may a dispatched pass work in one tick?
- `job.php` notes its start and limit (`pg_cron_clock()`): `JOB_TIME_LIMIT` in `data/config.php` when above 0, else `max_execution_time` (none on the command line). A job run alone uses `REQUEST_TIME_FLOAT`.
- `pg_cron_time_left($budget)` caps a pass's own budget (`SEO_ANALYZE_TIME_BUDGET`) by the time left, less a margin for the closing writes (a fifth of the limit, at most 15 s), never below 1 (`pg_cron_tick_budget()`, pure). The structure and accessibility jobs use it.
- Why: a hosting job runner or a Windows task stops `job.php` after a fixed time; a pass that worked past it lost its tick and kept its lock.

## What does an unfinished pass do at the end of its tick?
- `pg_cron_due_again($name, 60)`: `last_run_at` moved back to `now - interval + 60` (`pg_cron_due_again_stamp()`, never later than now), so the job is due in a minute instead of after its interval. `pg_cron_dispatch_finished()` keeps that time instead of stamping the tick.
- Jobs that waited longer still go first in their lane (`pg_cron_pick()`, smallest `last_run_at`). Between lanes the job due longer (last run plus interval) goes first, light on a tie (`pg_cron_lane_choice()`): compared on last run, a heavy pass asking to come back every minute carried a day-old stamp and won every tick while the light jobs waited.
- `seo_a11y_job` and `seo_analyze_job` lock for 600 s, not the default hour: a tick works one budget.

## How do the health screens tell a finished run from a stamped tick?
- The dispatcher stamps the job's row after every tick, finished, crashed or declined (`pg_cron_stamp()`), so the rotation moves. Only `pg_cron_ran()`, called by the job at its real end, also writes `<job>_done` (dispatched, not inline jobs: `pg_cron_done_marker()`).
- System Status, `pg_ops_alerts_check_cron()` and the accessibility screen's "Last full check" read `pg_cron_health_last_runs()`: the `_done` time stands for the job when present (`pg_cron_health_runs()`, pure). Before, a crashed index run or an accessibility pass cut at its budget showed healthy and "No problem found".
- A job that has not finished since the update has no marker yet and still reads its plain row. `health.php` and the API's meta still read the plain rows.

## Where does the accessibility pass keep its place?
- `data/temp/seo_a11y.json` written after every page, not only when the budget runs out: a killed process carries on after the last page checked.

Test: `tests/cron_resume_test.php`.
