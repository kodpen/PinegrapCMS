---
name: ad-conversions-privacy
description: ad_conversion_events in a person's erase and export: rows of their orders, and Lead and sign-up rows (order_id 0) found by the hashed e-mail in ids (#10955)
module: ad_conversions
---

## How does a person's erase find their rows?
- `pg_privacy_erase_tables()`: `ad_conversion_events` found by `order_id` → `order_ids`, or by `id` → `ad_conversion_ids`; deleted, sent or not.
- A Lead or sign-up row has `order_id` 0 and keeps the hashed e-mail in `ids` (`ad-conversions-visitor-events.md`). `pg_privacy_erase_scope()` fills `ad_conversion_ids` with `pg_ad_conversion_person_where()`: each of the person's e-mails hashed by `pg_ad_conversion_hash_email()`, the queue's own hash, matched in `ids` as `"email_sha256":"<hash>"`, only on rows with `order_id` 0.
- Before #10955 such a row kept the hashed e-mail after the person's erase until it was sent, given up or swept at 30 days.

## What is not matched?
- A row sent or given up: its `ids` is NULL, it names nobody and stays for the 30-day sweep (`ad-conversions-queue.md`).
- A registration's event id `reg-<user id>` is not used to find rows; the e-mail hash is.

## What does the export show?
- Section `ad_conversions` ("Ad service events") after `consents`: the rows of the person's orders only.
