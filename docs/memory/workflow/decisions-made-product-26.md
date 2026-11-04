---
name: decisions-made-product-26
description: Decisions list, product part 26 (2026-11-04 on): the starter dumps stay deletable in the Backup Manager, with a confirmation that names the consequence
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Starter dumps in the Backup Manager: deletable, with a warning (#6445, owner, 2026-11-04)
- Owner (2026-11-04, #6445, option B): `english_default` and `turkish_default` keep their Delete button in `backups.php`; for those two names the confirmation text says what follows: the language is gone from a later install made from this copy (`get_available_starter_sites()` lists only the folders on disk) and the panel does not bring the dump back. Reason: on a running site the dumps have no use and a manager may want the space (about 3 MB for the two); the write side stays refused by `pg_backup_name_reserved()` (`platform/backup-manager-names-and-list.md`). Refused: hiding Delete and refusing the POST for the two names. One `bug` work item in `backups.php`: the row markup and the new string added with `php tools/tr_add.php`.
