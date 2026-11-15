---
name: addon-hook-system-census-files
description: Add-on hook system AH1 census, files part: what file integrity, backup, System Status, the migration runner and the updater read today, and what each would do with includes/addons/<slug>/
---

## What does the file integrity check read?
- `check_directory_file_integrity()` (`includes/fn/system_status.php`), run by System Status and the ops alert (`includes/fn/ops_alerts.php`).
- Scope: `pg_integrity_scope_directories()` returns `['includes']`; `pg_integrity_in_scope()` covers the files directly in the software folder and everything under `includes/`. `pg_integrity_ignored_files()` is a basename list (`error_log`, `.htaccess`, ...); there is no path ignore list.
- Expected files: the release tag's tree on GitHub (`pg_integrity_github_reference()`, path prefix `pinegrap/`, cached in `data/temp/hash_reference_github.json`); on a dev machine `data/temp/hash_reference.json`.
- It walks the reference only, never the disk: a file not in the release is never reported. So a site's own `includes/addons/<slug>/` is ignored as things stand; an add-on shipped in the release is checked like core (an edit reads as tampered).
- The Write Permissions row (`pg_write_permission_scan()`) walks the whole software folder and would list an add-on file that is not writable.

## What does a backup hold?
- Auto backup: `auto_backup.php` → `pg_backup_run_auto()` → `pg_backup_run_zip()` / `pg_backup_run_folder()` (`includes/fn/backup.php`): `sql.sql` plus the top-level files of the files and layouts folders. Manual backup (`includes/panel/software.php`) holds the same. Cloud sending: `pg_backup_remote_send()`, `includes/fn/backup_cloud.php`.
- The database part (`pg_backup_dump_database()`) dumps every table but those in `pg_backup_skipped_tables()`, so an add-on's tables are in it. No backup holds code under `includes/`, so add-on folders are not.
- Before an update, `pg_update_backup_create()` (`includes/fn/update.php`) keeps the database and only the files the package replaces.
- Restore: `pg_restore_swap()` (`includes/fn/backup_restore.php`) drops live tables missing from the backup only when they are in `get_tables()` (`install/index.php`); an add-on table outside that list survives a restore with its rows.

## How is System Status built?
- `get_system_status_checks()` (`includes/fn/system_status.php`), cached 10 minutes in `data/temp/system_status_cache.json` with a shape version `'v'`. Rows come from the `$makeIcon(...)` closure in groups `security`, `seo`, `server`, `maintenance`; scoring by title in `$check_weights` (a title not there is unscored).
- Drawn by `includes/dashboard/widgets/widget_2.php`, `includes/dashboard/v2/page_status.php` and `includes/api/resources/system.php`. `pg_system_status_forget()` drops the cache.
- A list of installed add-ons has no row today; the `maintenance` group is where Last Backup and the like sit.

## How does the migration runner know what ran?
- Versions: `includes/migrations/versions.php` (`install_get_versions()`); each `includes/migrations/<ver>.php` defines `upgrade_to_<ver>()` calling `upgrade_<ver>_<topic>()` steps. `install_run_upgrades()` (`includes/migrations/runner.php`), called from `install/index.php`.
- Done means only `config.version`: no table of steps run. Step labels such as `// 11.0.17` are comments; every step is idempotent through `install_add_column()`, `install_create_table()` and the rest, whose notes (`install_note()`) are on-screen only.
- So nothing records a namespaced add-on step today; an add-on's steps would need their own done record. The `install_*` helpers need only `db()` and the `$install_runner` global.

## What does an update do to the folder?
- `pg_extract_archive()` (`includes/fn/update.php`) writes only the package's entries and deletes nothing absent from it; `pg_update_rollback()` deletes only files the package added. `clean_up.php` deletes an explicit name list.
- So `includes/addons/<slug>/` is left alone unless a release ships files at those paths.
