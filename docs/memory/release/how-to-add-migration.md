---
name: how-to-add-migration
description: Step-by-step for a DB schema change: open-version sub-step vs new number, versions.php, migration file template, get_tables() for new tables, changelog.
---

## How do I add a schema change?
First check which version is open (docs/memory/release/release-status.md).
- **Open (unreleased) version exists:** skip steps 1 and 2. Add the change as a new sub-step function and call it from `upgrade_to_<open version>()`; make sure that version's changelog section has its [SCHEME] line.
- **Only the last version is released:** open a new number with steps 1-5.

1. Append the new number to the end of the `includes/migrations/versions.php` list: `'2026.4.5',`
2. If it touches the DB, write `includes/migrations/2026.4.5.php` (header + guard + function; copy from neighbours). No file for a code-only version. Run `php -l`.
```php
if (!defined('INSTALL_OR_UPDATE')) { exit; }

function upgrade_to_2026_4_5() {
    install_add_column('user', 'secret_key_hash', "VARCHAR(64) DEFAULT NULL");
    install_add_index('user', 'idx_secret_key_hash', "INDEX idx_secret_key_hash (secret_key_hash)");
    // Backfill: fill existing rows - write it re-runnable
}
```
3. Run it from the upgrade UI; no manual SQL. Run twice on the dev DB: the second time the screen must say "zaten yerindeydi".
4. **New table: add it to `get_tables()` in `install/index.php`** (tables DROPped before a clean install; used only for DROP, order irrelevant). If skipped, a clean install into the same DB leaves the old table, the post-install upgrade sees it and skips "already exists", and the site opens on the old schema; no error because the step is idempotent - silently wrong. `functions.php` `check_and_repair_database_tables()` once kept a second list; it now finds tables from `information_schema`, add nothing there.
5. Changelog: the version needs one [SCHEME] line naming the area (docs/memory/release/upgrade-changelog-rules.md); the detail goes to the matching docs/memory file, the step's row to docs/sema-adimlari.md.

Related: docs/memory/release/migration-step-helpers.md
