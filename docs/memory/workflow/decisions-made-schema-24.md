---
name: decisions-made-schema-24
description: Decisions list, schema part 24 (2026-11-16 on): the three site_push tables of visitor notifications, their columns and keys as the architect confirmed them for VP1
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which tables do visitor notifications get? (architect, 2026-11-16, agent channel message 6528)
- Under the owner's yes to three tables for visitor notifications (poll 65, `decisions-made-product-59.md`: "Evet, eklenebilir; sütunları Mimar onaylasın"). Added by VP1 (#9947) as step 11.0.227, `upgrade_2026_11_0_site_push()`. No backfill.
- `site_push_subscription`: `id` INT UNSIGNED AUTO_INCREMENT, primary key; `endpoint` VARCHAR(500), `endpoint_hash` CHAR(64), `p256dh` VARCHAR(100), `auth` VARCHAR(32), `service` VARCHAR(10), `secret_hash` CHAR(64), `language` VARCHAR(10), all ascii_bin; `user_id`, `created_at`, `last_seen_at` INT UNSIGNED; `fail_count` SMALLINT UNSIGNED. Keys: `uq_endpoint (endpoint_hash)` and `uq_secret (secret_hash)` unique, `idx_user (user_id)`.
- `site_push_message`: `id`; `title` VARCHAR(60); `body` VARCHAR(200); `url` VARCHAR(500); `kind` TINYINT UNSIGNED; `created_by`, `created_at`, `queued_count`, `sent_count`, `failed_count`, `removed_count` INT UNSIGNED. Key: `idx_kind_created (kind, created_at)`.
- `site_push_queue`: `message_id`, `subscription_id` INT UNSIGNED, together the primary key; `status`, `attempts` TINYINT UNSIGNED; `next_at`, `sent_at`, `fetched_at` INT UNSIGNED. Keys: `idx_due (status, next_at)`, `idx_subscription (subscription_id, status)`.
- Every column is NOT NULL and all but `id` carry a default; times are `_at` columns in Unix seconds (INT), the open version's way. No row id on the queue.

## What did the architect change in the draft's columns, and which rules came with them?
- `kind` in place of the draft's `is_test`: 0 news to every subscriber, 1 a test, 2 a stock notice (written by VP5), 3 an order's moment (VP6); 2 and 3 go to one device and stay out of the panel's list of sent messages. One column now, so the later steps add none; VP2 writes only 0 and 1. With it `idx_kind_created` in place of `idx_created`.
- An endpoint or a key longer than its column is refused, never cut: a cut address is a dead subscription.
- `url` holds a path inside the site, no host: the worker opens it on its own origin, so a site whose domain changes keeps its old messages.
- The queue is not a history: finished rows (status 2 and 3) and a dropped message's rows are deleted 7 days later by the sender's pass (VP2). The counts stay on the message row, so the panel is not touched.
- The sender runs from two places (the general job and `push_job.php`): the pass is taken with one `pg_cron_claim()` (`platform/job-script-claims.md`) and a row is set to 1 as soon as it is pushed; no column for this. A worker woken twice for one message shows that message again, not the fixed notice: the door may give a row whose `fetched_at` is a few minutes old once more.
- Kept as the draft chose: no `contact_id` column (the account's contact is reached through `user_id`), the counts on the message row, nothing written into the panel's `push_subscriptions` or `push_queue`.
- How the tables are written and read: `platform/visitor-push-rules.md`.
