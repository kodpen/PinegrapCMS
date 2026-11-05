---
name: db-q-converted-dashboard
description: The new dashboard's dashboard_layout rows (includes/dashboard/v2/layout.php) and the recent orders card on db_q(); what stayed raw, guard test.
---

## Which queries are converted, and how?
- `includes/dashboard/v2/layout.php` (7): the person's row read, the switch INSERT, the role lock and role default reads (`role IN (?, 9)`), the person's layout INSERT, the role default INSERT and DELETE. Every value the old SQL quoted is bound `(string)` (ids and times too, `(string) (int) $role`, the lock flag `'1'`/`'0'`), so the SQL text sent is the same; a null look is `(string) null`, `''` as `escape(null)` gave. `$look_sql` is a constant clause without a value and stays in the SQL text.
- `includes/dashboard/widgets/widget_8.php`, `pg_dashboard_recent_orders()` (1): the `LIMIT` bound bare `(int)`.
- The results are read with `mysqli_fetch_assoc()` / `mysqli_num_rows()` as before: `db_q()` returns the same mysqli result, and `false` on a failed query (no table yet), which the callers already treat as "no row".

## What stayed raw?
- `pg_dashboard_v2_share_rows()` (`SELECT role, locked … WHERE user_id = 0`), the cart list of `widget_8.php`, `includes/dashboard/v2/page.php` and the other widgets: no value in their SQL. `widget_18.php`'s file lists are completed by the integer/literal list `$fm_image_list`.

## What guards it?
- `tests/db_q_tail_test.php` lists both files (at least 7 and 1 `db_q()` calls, at most 1 raw each), refuses an escaped value in their SQL, counts one value per `?`, and checks the bound text of the layout INSERT and the recent orders `LIMIT`.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-tail.md
