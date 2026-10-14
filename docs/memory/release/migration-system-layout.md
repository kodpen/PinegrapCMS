---
name: migration-system-layout
description: Where DB schema changes live (includes/migrations files and roles) and how version entry functions and sub-steps are named. Direct SQL for schema is forbidden.
---

## Where do DB schema changes go?
- Rule: never write direct SQL for schema; every `ALTER TABLE` / `CREATE TABLE` change goes through the upgrade system.
- Since 2026-08-28 the system lives in `pinegrap/includes/migrations/`, not in `install/index.php` (the install folder can be deleted from a server; includes stays).

## What does each file do?
- `includes/migrations/versions.php`: version history, flat list, order is binding. A row is never deleted, a number is never reused.
- `includes/migrations/<version>.php`: that version's `upgrade_to_<version>()` (2026.2.5.php defines `upgrade_to_2026_2_5()`). Only versions that touch the DB have a file; a code-only version stays in the list with no file.
- `includes/migrations/legacy.php`: 2017.2 to 2023.2.1 functions, as they were.
- `includes/migrations/runner.php`: helpers (`install_add_column` ...), the `db()` safety net, lock, loop `install_run_upgrades()`, preflight `install_preflight()`, backup `install_backup_database()`, last-run markers `install_last_*()`.
- `install/index.php`: screen + form + clean install only; delegates the upgrade to the runner (`install_action=upgrade_step` / `backup_database` JSON endpoints).

## How are upgrade functions named?
- Version entry: `upgrade_to_2026_4_5()` - the only name the system builds and calls.
- Sub-steps: `upgrade_2026_4_5_<topic>()` (e.g. `upgrade_2026_4_4_recycle_bin()`), no `to_`; they are called from the entry, not by the system.

Related: docs/memory/release/migration-step-helpers.md
