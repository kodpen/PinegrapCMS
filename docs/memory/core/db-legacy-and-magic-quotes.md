---
name: db-legacy-and-magic-quotes
description: PHP 5 remnants removed (PR #3149): the DB_LEGACY second connection through mysql_* in db_connect() and router.php, init.php's magic quotes strip; a config that still sets DB_LEGACY
---

## What was removed?
- `db_connect()` (`includes/fn/core.php`) and `router.php` opened a second connection through the mysql extension (`mysql_connect()`, charset, `SET NAMES`) when `data/config.php` set `DB_LEGACY` and `mysql_connect()` existed, so custom code (hook code, PHP regions, custom layouts) could call `mysql_query()` without a link. The extension left PHP in 7.0; every query of the product goes through `mysqli` (`db::$con`), which never depended on it.
- `init.php` stripped slashes from `$_REQUEST`, `$_GET`, `$_POST` and `$_COOKIE` (`array_stripslashes()`) when `get_magic_quotes_gpc()` or the `magic_quotes_sybase` setting asked for it. Both are false on every supported PHP: the function answers false from 5.4, is deprecated in 7.4 (one E_DEPRECATED per request there) and removed in 8.0 (its `function_exists()` guard skipped it); the setting is gone since 5.4.
- `data/config(default).php` no longer lists `DB_LEGACY`. The owner's agreement to the removal was asked in #3150.

## What does a site whose config.php sets DB_LEGACY get?
- The installer never wrote it; a config holds it by hand or from the template. The `define()` is harmless: nothing reads the constant, and the site runs on `mysqli` as before.
- Only a server that loads the unmaintained PECL `mysql` extension ran the second connection (with the constant true). There, custom code that calls `mysql_*` without its own link loses it: it opens one with the `DB_*` constants, or moves to `db()` / `db_q()`.

## What is left, and what keeps it out?
- `install/index.php` keeps its own guarded copy of the strip (it loads no `init.php`; not fatal on PHP 8) until it is removed in its own change. `array_stripslashes()` stays: the installer and custom code may call it.
- `tests/php_dead_remnants_test.php` scans the product's tokens (vendored libraries and `data/` left out as in `tools/phpcs.xml`, the config template read): no `mysql_*` call, no `DB_LEGACY`, the magic quotes names only in `install/index.php`. PHPCompatibility fails a new `mysql_*` or `get_magic_quotes_gpc()` call anywhere but the installer: its baseline holds none outside it.
- The `mcrypt` path of `_pg_decrypt_legacy_credit_card_number()` stays while PHP 7.1 is supported (`plans/php-compatibility-and-bugs.md`).

Related: docs/memory/workflow/static-analysis.md, docs/memory/core/init-constants-and-config-file.md
