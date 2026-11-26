---
name: settings-maintenance
description: Site Settings › Updates and maintenance as folded rows: the eight rows in order, each row's line of state and pill, the Sales watch card of its own, what stayed (ids, names, the save)
module: maintenance_settings_fold
---

## How is the pane drawn?
- Folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`) since #SM1PR, in this order: Update channel, Maintenance mode, Scheduled jobs, Backups, Storage, Operations alerts, Sales watch, Chat notifications. The registry's sections follow the same order and names.
- Order, names, icons and lines: `includes/fn/maintenance_settings_fold.php` (`pg_maintenance_fold_order()`, `pg_maintenance_fold_face()`, `pg_maintenance_fold_<part>()`, pure but for `lang()` and `date()`); `includes/settings/maintenance.php` reads the facts. Test: `tests/settings_maintenance_fold_test.php`.
- Each card is still drawn whole inside its row; the open rows' new drawing is the plan settings-maintenance-tidy (SM2 Scheduled jobs, SM3 the rest).

## What does each row say closed?
- Update channel: Stable or Beta (amber pill), then "backs up before updating" when on.
- Maintenance mode: Off; "On until 14:00" (the day too when not today) or On, amber pill; a switch past its end time: "Ended at …: visitors see the site", no pill.
- Scheduled jobs: "Runs every minute · 6 jobs with it" (jobs selected that can run with the general job, none while the master switch is off); "Not scheduled: nothing runs on its own" when the general job never ran, "Stopped: the general job last ran …" past its `stale_after`; both ask for attention.
- Backups: the remote copy's place (or On this server) and the newest backup's time (`pg_backup_newest()`); "No backup yet" or a failed remote copy asks for attention.
- Storage: "Files on the disk", "In the bucket {name}", "Moving files: 1,240 left" (or Bringing files back, paused); a failed last "Save and test" asks for attention.
- Operations alerts: "By e-mail to 2 addresses and to devices", e-mail or devices alone, "On the bell only", or Off.
- Sales watch: "Watching, normal sensitivity" (and "· 1 channel paused"), "Paused until 02.01.2027" (the store's pause, its last day), Off when every alert is off, or "Off with Operations alerts".
- Chat notifications: the targets' labels (the service's name for one without), at most three then "and N more", or None.

## Why is the sales watch a card of its own?
- The survey found it hidden inside Alerts (K2). Its behaviour stays: it stops with Operations alerts (`workflow/decisions-made-product-84.md`). Its fields still hide with that switch; a note and the button Open Operations alerts (`data-pg-set-goto="pgset-alerts"`) show instead.

## What stayed?
- Every card id, field name and value, `sales_watch_card` and `object_storage_card`, the named Save and test buttons, the save module and the legacy anchors. The two S3 sets stay apart (`platform/object-storage-decisions.md`).

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/commerce/sales-watch-settings.md
