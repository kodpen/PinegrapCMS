---
name: schema-probe-rules
description: Code that reads a table/column added by a migration probes first with pg_schema_has() (cache file, invalidation, pg_schema_forget() for a recheck); which hot probes use it, the router's config check
---

## When must code probe before reading a table or column?
- Any code that reads a table/column a migration adds, outside the upgrade bridge, probes first. A missing table read by code still gives a wrong answer even though error reporting has been off since 2026-09-12.
- History: `get_system_status_checks()` `api_webhooks` row once returned 500 - `@mysqli_query` does not silence the exception.
- Older helpers: `waf_table_has_column()` for a column, `SHOW TABLES LIKE` for a table.

## What is the probe in new code (since 2026.4.8)?
- `pg_schema_has($table, $column)` (`includes/fn/core.php`); never `SHOW COLUMNS/TABLES ... LIKE`: `_` is a wildcard there (`LIKE 'visitor_content_hourly'` also matched a table `visitorXcontent_hourly`).
- Kept in-request and in `data/temp/schema_<db>_<code>.json` (`config.version` + `pg_code_version()`). The installer defines `VERSION` as `true`, so an install or an upgrade keeps answers for the request only.
- `pg_purge_caches()`, the end of an upgrade (`install_run_upgrades` `done`) and the installer's table swap (`pg_restore_swap()`: a fresh install, or a restored backup of the same version, which runs no upgrade) clear it with `pg_schema_cache_clear()`.

## What does a readiness check called with `$recheck` do?
- Calls `pg_schema_forget($table)` before asking: `pg_schema_has()` remembers a "no" for the request too. Without it the installer, which creates `visitor_content_hourly` and then runs the backfill with `$recheck = true` (migrations 2026.3.5, 2026.3.8), would read "no table" and skip the backfill.
- `pg_schema_forget()` loads the request's cache file first, so a later load cannot bring the forgotten answer back. Pure part `pg_schema_cache_forget()` (`tests/schema_has_test.php`).

## Which hot probes use it?
- `includes/fn/seo.php`: `pg_visitor_rollup_ready()`, `pg_sfv_stats_ready()`, `pg_sfv_legacy_table_exists()`, `pg_page_noindex_ready()`, `pg_multi_page_design_ready()`, `pg_page_custom_jsonld_ready()`, `pg_page_seo_extras_ready()`, `pg_recycle_bin_folder_id()`, `pg_default_upload_folder()`; `pg_offer_conditions()` (`ecommerce.php`); the three in `view_orders.php`; save for later in `cart_action.php` and `pg_order_item_active_sql()` (`ecommerce.php`, every cart reader); the offline payment cancel in `job.php` (PR #883).
- Sandbox, Turkish starter site (2026-10-20): 185 addresses as a visitor 9,351 → 8,736 queries, probes 615 → 0 (home page 73 → 68); as the administrator 193 addresses 18,496 → 17,754; `view_orders.php` 37 → 34.
- Signed-in requests (PR #898): `user` columns and `auth_tokens` (`includes/authentication.php`, `includes/fn/auth.php`; see upgrade-bridge-rule.md), root `seo.php` (`pg_seo_columns_exist()`, `pg_seo_speed_source_ready()`), panel home (`includes/dashboard/brief.php`, `widget_9.php`, `_orders_has_refund_columns()`). Administrator, 193 addresses: 17,752 → 15,949 queries, probes 1,818 → 17.

## How does the router check for the config table?
- It has no `functions.php`. After the character set and `sql_mode` it reads `SELECT * FROM config LIMIT 1` and hands the row to `waf_prime_config()`, so the firewall does not read it again. `SHOW TABLES LIKE 'config'` runs only when that read fails: no table gives the error page, a table that cannot be read (damaged) goes on as before.
- The check stays above `define('DB_CONNECTED')`: the error page's `output_control_panel_header_includes()` reads `config` when that constant is defined and exits with the MySQL error instead of drawing the page (PR #883).

## What about code that runs on the upgrade bridge?
- Bridge code must not assume new columns/tables at all: see docs/memory/release/upgrade-bridge-rule.md (probes live there, not behind the gate).
