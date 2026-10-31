---
name: decisions-made-schema-7
description: Decisions list, schema part 7 (2026-10-31 on): SMS campaign tables sms_campaigns and contact_groups_sms_campaigns_xref
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the migration shape.

## SMS campaign storage (#3847)
- Approved (architect, 2026-10-31, sms-and-iys step 10): two tables by `install_create_table()` in `includes/migrations/2026.11.0.php` under one sub-step, the next free general label when pushed (11.0.137 today), InnoDB utf8mb4, no ENUM. `sms_campaigns`: `id` INT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(100) NOT NULL DEFAULT '', `body` TEXT NOT NULL, `start_at` INT UNSIGNED NOT NULL DEFAULT 0 (epoch, as `sms_outbox.send_after`), `status` VARCHAR(16) NOT NULL DEFAULT 'draft' (draft/ready/queued/cancelled), `queued_count`, `refused_count`, `queued_at`, `created_at`, `created_by`, `updated_at`, `updated_by` INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (id), KEY idx_due (status, start_at). `contact_groups_sms_campaigns_xref`: `contact_group_id`, `sms_campaign_id` INT UNSIGNED NOT NULL DEFAULT 0, `type` VARCHAR(16) NOT NULL DEFAULT 'included' (included/excluded), a KEY on each id.
- Recipients are `sms_outbox` rows (kind `marketing`, reference `smscampaign:<campaign id>:<contact id>`, so `uq_reference` guards a rerun); the general job queues a due `ready` campaign once and writes the counts; the lasting record is the counts, live figures come from the outbox while it keeps the rows. Refused: a channel column on the e-mail campaign tables (every e-mail reader would have to skip SMS rows). Deferred: a per-recipient `sms_campaign_recipients` table, asked again if a lasting per-number report is wanted.
