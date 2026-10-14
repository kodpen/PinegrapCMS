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
- MySQL cannot cast `''` to DATE and errors in strict mode. The query returns `false` (since 2026-09-12; before that, PHP 8.1's default `mysqli_sql_exception` threw and the page 500'd; see the database rules).
- So the row silently vanishes: `db()` / `db_value()` return empty and the screen renders with missing data.
- It hit two places in cancellation (`process_order_cancellation()` shipping check, order_view `can_cancel` flag); neither fired earlier only because cancellation was broken.

Related: .claude/skills/pinegrap-veritabani/SKILL.md
