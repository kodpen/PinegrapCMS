---
name: db-legacy-and-magic-quotes
description: PHP 5 remnants removed (PR #3149, #3195): the DB_LEGACY mysql_* connection in db_connect() and router.php, the magic quotes strip of init.php and the installer; a config that still sets DB_LEGACY
---

## What was removed?
- `db_connect()` (`includes/fn/core.php`) and `router.php` opened a second connection through the mysql extension (`mysql_connect()`, charset, `SET NAMES`) when `data/config.php` set `DB_LEGACY` and `mysql_connect()` existed, so custom code (hook code, PHP regions, custom layouts) could call `mysql_query()` without a link. The extension left PHP in 7.0; every query of the product goes through `mysqli` (`db::$con`), which never depended on it.
- The magic quotes strip (`array_stripslashes()` when `get_magic_quotes_gpc()` or the `magic_quotes_sybase` setting asked for it): of `$_REQUEST`, `$_GET`, `$_POST` and `$_COOKIE` in `init.php` (PR #3149), and of `$_GET`, `$_POST` and `$_COOKIE` in `install/index.php`, which loads no `init.php` (PR #3195). Neither ever asked on a supported PHP: the function answers false from 5.4, is deprecated in 7.4 (one E_DEPRECATED per request there) and removed in 8.0 (its `function_exists()` guard skipped it); the setting is gone since 5.4. So no request value changes.
- `data/config(default).php` no longer lists `DB_LEGACY`. Decided in #3150 (architect, `workflow/decisions-made-product-3.md`).

## What does a site whose config.php sets DB_LEGACY get?
- The installer never wrote it; a config holds it by hand or from the template. The `define()` is harmless: nothing reads the constant, and the site runs on `mysqli` as before.
- Only a server that loads the unmaintained PECL `mysql` extension ran the second connection (with the constant true). There, custom code that calls `mysql_*` without its own link loses it: it opens one with the `DB_*` constants, or moves to `db()` / `db_q()`.

## What is left, and what keeps it out?
- `array_stripslashes()` (`includes/fn/core.php`) stays although nothing in the product calls it any more: custom code may.
- `tests/php_dead_remnants_test.php` scans the product's tokens (vendored libraries and `data/` left out as in `tools/phpcs.xml`, the config template read): no `mysql_*` call, no `DB_LEGACY`, no magic quotes function or ini name, the installer included. PHPCompatibility fails a new `mysql_*` or `get_magic_quotes_gpc()` call anywhere: its baseline (`tools/phpcs-baseline.json`) holds none.
- The `mcrypt` path of `_pg_decrypt_legacy_credit_card_number()` stays while PHP 7.1 is supported (`plans/php-compatibility-and-bugs.md`).

Related: docs/memory/workflow/static-analysis.md, docs/memory/core/init-constants-and-config-file.md
