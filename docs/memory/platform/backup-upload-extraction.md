---
name: backup-upload-extraction
description: Installation screen backup upload: archive extracted a megabyte at a time into data/temp/upload_<id>, checksum-checked, moved into data/backups only when whole.
---

## What does the upload on the installation screen do?
- `process_install_backup_upload()` in `install/index.php` (only after an administrator unlocked the screen) takes a `.zip` whose top or single folder holds `sql.sql`, extracts it into a backup folder in `data/backups/` and selects it in "Install From Folder".
- The restore that follows is the stepwise one (`includes/fn/backup_restore.php`): 20 s steps into `pgr_new_` tables, then one `RENAME TABLE`. A `.zip` copied into `data/backups/` by FTP is restored directly; that path extracts in steps of its own.

## Why is every file copied in pieces?
- The upload used `ZipArchive::getFromIndex()`, which holds a whole file in memory. A 200 MB `sql.sql` needs more than 200 MB, so with `memory_limit` 64M–256M a large backup died with "Allowed memory size exhausted", HTTP 500, and left a backup folder with `files/` and `layouts/` but no dump.
- Now `pg_restore_archive_copy()` reads the file's stream a megabyte at a time, checks the size and CRC32 the archive records, and deletes a file that does not come out whole. Memory use stays at a few MB whatever the backup's size (tested: 200 MB dump at 64M).

## Why a working folder in data/temp?
- Files are written to `data/temp/upload_<16 hex>` and the folder is renamed into `data/backups/<name>` only after the last file passed its check, so the backup list never shows a half-extracted backup that looks installable.
- A damaged file, a write error or an empty archive deletes the working folder and says why (nothing is extracted). A request that dies (time limit, memory) deletes it in a shutdown function; one the server killed outright is swept by the next upload after a day.
- Free disk space for the whole uncompressed size plus 64 MB is checked before the first byte.

## Rules
- Never read an archive member with `getFromIndex()`/`getFromName()` when its size is not known to be small; use `pg_restore_archive_copy()` or the stream the way `pg_restore_extract_step()` does.
- Paths inside the archive are rebuilt from clean parts (no `..`, empty or dot parts), so an entry cannot write outside the working folder; a name with a character Windows cannot create (`:` `*` `?` `"` `<` `>` `|`, control characters) is skipped, as the archive restore skips it.

PR: #473
