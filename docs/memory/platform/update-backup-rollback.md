---
name: update-backup-rollback
description: Backup a software update or repair writes before replacing files (pre-update-*.zip) and the rollback on Repair Software: contents, hash record, effect on files and config.version
---

## What does an update write before it replaces the files?
- A fourth step, `backup`, between `download` and `replace` of the `software_update` panel action (update screen and Repair Software both call it). `pg_update_backup_create()` (`includes/fn/update.php`) writes `data/backups/pre-update-<version>-<YmdHis>.zip`: `sql.sql` (the usual dump, `pg_backup_dump_database()`), `software.zip` (the files the package will overwrite, as they are, under the package's own names relative to the web root) and `manifest.json` (version, package, files the update adds).
- Built in `data/temp/update_backup_*`, moved into place only when whole. A retry with the same package the same day reuses that day's archive (the record keeps the package's SHA-256): after a replace that failed part-way, a new backup would save a mix. Unreadable files or no pdo_mysql fail the step. Retention (`pg_update_backup_prune_list()`): recorded archives only, by recorded time, three newest plus the one just written plus the newest of each version. PR #544.
- A failed backup stops the update before anything is replaced, with a message naming the setting. `update_backup` (config, 2026.11.0 step 11.0.6, default 1; Settings › General › Update Channel) turns it off for sites too large to dump within one request.

## How is it rolled back?
- Repair Software (administrators) lists the archives (`pg_update_backup_list()`); action `software_rollback` → `pg_update_rollback()`: the archive is copied to a private folder and only the copy is hashed and read (a manager could swap the file between check and read), `software.zip` is copied out with its archive checksum checked (`pg_restore_archive_copy()`), written with `pg_extract_archive()` (the update's own verified extraction), the files the update added are deleted (manifest names through `pg_update_safe_entry()`), the bytecode cache is cleared (`pg_update_clear_opcache()`, shared with the replace step), status/integrity/schema caches are dropped and `config.version` is set back to the archive's version when it is newer.
- The database is not touched: newer columns stay and the older code ignores them; the next update reruns those steps (idempotent). To put the database back too, restore the same archive on the installation screen: it holds `sql.sql` and no `files/`/`layouts/`, so the installer keeps the site's files. That loses everything written since the update; both screens say so.

## Why a record outside the backup folder?
- Managers can upload into `data/backups`, and a rollback replaces executable code. Only an archive this site made is used: its SHA-256 is written to `data/update_backups.json` at creation and compared before anything is extracted. An unrecorded or changed archive is listed but refused (it can still be restored as a database backup).

## Where is the rollback, and why not on the update screen?
- The plan named the Software Update screen, but that screen only opens while an update is pending, so the card sits on Repair Software (admin-only, already the place that writes code over the software).

## How was it tested?
- `tests/update_backup_test.php`; sandbox with a fake package: backup step → archive with 2 files and 1 added; replace; rollback refused for a bad name, an unrecorded copy and a changed archive; real rollback restored both files byte for byte (git clean), deleted the added file and set `config.version` back; retention kept three; switch off skips the step.
