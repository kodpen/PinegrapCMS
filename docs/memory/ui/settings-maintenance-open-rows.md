---
name: settings-maintenance-open-rows
description: Updates and maintenance, the rows but Scheduled jobs opened (SM3): pg-switch rows, channel and Sensitivity as pg-seg, group labels, thresholds two a row, Move files, radio ids
module: maintenance_settings_open
---

## How are the opened rows drawn?
- One column, group labels, one line of help a field (`p.pg-field-help` tied by `aria-describedby`); no `div.switch`, no popover. Scheduled jobs is `settings-maintenance-cron.md`.
- Helpers in `includes/fn/maintenance_settings_open.php` (pure): `pg_maintenance_settings_switch($name, $on, $label, $help, $attributes)`, `pg_maintenance_settings_seg($legend, $name, $options, $current, $help)`, `pg_maintenance_settings_group($label)`. Test: `tests/settings_maintenance_open_test.php`.
- Source tests that list the pane's field names (`settings_maintenance_test.php`, `settings_maintenance_fold_test.php`) read `name="…"` and the first name argument of these helpers.

## What does each row hold?
- Update channel: Stable / Beta as `pg-seg`, the vendor check's line, the switch Back up before updating.
- Maintenance mode: its switch, the state alert, Ends at, Addresses that see the site, the group Health address (the `health.php` address with Copy).
- Backups: Backup Manager in the heading; groups Automatic backups (Automatic backups to keep) and Remote copy (the destination select, then the FTP, S3 or cloud fields and Save and test).
- Storage: the switch Use object storage, the group Bucket, Save and test with the last test's line, the group Move files (the counts, the walk's state, its failures, the move buttons).
- Operations alerts: the master switch, then Channels (e-mail, recipients, devices) and When to tell: the four thresholds `col-12 md:col-6`, two a row from 768 px, each in an inner `div` so the one-setting-a-line rule of the settings card leaves them in pairs.
- Sales watch: the note and Open Operations alerts while alerts are off; else Watch for (the alert ticks), Sensitivity as `pg-seg`, Price errors, Pause the watch until.
- Chat notifications: unchanged, `pg_notify_settings_card_html()`.

## What stayed?
- Every field name, value and card id, `object_storage_card`, `sales_watch_card`, the named Save and test buttons, `maintenance.save.php`. The backup's S3 fields and the store's are drawn alike (same labels, same order, test) and stay apart (`platform/object-storage-decisions.md`). Secret boxes are drawn empty with "Saved".
- Radio ids are the name and the value: `software_update_channel_stable`/`_beta`, `sales_watch_level_low`/`_normal`/`_high`; no link named the old select ids.
- Built in SM3 of the plan settings-maintenance-tidy.

Related: docs/memory/ui/settings-maintenance.md, docs/memory/ui/settings-maintenance-cron.md, docs/memory/ui/settings-fold-rows.md
