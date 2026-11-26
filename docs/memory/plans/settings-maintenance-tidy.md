---
name: settings-maintenance-tidy
description: Plan (step TR5 of settings-tidy-round): Site Settings › Updates and maintenance as folded rows, the cron card as a list of jobs, the sales watch a row of its own, steps SM1 to SM4
priority: 6
---

## What is asked?
- Owner (2026-11-21, 2026-11-26): old settings screens made modern (`settings-tidy-round.md`). The survey left three things here: the cron card's long instructions, two sets of S3 fields, the sales watch hidden with Operations alerts (K2).

## What is there today? (`includes/settings/maintenance.php`, 926 lines, read 2026-11-26)
- Cards: Update Channel (`pgset-channel`), Cron Jobs (`pgset-cron`), Backups (`pgset-backup`, with Backup Manager), Storage (`pgset-storage`, the S3-compatible store, Save and test, the move tool), Alerts (`pgset-alerts`: Operations alerts with the sales watch inside), Chat notifications (`pgset-chat-notify`), Maintenance Mode (`pgset-maintenance`).
- Cron Jobs: the switch "Run Cron Jobs Automatically", the general job's command, a switch a job; "Setup Instructions" opens a drawer of eleven jobs, a paragraph or more each.

## What must it never do?
- Change a field's name, id or value, a settings key, `maintenance.save.php`'s checks (the `object_storage_card` mark, the named Save and test buttons) or what a save writes; one Save a pane.
- Join the two S3 sets: the `s3` backup type keeps its own settings and the store its own (`platform/object-storage-decisions.md`); neither goes to Connections (`ui/connections-one-place.md`). They are only drawn alike.
- Change the sales watch's behaviour: it still stops with Operations alerts (`workflow/decisions-made-product-84.md`). Only its place in the pane changes.
- Lose an anchor (`pg_settings_legacy_anchors()`), or show a secret: a secret box stays empty with "Saved".

## Which rows, and what does each say closed?
- Update channel: "Stable" or "Beta", then "backs up before updating" when on.
- Maintenance mode: "Off", or "On until 14:00" with an amber pill.
- Scheduled jobs: "Runs every minute · 6 jobs with it", or "Not scheduled: nothing runs on its own" with attention.
- Backups: the destination and the last backup's time; "No backup yet" with attention.
- Storage: "Files on the disk", "In the bucket {name}", or "Moving files: 1,240 left"; a failed last test asks for attention.
- Operations alerts: "By e-mail to 2 addresses and to devices", or "Off".
- Sales watch (new card `pgset-sales-watch`): "Watching, normal sensitivity", "Paused until 2 January", or "Off with Operations alerts" with a link that opens that row.
- Chat notifications: the targets by name, or "None".

## How is Scheduled jobs drawn open?
- The switch "Run the jobs with the general job" and, under it, the general job's command in one read-only line with Copy and "Every minute".
- Then a list, one row a job: its name, one line of what it does, its recommended schedule, and the switch it has today, shown while the master is on. A row's "How to schedule it" opens the numbered steps drawer (`ui/look-design-examples-patterns.md`) with that job's command, Copy, and its notes as short steps.
- The selection stays posted while the master is off.

## How are the other rows drawn open?
- One column, group labels, `pg-switch`, Sensitivity and the channel as `pg-seg`; the alert thresholds two a row from 768 px; Storage's move tool as the group "Move files".

## Which steps? (one at a time: one file; tests red first)
- SM1 (M) done (#11876) The fold: rows, lines and pills as pure functions in `includes/fn/maintenance_settings_fold.php` with its test; the sales watch cut into its own card.
- SM2 (M) after SM1: Scheduled jobs.
- SM3 (M) after SM2 and after TR3a has landed: Backups and Storage, Update channel, Maintenance mode, Operations alerts, Sales watch, Chat notifications opened.
- SM4 (S) Close-out: `ui/settings-maintenance.md`, the survey's K2 and S3 lines corrected, this file deleted.

Related: docs/memory/plans/settings-tidy-round.md, docs/memory/ui/settings-fold-rows.md, docs/memory/platform/object-storage-client.md
