---
name: date-column-empty-comparison
description: Why DATE columns (ship_date, expiration_date, arrival_date) must be compared to '0000-00-00' and never to '', and what happens under strict mode and PHP 8.1.
---

## What is the "empty" value of a DATE column?
- Columns such as `ship_tos.ship_date`, `gift_cards.expiration_date`, `arrival_date` are `DATE NOT NULL DEFAULT '0000-00-00'`. Empty = the zero date, NOT an empty string.

## Which comparison is safe?
```sql
AND ship_date != ''            -- WRONG: MySQL strict: "Incorrect DATE value: ''"
AND ship_date != '0000-00-00'  -- RIGHT: the pattern used across the codebase
```
- Comparing to the zero date (`= '0000-00-00'`) is fine (works in 17 places). Only `''` is the problem.

## What happens with `''`?
- MySQL cannot cast `''` to DATE and errors in strict mode. A raw `mysqli_query()` then returns `false` (since 2026-09-12; before that, PHP 8.1's default `mysqli_sql_exception` threw and the page 500'd).
- Code that reads that `false` as "no row" lets the row vanish silently and renders the screen with missing data; through a helper (`db()`, `db_value()`) the request ends with "Query failed." (`db-helpers-and-error-contract.md`).
- It hit two places in cancellation (`process_order_cancellation()` shipping check, order_view `can_cancel` flag); neither fired earlier only because cancellation was broken.

Related: docs/memory/data/sql-silent-wrong-results.md, docs/memory/data/db-helpers-and-error-contract.md
