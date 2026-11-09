---
name: auto-backup-week-name
description: The weekly automatic backup is named by its ISO week's Thursday, so a week across a month or year keeps one name and the retention counts real weeks.
module: backup
---

## What name does the weekly automatic backup get?
`pg_backup_auto_name()` gives `auto_backup_<Y>-<m>-<W>`: the ISO week of the run, with the year and month of that week's Thursday (noon, by calendar day, so DST cannot move it). Every day of one ISO week gives the same name, and the year is the ISO year. The shape is unchanged, so `pg_backup_is_auto_name()`, the explorer and old archives still match.

## Why the Thursday?
The name used to be `date('Y-m-W')` of the run day. A week crossing a month or a year then had two names (Mon 2026-11-30 → `2026-11-49`, Tue 2026-12-01 → `2026-12-49`; 2025-12-29 → `2025-12-01`, 2026-01-01 → `2026-01-01`). `pg_backup_prune_list()` groups by name, so a retention of 4 kept about 3 real weeks plus an extra full archive, and the remote copy (FTP, S3, Drive, OneDrive, Dropbox) uploaded such a week twice. Fixed in #7666's PR.

## What about names written before?
Old split pairs age out through the normal retention. `pg_backup_auto_sort_key()` keeps its January-53 / December-1 special cases for them (new names never hit them). `pg_explorer_backup_auto_week()` (`includes/explorer/handle_backups.php`) reads week 1 in December as next year's and week 52/53 in January as the year before, so old names get the right Monday–Sunday label.

Tests: `tests/backup_test.php` (`test_backup_auto_name_is_one_name_per_iso_week`), `tests/explorer_file_manager_fixes_test.php` (`test_backup_auto_week`).
