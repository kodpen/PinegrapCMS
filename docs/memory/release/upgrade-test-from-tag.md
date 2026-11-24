---
name: upgrade-test-from-tag
description: Testing the open version as a real upgrade: install the last release from its git tag, upgrade it with today's files, compare the schema with a fresh install
---

## Why install the last release instead of setting config.version back?
- Setting `config.version` back (`rerun-upgrade-steps.md`) runs the steps over tables that already have the new columns, so every ADD is skipped: it proves the double run only. An install of the last released tag has the old schema, so every step really runs.

## How is the old site installed?
- `bash tools/setup_sandbox.sh` first (MariaDB, the fresh install of today's files, `pinegrap_sandbox`).
- `git fetch --depth=1 origin tag v2026.4.10`, then `git worktree add --detach <scratch>/old v2026.4.10`.
- A second database (`CREATE DATABASE pg_old`, `GRANT ALL ... TO 'pinegrap'@'localhost'`), a `data/config.php` in the worktree naming it, `php -S 127.0.0.1:8001 -t <scratch>/old`, then the installer form posted as `setup_sandbox.sh` posts it (token from the page, `install_from_folder=turkish_default`). 2026.4.10 gives 302 tables; keep a `mysqldump` of it to restore for each try.

## How is it upgraded with today's files?
- Point today's `pinegrap/data/config.php` at the old database (it is skip-worktree in the sandbox), then `php pinegrap/install/index.php automated_upgrade`: one line, `ok: 2026.4.10 -> 2026.11.0` or the failing statement.
- Compare with the fresh install through `information_schema` (columns with type, NULL, default, charset, collation; statistics grouped per index; tables with engine), sorted: no line may differ.

## How are a cut-off run and a pause tried?
- Cut off: a local, never committed line in `pg_db_run()` (`includes/fn/core.php`) that throws before the Nth write statement while the runner is active; restore the dump, run with the fault, run again without, compare schema and row counts with one clean run.
- Pause: `install_pause_budget()` returning 0 locally makes every `install_pause()` point pause.
- `git checkout` both files afterwards.

Related: docs/memory/release/rerun-upgrade-steps.md, docs/memory/release/upgrade-bridge-rule.md
