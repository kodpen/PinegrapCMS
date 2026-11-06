---
name: recycle-bin-purge-rights
description: The daily recycle bin purge deletes as the software (administrator role), whoever's explorer visit claims it; a hidden short link is refused, never its record dropped
---

## Whose rights does the daily purge use?
- `pg_recycle_purge_if_due()` (`includes/explorer/recycle_names.php`) is claimed once a day by the first File Manager or store visit, then deletes up to 30 expired entries through `pg_recycle_hard_delete_item()` with `pg_recycle_purge_user($user)`: the visitor's id, role 0. The audit trail already records it as a job (`pg_audit_as_job()`).
- `pg_recycle_hard_delete_item()` decides the file and folder right with `pg_folder_edit_access(…, $user['id'], $user['role'])` from the user it is given, as the page case already did; `check_edit_access()` read the session and ignored that user. For a panel caller the two are the same test.

## What went wrong before?
- A folder-rights user (role 3) who opened the File Manager first claimed the day and purged nothing: the binned rows sit in the bin folder, which is not theirs.
- A manager's visit was refused design files and page styles; at the head of the oldest-first queue (LIMIT 30) they blocked every younger entry for good.
- A short link a role-3 user may not see was taken for gone: the purge (or a hard delete) dropped its `recycle_bin` record and the link stayed binned, never purged again. Now it answers "Access denied" and the record stays.

Tests: `tests/recycle_bin_purge_test.php`.
