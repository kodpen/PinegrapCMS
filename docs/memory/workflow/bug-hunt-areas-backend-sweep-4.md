---
name: bug-hunt-areas-backend-sweep-4
description: Fourth whole-backend sweep (2026-11-22): SQL error log while crawling, static scans, array and unknown-id probes, root files; fixed checkout recipient id, Visitors grouping, System Informations
---

## What did the fourth pass add?
- MariaDB's `sql_errlog` plugin (`INSTALL SONAME 'sql_errlog'`, `sql_error_log_warnings` on) logged every failed statement and warning while the 334 panel screens were crawled as `admin`; its new lines and those of `data/temp/php_errors.log` were kept per screen. `db_q()` and a direct `mysqli_query()` fail silently: this log is how to see them.
- Static scans: table names after FROM/JOIN/INTO/UPDATE against the schema; INSERT and UPDATE column lists and single-table WHERE columns against the tables; request values put into SQL without `escape()` or a cast; root files reading `$_POST` with no token check; divisions by an unguarded variable.
- The 182 starter site pages requested signed out with each of the 67 GET parameters the site files read sent as an array (`?name[]=1`), a fault narrowed down by halving the list. Send a browser user agent: `init_tracking()` skips a request it counts as automated.
- The 75 edit and view screens that read `id`, each with `?id=999999`, no id, `?id[]=1` and `?id=abc`.
- The 578 root files requested signed out, as a visitor.

## What did it find and fix?
- The checkout's shipping address and arrival and shipping method screens answered `?ship_to_id[]=1` with an error page (`escape()` of an array is a TypeError on PHP 8; 2026.4.10 too). Both drop it first with `pg_request_single_values()`: the "only during checkout" message, as with no id (#11059, `core/request-parameter-arrays.md`).
- The Visitors screen's Pages table grouped by `page_id`, the name the select list also gives `MAX(page_id)`: MariaDB warning 1052 on every load (the column won, the figures were right). The grouping names `visitor_content_hourly.page_id` (#11059).
- System Informations (`si.php`, public: `decisions-made-access.md`) read `LOCAL_ADDR` unchecked when `SERVER_ADDR` was missing: a warning on each load under PHP's built-in server. It reads whichever is set (#11059).

## What read clean, or is no fault?
- No other failed statement on the 334 screens. `database_engine.php` probes `information_schema_stats_expiry` and `@@transaction_isolation`, which MariaDB 10.x lacks: `@`-silenced, with a fallback to `@@tx_isolation`.
- Schema scans: only `custom_apps` (an old table a migration renames) and `sqlite_master` (the SQLite branch of `mysqldump.php`); `orders.notes` is written only after `pg_schema_has()`.
- No request value reaches SQL unescaped; every root POST handler checks the form token or a signed token of its own.
- Divisions: the zero cases are guarded (exchange rates above zero, installments above one, minimum hits, `max(1, …)`).
- Unknown ids: no PHP message, no failed query. `edit_sms_campaign.php`, `edit_offer.php` and `view_return.php` show their own "not found" with 200, not 404; ERP and course screens say the module is off.
- `?t[]=`, `?a[]=` and the `utm_*` tags as arrays: `get_page.php` drops them before `init_tracking()`.
- Root files: `health.php` answers 503 `cron_stalled` on a sandbox where cron never ran, as it should.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-backend-sweep-2.md, docs/memory/core/request-parameter-arrays.md
