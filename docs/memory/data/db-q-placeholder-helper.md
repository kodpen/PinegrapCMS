---
name: db-q-placeholder-helper
description: db_q($sql, $params) and its binder pg_db_bind(): how each ? is filled, what is refused, why it returns false like mysqli_query() instead of ending the request, the new-code rule.
---

## What does db_q() do?
- `db_q("SELECT id FROM page WHERE name = ? AND folder = ?", array($name, $folder_id))` in `includes/fn/core.php` fills the placeholders with `pg_db_bind()` and runs the query. Text building, not a prepared statement.
- `pg_db_bind($sql, $params, $escape)` is pure (`tests/db_q_test.php`): a string is quoted and escaped (`escape()` in `db_q()`), an int or finite float is written bare (float through `var_export()`, so no locale comma before PHP 8), a bool as 1/0, null as `NULL`. Values are taken in order; keys are ignored.
- It answers null (`db_q()`: logged, `false`) when the `?` count and the value count differ, or for an array, an object, INF or NAN.
- Every `?` in the template is a placeholder, also one inside a quoted literal: write such a literal as a bound value. A `?` inside a bound value is never expanded again.
- A numeric string stays quoted (`'42'`); cast to int first where a column compare needs a number.

## Why false on failure, not the end of the request?
- `db_q()` exists to convert the ~2,400 raw `mysqli_query()` calls (plan `refactor-candidates.md` item 4). Those read `mysqli_num_rows()` / fetch loops and carry their own `or output_error(...)` or `if (!$result)`. Returning the mysqli result and `false` like `mysqli_query()` (`MYSQLI_REPORT_OFF`) lets a caller switch without changing what it does on failure; `mysqli_error(db::$con)` still names the cause.
- So it differs from `db()` / `db_value()` / `db_items()`, which end the request through `pg_db_run()` (`db-helpers-and-error-contract.md`). It also skips the upgrade runner's `install_query_failed()` hook, as a raw call does. It returns `false` without a connection. It is counted in `pg_db_stats()` like the helpers.
- For a one-shape read where ending the request on failure is wanted, keep using `db_value()` / `db_item()` / `db_items()` with `e()`.

## Where is it used?
- New code that would call `mysqli_query()` with values uses `db_q()`. Old code switches when a file is touched for another reason; whole-file conversions go hottest first.
- Converted files, what each kept raw and the test that guards it: `db-q-converted-files.md`.
- `get_file.php` keeps its own db copies and does not get `db_q()` (`core/get-file-shared-code.md`, `tools/check_copies.php`).
