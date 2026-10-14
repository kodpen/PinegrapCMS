---
name: upgrade-bridge-rule
description: Upgrade bridge rule: code lands before the schema, so login, install lock and initialize_user() must run on the previous schema; gate and mandatory test.
---

## Why can code not assume the new schema?
- `software_update.php` replaces files and runs the upgrade afterwards; on a site where files were copied by hand the upgrade may not have run at all. In that gap this version's code runs on the previous version's tables and session format.
- 2026.4.4 hit it once: a 4.3 site lost its session on redirect (old session had no `sessionuserid`), login selected `user_password_algo`, the install lock selected the same column - the site could not reach its own upgrade.

## What must bridge code do?
- Login path (`validate_login`, `pg_password_verify`, API login), install lock and `initialize_user()` must work on the previous released schema. To read a new column/table, probe first with the pattern `pg_user_has_password_algo()`, `pg_auth_tokens_table_exists()`, `pg_auth_tokens_have_pin()` (static, SHOW COLUMNS/TABLES, once per request).
- Gate `pg_require_current_schema()`: `validate_user()` and login screen `index.php` call it first; if `config.version` is behind the code (`pg_code_version()` = last line of versions.php) the visitor is sent to `install/index.php`. Everything behind the gate may assume the current schema; probes are not written there.
- Install screen `check_if_administrator_is_logged_in()` also accepts the previous version's identity proof: the session pair (`sessionusername` + `sessionpassword` MD5) and the remember-me cookie pair (`software[username]` + `software[password]`), only while `install_bridge_open()` (config.version < code). In every version that changes the identity format, update this fallback to the new old-format. Once the bridge closes it is dead code.
- `initialize_user()` does not clear the old session keys and old cookie pair while the schema is behind; they are the only proof carrying the upgrade.
- Version skipping is allowed (2025.x to latest): the gate uses `version_compare`, the runner runs versions in order, the bridge stays open until the last step.

## What test is mandatory for every new version?
Previous released version's DB + new files -> (a) `software_update.php` flow with an old admin session, (b) sessionless `install/index.php`: lock screen -> password -> upgrade -> login.
