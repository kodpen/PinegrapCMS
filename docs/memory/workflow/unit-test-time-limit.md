---
name: unit-test-time-limit
description: Time in unit tests: tools/test.php gives each test the run's time limit back (Windows counts set_time_limit in wall-clock time); a speed assertion measures CPU time, not the wall clock
---

## Why does every unit test start with the run's own time limit?
- Code the tests reach sets its own limit with `@set_time_limit(120)`: `pg_image_derivative()` (`includes/image_engine.php`), `pg_image_upload_cap_apply()`, `pg_image_editor_render()`, `pg_site_icon_draw()`, in 14 tests. Every call restarts PHP's timer. The runner itself has no limit: the command line ignores php.ini's `max_execution_time`, and the hook passes no `-d`.
- Windows counts that timer in wall-clock time, Linux in CPU time only. On the owner's machine the suite takes 4-5 minutes (a run on 2026-11-09: the image tests set the limit at 165 s of 291 s). That day a push stopped in the hook with `Maximum execution time of 120 seconds exceeded` in `panel_count_text_test.php`, whichever test was running 120 s after the last call; a retry passed. CI's step took about 2 minutes then, so the CPU count stayed under the limit there.

## What does the runner do now?
- Before each test it calls `@set_time_limit(PG_TEST_TIME_LIMIT)` behind `function_exists()`, since hosts may disable the function: the limit the run started with, none unless `-d max_execution_time` gives one (#8386). `tests/runner_time_limit_test.php` pins it: one test leaves 600, the next must see the run's own value.
- So a limit lasts only until the next test starts. A test that needs one sets it itself, and a hanging test still stops at its own limit.
- A branch cut from development before this change can still stop that way in the hook: push again.

## How does a test assert that something is fast enough?
- In the process's own CPU time, `getrusage()` user plus system, not `microtime(true)`: sessions run their tests on one machine at once, and that adds to the wall clock while the test waits for a processor, not to its CPU time. Read `ru_utime`/`ru_stime` `.tv_sec`/`.tv_usec` only when set (Windows has fewer fields) and fall back to the wall clock only when there are no times.
- CPU time still grows on a busy machine (a shared core, contended file system work: up to three times as much in #12089), so the fastest of a few tries counts, stopping at the first inside the limit. Waiting (sleep, I/O) is no CPU time: a slowdown made with `usleep()` is not seen.
- Example: `tests/security_scan_round_test.php`, whose wall-clock budget stopped pushes (#12080, `waf/security-scan-test-round.md`). A hang guard with a margin of a hundred times or more stays on the wall clock: `ods_cell_ceiling_test.php` and `sheet_read_test.php` take milliseconds against 5 and 10 s.

Related: docs/memory/workflow/pre-push-checks.md, docs/memory/workflow/unit-test-runner.md
