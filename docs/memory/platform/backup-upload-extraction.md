---
name: backup-upload-extraction
description: Backup archives: install-screen upload streamed into data/temp and checksum-checked; the file explorer's backup extract/upload and web-root zip extract follow the same rule plus name and size limits.
---

## What does the upload on the installation screen do?
- `process_install_backup_upload()` in `install/index.php` (only after an administrator unlocked the screen) takes a `.zip` whose top or single folder holds `sql.sql`, extracts it into a backup folder in `data/backups/` and selects it in "Install From Folder".
- The restore that follows is the stepwise one (`includes/fn/backup_restore.php`): 20 s steps into `pgr_new_` tables, then one `RENAME TABLE`. A `.zip` copied into `data/backups/` by FTP is restored directly; that path extracts in steps of its own.

## Why is every file copied in pieces?
- The upload used `ZipArchive::getFromIndex()`, which holds a whole file in memory. A 200 MB `sql.sql` needs more than 200 MB, so with `memory_limit` 64M–256M a large backup died with "Allowed memory size exhausted", HTTP 500, and left a backup folder with `files/` and `layouts/` but no dump.
- Now `pg_restore_archive_copy()` reads the file's stream a megabyte at a time, never more than one byte past the size the archive records (zip-entry-declared-size.md), checks that size and the CRC32, and deletes a file that does not come out whole. Memory use stays at a few MB whatever the backup's size (tested: 200 MB dump at 64M).

## Why a working folder in data/temp?
- Files are written to `data/temp/upload_<16 hex>` and the folder is renamed into `data/backups/<name>` only after the last file passed its check, so the backup list never shows a half-extracted backup that looks installable.
- A damaged file, a write error or an empty archive deletes the working folder and says why (nothing is extracted). A request that dies (time limit, memory) deletes it in a shutdown function; one the server killed outright is swept by the next upload after a day.
- Free disk space for the whole uncompressed size plus 64 MB is checked before the first byte.

## Who does the site log name?
- `get_install_log_username()`: the signed-in user's name, else the name the screen was unlocked with (`set_install_unlocked($username)` keeps it in the session; unlocking signs nobody in), else ''. Every `log_activity()` on the installation screen uses it.
- Symptom it fixed (#480): after an upload on an unlocked screen, PHP 8 warned "Undefined array key sessionusername" and the log line had no user.

## What rules apply to every archive read?
- Never read an archive member with `getFromIndex()`/`getFromName()` when its size is not known to be small; use `pg_restore_archive_copy()` or the stream the way `pg_restore_extract_step()` does.
- Paths inside the archive are rebuilt from clean parts (no `..`, empty or dot parts), so an entry cannot write outside the working folder; a name with a character Windows cannot create (`:` `*` `?` `"` `<` `>` `|`, control characters) is skipped, as the archive restore skips it.

## What do the file explorer's archive actions refuse? (`includes/explorer/handle_backups.php`, `handle_zip.php`)
- `explorer_backup_extract` (manager): streams every entry with `pg_restore_archive_copy()`, no `memory_limit -1`; the listed size must fit on the disk (`pg_explorer_archive_size()`, `pg_explorer_disk_has_room()`); `pg_explorer_backup_entry_blocked()` skips dotfiles and `web.config` anywhere and executable names outside `layouts/` (at the root or under one wrapper folder) - a child `.htaccess` in `data/backups/` would undo the rules keeping the archives unreachable.
- `explorer_backup_upload`: `.htaccess` is accepted only at the backup root and only while none is there (to restore a lost one), never in a sub-folder.
- `explorer_zip_extract` (role 3 with folder edit rights; files go to `data/files`, only their names join the site's address space): the listing is capped at 10,000 entries and 2 GB uncompressed and must fit on the disk before anything is inflated; files stream.

PR: #473, #480
