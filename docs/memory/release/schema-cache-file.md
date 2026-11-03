---
name: schema-cache-file
description: How pg_schema_has() writes data/temp/schema_*.json (merge with the file, Windows rename fallback) and why waf_table_has_column() and pg_mfa_table_exists() go through it (issues #6070, #6079)
module: core
---

## How is the schema cache file written?
- `pg_schema_cache_file_write()` (`fn/core.php`) after each table read from the database: it reads the file again, merges (`pg_schema_cache_merge()`), writes a temporary file and renames it over the cache.
- Merge: the file adds the tables and missing tables this request has no answer about; this request's answer wins for a table both know; a table `pg_schema_forget()` dropped in this request (`$state['forgotten']`) is not taken back from the file. Without it concurrent requests wiped each other's tables (last writer wins).
- Windows does not rename over an existing file, above all one another request is reading: the target is deleted and the rename tried again (as `pg_file_write_whole()` and `pg_lang_write_file()` do).

## What did it fix?
- On the Windows dev site (IIS, PHP 8.5) the file was written once and never again: every table not in that first write (auth_tokens, orders, contacts, user_mfa, waf_rate) was asked of `information_schema` on every request, about 6 queries and 6 ms each, 26 on edit_user (issue #6070).

## Why do waf_table_has_column() and pg_mfa_table_exists() ask pg_schema_has()?
- `waf_table_has_column()` ran `SHOW COLUMNS ... LIKE` each request (kept per request only; `_` a wildcard) and `pg_mfa_table_exists()` an `information_schema.TABLES` query: 107-115 probes, 66-93 ms on one edit_user request (issue #6079).
- `waf_table_has_column()` goes to `pg_schema_has()` when it exists, the db class has a connection and both names are plain (`[a-z0-9_]+`); otherwise (router phase before functions.php, an odd name) the old query, kept per request. `pg_mfa_table_exists()` is `pg_schema_has('user_mfa') && pg_schema_has('user_mfa_recovery')`.
- No caller adds a column at runtime and then asks again; migrations do not call them (an upgrade keeps answers for the request only anyway, schema-probe-rules.md).
- Tests: `tests/schema_has_test.php` (merge, write over an existing file, both callers answered from a primed cache with a connection that is not mysqli).
