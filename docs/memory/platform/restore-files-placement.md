---
name: restore-files-placement
description: The install-screen restore replaces data/files and data/layouts only when the disk holds the backup's files, and names files it could not write (pg_restore_place_folder())
module: backup_restore
---

## How does a restore put files/ and layouts/ in place?
- After the tables are swapped, `install/index.php` calls `pg_restore_place_folder($source, $target)` (`includes/fn/backup_restore.php`) once for files/ and once for layouts/, from the backup folder or the archive's working folder (`pg_restore_content_path()`).
- It adds up the regular files directly in both folders. Only when free space plus the site's own files covers the backup's files plus 16 MB does it delete the site's files and copy the backup's with `pg_backup_copy_folder()`, which checks each copy's size.
- Without that room, or when either folder cannot be read, nothing is touched: the step is a warning and the site keeps its own files.
- A file that could not be written is named in a warning step ("N files could not be copied: a, b, c, ..."); the count on a clean step is the files copied.
- Subfolders of either folder are left alone, as before.

## Why?
- The old loop deleted every site file and then ran `copy()` without checking it. On a disk that ran full part-way, the site was left with part of its files, or none, and the step still said "Copied the files of the site".
- Tests: `tests/restore_place_folder_test.php`.
