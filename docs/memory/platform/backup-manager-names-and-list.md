---
name: backup-manager-names-and-list
description: Backup Manager: the folder name (no basename, 100 characters), reserved names, starter-dump delete, the overwrite question, step errors, list time and order, incomplete folders
module: backup
---

## How does a typed name become a folder?
- `pg_backup_folder_name()` maps Latin letters to ASCII and turns everything outside `A-Za-z0-9_-` into `_`, `/` and `\` included, then collapses `_` runs, cuts to `PG_BACKUP_NAME_MAX` (100) and trims `_`. The Backup Name field has the same `maxlength`.
- Before #6012 it took `pg_basename()` first, so `yedek 03/11/2026` became `2026` and `… <b>x</b>` became `b`; the character class alone already kept the path inside `data/backups/`.
- Before #6012 there was no length limit: a long name made `mkdir()` fail on Windows paths while the step still said success.

## Which names are refused?
- `pg_backup_name_reserved()`: `english_default`, `turkish_default` (the starter sites), `pg_backup_is_auto_name()` names, and `pre_upgrade_` / `pre-update-` prefixes, without case. Every `software_backup` step refuses them, so no request can clear a starter site's files.

## May a starter-site dump be deleted?
- Yes (owner decision, #6445): `english_default` / `turkish_default` keep their Delete button. For those folders only (`pg_backup_is_starter_dump()`, without case; archives do not count) the row's `data-confirm-content` says a later install from this copy no longer offers that language and the panel does not bring the dump back (`get_available_starter_sites()` lists only folders on disk). Other backups keep the plain confirmation; the write side stays refused.

## What happens when the name already exists?
- `create_backup_folder` answers `status: confirm` with the question; `software_backup_runSteps()` (`backend.src.js`) asks through `pgConfirm()` and sends the step again with `overwrite: true`. Updating a backup under its name stays possible (`backup-folder-download-zip.md`), only no longer silent.

## How do the steps report failure?
- `create_backup_folder` checks the folder exists and `pg_dir_writable()` after `@mkdir()`, else errors with a translated reason. `create_mysql_dumb` wraps the dump library's English text in a translated sentence. An answer that is not JSON (a PHP warning before it) names the step.

## Which time does the list show, in which order?
- `pg_backup_taken_time()`: a folder's `sql.sql` time, else the folder's or archive's own. The folder's own time moves when Download writes `<name>.zip` into it. `backup_list()` returns name => time; `pg_backup_list_order()` sorts newest first, then by name (the DataTables sorting of `table.chart` is off).

## What does a folder without sql.sql show?
- An "Incomplete" badge and no Download button. A delete that removed the files but not the folder leaves one; the warning is one line naming the backup and how many entries were refused, never a server path (#6012).
