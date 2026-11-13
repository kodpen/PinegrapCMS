---
name: backup-folder-download-zip
description: Backup Manager Download and File Manager Zip of a folder backup: one writer with "/" names; the archive inside the folder is rebuilt when the backup was updated after it.
module: backup
---

## Where does a folder backup's download come from?
- `backups.php` zips the folder into itself (`data/backups/<name>/<name>.zip`) on the first Download and serves that archive on later downloads. An archive backup (`data/backups/<name>.zip`) is served as it is.

## When is the kept archive rebuilt?
- When `pg_backup_folder_zip_current($zip, $folder)` (`includes/fn/backup.php`) says no: the archive is missing, or any other file under the folder is as new as it (a file from the same second may have been written after it).
- The rebuild leaves the old archive out of the new one.

## Who writes a folder backup's archive?
- `pg_backup_folder_zip_write($folder, $zip_path)` (`includes/fn/backup.php`), for the Backup Manager's Download (inside the folder) and the File Manager's Zip (`includes/explorer/handle_backups.php`, beside it as `data/backups/<name>.zip`, a backup archive of its own). It returns the number of files, false on failure.
- Entry names come from `pg_backup_zip_entry_name()`: "/" between folders. On Windows `getRealPath()` gave `files\3940.jpg`, which unzip tools elsewhere take as one file name (#9122).
- It never packs `$zip_path` or `<folder>/<folder>.zip`. Before #9123 the File Manager's Zip packed the download archive too: about twice the size, a zip inside the zip. Empty folders are kept.
- Open: whether the download archive should stay inside the backup folder at all (it moves the folder's time and adds to its size, #5957).

## Which fault did this fix?
- "Create or Update Backup" under the same name, and the weekly automatic folder (`pg_backup_run_folder()`), rewrite the dump, files and layouts but leave the archive. Before #5130 the download kept handing out the earlier backup after such an update, with no warning.
