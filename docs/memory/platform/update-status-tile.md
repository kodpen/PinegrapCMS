---
name: update-status-tile
description: When the Software Update tile of System Status may say "up to date": only from a recent check the update server answered, never from a failed, old or switched-off one.
module: update
---

## What does the tile decide from?
- `pg_update_check_state($available, $checks_on, $attempted, $answered, $now)` (`includes/fn/update.php`, pure, tested in `tests/update_check_state_test.php`) returns `available`, `off`, `never`, `failed`, `stale` or `current`. Only `current` is green with full score; the other five are warnings scored at half the update weight, like "Not checked".
- `$attempted` is `config.last_software_update_check_timestamp`: `software_update_check()` stamps it **before** the request, and the periodic check (`init.php`, every three days) waits on it, so it records an attempt, not an answer.
- `$answered` is the `cron_runs` row `software_update_check_answered` (`PG_UPDATE_CHECK_ANSWER_MARK`), moved by `pg_update_check_answered()` right after either path of `software_update_check()` (api2 and the Kodpen API) stores the flag. A failed check (cURL, HTTP, invalid answer, no cURL) leaves it, so an answer older than the attempt is `failed`. No schema change: `cron_runs` already holds such markers.
- `stale`: the answer is older than `PG_UPDATE_CHECK_STALE_AGE` (a week; one missed periodic check does not turn the tile, two do). `off`: `SOFTWARE_UPDATE_CHECK` is false, whatever the old answer said.

## What did it do before #7703?
- Green "The software is up to date." whenever the stamp was non-zero and the flag 0: a site whose update server was unreachable for months, or one with checks turned off years ago, kept the full score while a release waited.

## What happens after an upgrade or a channel change?
- Both reset the stamp to 0 (`includes/migrations/runner.php`, `maintenance.save.php`), so the tile says "Not checked" until the next request runs the check, which then records its answer.
