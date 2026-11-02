---
name: backup-folder-download-zip
description: Backup Manager Download of a folder backup: the archive kept inside the folder is rebuilt when the backup was updated after it.
module: backup
---

## Where does a folder backup's download come from?
- `backups.php` zips the folder into itself (`data/backups/<name>/<name>.zip`) on the first Download and serves that archive on later downloads. An archive backup (`data/backups/<name>.zip`) is served as it is.

## When is the kept archive rebuilt?
- When `pg_backup_folder_zip_current($zip, $folder)` (`includes/fn/backup.php`) says no: the archive is missing, or any other file under the folder is as new as it (a file from the same second may have been written after it).
- The rebuild leaves the old archive out of the new one.

## Which fault did this fix?
- "Create or Update Backup" under the same name, and the weekly automatic folder (`pg_backup_run_folder()`), rewrite the dump, files and layouts but leave the archive. Before #5130 the download kept handing out the earlier backup after such an update, with no warning.
