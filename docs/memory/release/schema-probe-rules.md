---
name: schema-probe-rules
description: Code that reads a table/column added by a migration must probe first; use pg_schema_has($table, $column) (since 2026.4.8), its cache file and invalidation.
---

## When must code probe before reading a table or column?
- Any code that reads a table/column a migration adds, outside the upgrade bridge, probes first. A missing table read by code still gives a wrong answer even though error reporting has been off since 2026-09-12.
- History: `get_system_status_checks()` `api_webhooks` row once returned 500 - `@mysqli_query` does not silence the exception.
- Older helpers: `waf_table_has_column()` for a column, `SHOW TABLES LIKE` for a table.

## What is the probe in new code (since 2026.4.8)?
- Use `pg_schema_has($table, $column)` (`includes/fn/core.php`); do not write `SHOW COLUMNS ... LIKE`.
- The answer is kept in-request and in `data/temp/schema_<db>_<code>.json` (keyed by `config.version` + `pg_code_version()`).
- `pg_purge_caches()` and the end of an upgrade (`install_run_upgrades` `done`) clear it with `pg_schema_cache_clear()`.
- `get_system_status_checks()` `api_webhooks` probe was converted to it.

## What about code that runs on the upgrade bridge?
- Bridge code must not assume new columns/tables at all: see docs/memory/release/upgrade-bridge-rule.md (probes live there, not behind the gate).
