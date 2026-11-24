---
name: bug-hunt-areas-backup-restore
description: Bug hunt 24, making, listing, downloading and restoring a backup: two faults fixed (newer-version restore, unfinished folder listed as good), what was read clean
module: backup, backup_restore
---

## Which area was read?
- Hunt 24, backup-restore: who may make, list, download and restore a backup; the download address; a half-written backup; the retention prune; a restore onto a newer schema; a failure at the remote destination.
- Read: `backups.php`, `auto_backup.php`, `includes/fn/backup.php`, `includes/fn/backup_restore.php` (begin, swap), `includes/fn/backup_cloud.php` (put, prune), `includes/panel/software.php` (`software_backup`), `includes/explorer/handle_backups.php` (gates), the backup download of `view_folders.php`, the restore part of `install/index.php`.

## What did it fix?
- A backup taken with a newer software version was restored and swapped in; now refused before the swap (`platform/restore-newer-version-refused.md`).
- A folder backup cut short while its files were copied (closed tab, time limit), or a replaced backup with the old files beside the new dump, was listed, counted and offered to the installer as good; now "Incomplete" until its writer finishes (`platform/backup-manager-names-and-list.md`).

## What was read and found clean?
- Rights: Backup Manager, `software_backup`, `auto_backup.php` by web, the File Manager's backup handlers and the S3 fetch are manager (role <= 2) (`decisions-made-access.md`); the restore is the installation screen's administrator; rollback role 0.
- Download: `data/backups` is denied to the web; a backup leaves only by a POST of a listed name with the token (`backups.php`) or by `view_folders.php?backup_download` with the manager gate and the token.
- Archives: the automatic, pre-update and fetched archives are built or fetched in `data/temp` and renamed in only when whole; the dump writes `.part` first.
- Retention: `pg_backup_prune_list()` keeps the newest weeks, the archive over a folder of the same week; a remote listing cut short can only delete names older than those kept.
- Remote: a failed copy sets `backup_remote_error`, logs and sends `backup_remote_failed`; the Backups card and System Status show it; no ZipArchive is said on the card.

## What was left as it is?
- A failed prune of the remote copy is only logged, no alert. The FTP copy deletes the old file before its rename, so a failed rename leaves only the `.part`.
