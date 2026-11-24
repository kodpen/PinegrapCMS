---
name: restore-newer-version-refused
description: The install-screen restore refuses a backup taken with a newer software version, after loading and before the swap, so the site is never changed (pg_restore_version_newer())
module: backup_restore
---

## What happens to a backup of a newer version?
- The installation screen loads it next to the site as any restore, then reads `config.version` of the loaded tables (`pg_restore_loaded_version()`, from `pgr_new_config`). When it is newer than the software (`pg_restore_version_newer()`, `version_compare`), the loaded tables are dropped (`pg_restore_discard()`) and the screen says which version took the backup and to update the software to it first. The site is not changed; the refusal goes to the site log.
- Checked only while the restore is in phase `loaded`, before `pg_restore_swap()`; a restore already swapped is finished as before.

## What is not refused?
- The same version, an older one (the upgrade runs after), and a backup whose config has no row or an empty version.

## Why?
- Before the bug hunt backup-restore the restore swapped such tables in. The older code has no way down and does not know the newer columns, and the screen then read a database version that is not in its package: "not part of this package", no upgrade offered, a site that may not start.
