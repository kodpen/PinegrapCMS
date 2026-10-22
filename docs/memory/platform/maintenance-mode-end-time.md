---
name: maintenance-mode-end-time
description: How Settings › General reads the maintenance end time (the zone of the same save) and when a passed end time refuses the save (only switching on or a new end)
---

## In which time zone is the typed end time read?
- In the one the same save gives the site: `pg_maintenance_until_parse($posted, $timezone)` (`includes/fn/maintenance_mode.php`) with the posted `timezone`, or `SERVER_TIMEZONE` when it is empty (init.php's rule); an unknown zone falls back to the request's. `0` = no end time, `false` = not a date and time ("The end time is not a date and time."). The card shows the stored timestamp with `date()` in the site's zone (`includes/settings/prep.php`).
- Before PR #1092 (issue #770) it was read in the zone the request started with, while the same UPDATE wrote the new zone: UTC → Europe/Istanbul with 15:00 typed stored 15:00 UTC and showed 18:00 afterwards.

## When does a passed end time refuse the save?
- `pg_maintenance_until_refused($on, $until, $now, $was_on, $was_until)`: only when the switch is posted on, the end is set and not ahead of now, and the save switches the mode on or changes the end. A mode that ended by itself, saved with its switch and end as they were, is accepted and stays ended (`pg_maintenance_holds()`).
- Before PR #1092 every save with the box still ticked and the end passed failed with "The end time has already passed." and wrote nothing, so no other general setting (backups, alerts, time zone) could be saved until the box was cleared.

## Tests?
- `tests/maintenance_mode_test.php`: `test_maintenance_until_parse`, `test_maintenance_until_refused`.
