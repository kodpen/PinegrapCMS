---
name: decisions-made-product-39
description: Decisions list, product part 39 (2026-11-09 on): a backup is reported as a problem only when the newest one is more than a month old
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## When is a backup's age a warning? (2026-11-09)
- Owner (2026-11-09): warn only when the last backup is older than one month. Under a month, System Status reads the backup as fine (no warning, no score deduction, no greeting advice) and no operations alert is sent; the age may still be shown as plain information. No backup at all stays a failure; a switched-off schedule stays grey. Reason: the constant warning was noise; the agents kept writing "it has been 3 days since the last backup" into the daily summary, and the automatic backup is weekly, so the old two-day threshold was yellow most of every week. Rule: `platform/backup-age-rule.md`.
