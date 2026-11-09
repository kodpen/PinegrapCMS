---
name: unit-test-time-limit
description: tools/test.php gives every test the run's own time limit back, because product code sets @set_time_limit(120) and Windows counts it in wall-clock time, which stopped pushes there
---

## Why does every unit test start with the run's own time limit?
- Code the tests reach sets its own limit with `@set_time_limit(120)`: `pg_image_derivative()` (`includes/image_engine.php`), `pg_image_upload_cap_apply()`, `pg_image_editor_save()`, `pg_image_editor_render()`, `pg_site_icon_draw()`. Every call restarts PHP's timer. The runner itself has no limit: the command line ignores php.ini's `max_execution_time`, and the hook passes no `-d`.
- Windows counts that timer in wall-clock time, Linux in CPU time only. On the owner's machine the suite takes 4-5 minutes (a run on 2026-11-09: the image tests set the limit at 165 s of 291 s). That day a push stopped in the hook with `Maximum execution time of 120 seconds exceeded` in `panel_count_text_test.php`, whichever test was running 120 s after the last call; a retry passed. CI's step took about 2 minutes then, so the CPU count stayed under the limit there.

## What does the runner do now?
- Before each test it calls `@set_time_limit(PG_TEST_TIME_LIMIT)` behind `function_exists()`, since hosts may disable the function: the limit the run started with, none unless `-d max_execution_time` gives one. `tests/runner_time_limit_test.php` pins it: one test leaves 600, the next must see the run's own value.
- So a limit lasts only until the next test starts. A test that needs one sets it itself, and a hanging test still stops at its own limit.
- A branch cut from development before this change can still stop that way in the hook: push again.

Related: docs/memory/workflow/pre-push-checks.md, docs/memory/workflow/unit-test-runner.md
