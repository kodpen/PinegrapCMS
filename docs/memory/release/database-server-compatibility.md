---
name: database-server-compatibility
description: Which MySQL and MariaDB versions the install and the upgrade steps run on, why MySQL 5.6 and MariaDB 10.1 are refused, the 2022.2 DROP DEFAULT failure (issue #464) and how to test a server.
---

## Which database servers does Pinegrap run on?
- MySQL 5.7 and later (8.0.12 included) and MariaDB 10.2 and later. A fresh install (starter site at 2021.3, then every step up to the open version) passes on MySQL 5.7, 8.0.12, 8.0, 8.4, 9.4 and MariaDB 10.2, 10.3, 10.4, 10.5, 10.6, 10.11, 11.4, 11.8, and gives the same schema on all of them.
- MySQL 5.6 and MariaDB 10.1 are refused before a table is touched: `install_server_problem()` (runner.php, from `pg_db_server_version()` / `pg_db_server_supported()` in includes/fn/db_maintenance.php) answers the install form, its connection test, the pre-flight card and the first line of `install_run_upgrades()`. An unreadable version string is let through.

## Why are MySQL 5.6 and MariaDB 10.1 refused?
- From 2026.1.21 on the steps put UNIQUE keys and indexes on `VARCHAR(255)` utf8mb4 columns (1020 bytes). These servers allow 767 bytes per key unless `innodb_large_prefix` is on and every table is `ROW_FORMAT=DYNAMIC`; neither is their default and a shared host cannot change it. The upgrade stopped at 2026.1.21 with error 1071, so no site on them can be past that version.

## What failed on MySQL 5.7 (issue #464, PR #470)?
- Installs and upgrades stopped at version 2022.2 with `MySQL error 1101: BLOB, TEXT, GEOMETRY or JSON column 'custom_css' can't have a default value — ALTER TABLE config ALTER custom_css DROP DEFAULT`. MySQL before 8.0.13 and MariaDB before 10.2 refuse `ALTER … DROP DEFAULT` on any TEXT column, even one without a default; MySQL 8.0.13+ and MariaDB 10.2+ accept it, which is why the owner's MySQL 8 never showed it.
- `upgrade_to_2022_2()` now calls `install_drop_column_default()`, which reads `information_schema.COLUMNS` and sends `DROP DEFAULT` only when the column has a default (NULL on a nullable column counts as none). On MariaDB a literal TEXT default is still dropped. A site stuck at 2022.1.9 continues with "Try again" (or a fresh install starts over) once the files are updated.

## Does the sql_mode matter?
- Yes. The LiveSite-era steps add `MEDIUMTEXT NOT NULL DEFAULT ''` and `DATE … DEFAULT '0000-00-00'`; MySQL's default strict mode refuses them (1101, 1067). Every caller pins `SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'` and `install_run_upgrades()` sets it again itself. Under that mode they are warnings only, on MySQL and MariaDB alike.

## How is a server tested?
- `tools/check_install.sh [starter] [port]` with `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE` set: installs through the real screen (php -S + curl), then reinstalls over the same tables, and fails unless `config.version` ends on the last version of versions.php. It overwrites data/config.php, so run it on a copy of the tree or in CI.
- CI: `.github/workflows/install-check.yml` runs it on mysql:5.7, 8.0, 8.4 and mariadb:10.3, 10.6, 11.4.
- Re-running every step from 2022.1.9 over a fully upgraded schema is not a valid test: later steps drop tables that earlier ones create.

Related: docs/memory/release/upgrade-runner-behavior.md, docs/memory/release/migration-step-helpers.md
