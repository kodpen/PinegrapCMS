---
name: feedback-store
description: Satisfaction survey tables feedback_surveys and feedback_responses (11.0.263, FD1b): defaults, NULL token_hash, person_hash of a number, personal data erasure, export and inventory of the answers
---

## Which tables hold the satisfaction surveys?
- `feedback_surveys` (a survey's settings) and `feedback_responses` (one row per sent survey), made by `upgrade_2026_11_0_feedback_surveys()` (11.0.263) and listed in `get_tables()`. Columns and keys: `workflow/decisions-made-schema-45.md`; `tests/feedback_store_test.php` pins every line of both CREATE TABLE statements.
- Defaults chosen in the step: `kind` 1 (NPS); every other number 0, text '', TEXT NULL. A new survey is off (`enabled` 0).

## Why is token_hash NULL and not ''?
- The answer token signs the row id (`pg_feedback_token_make()`), so the row is inserted first and the hash written after. With a unique key, two rows waiting for their link with '' would collide; NULL never does (architect, 2026-11-21, `workflow/decisions-made-schema-45.md`).
- Its terms: the writer sets the hash in the same call as the insert, so no row stays NULL past it; the answer page finds a row by an equal hash only; the job's pass skips a row whose hash is NULL.

## How is person_hash made?
- `pg_feedback_person_hash()`: SHA-1 of `pg_feedback_fold_person()` (address trimmed and lower-cased, number digits only). A number is hashed in the form `pg_sms_number()` gives (country code, no `+`), the form the personal data export and erasure read numbers in; another form (a leading 0) gives another hash and the person's rows are not found.
- `pg_feedback_person_hashes($list)` hashes a list once each; the export and erasure use it.

## What does the personal data erasure do?
- `pg_privacy_erase_tables()` entry `feedback_responses`: found by `contact_id` (`contact_ids`) or `person_hash` (scope key `feedback_hashes`, the hashes of the person's addresses and numbers, built after `phones`); anonymised: `contact_id` 0, `person_hash` '', `recipient` '', `comment` and `answers` NULL. Score, band and dates stay for the report (owner, poll 197).
- `_pg_privacy_erase_apply_feedback_responses()` first sets a queued row (`status` 0) to cancelled (3), so the job never sends to an empty recipient.
- `pg_privacy_erase_set()` writes a `null` field as SQL `NULL` (added here).
- `feedback_surveys` names nobody and is in neither list.

## What does the personal data export give?
- Section `feedback` ("Satisfaction surveys"), after `bookings`: id, survey name, kind, recipient, record type and id, channel, status, sent and answered times, score, band, comment, extra answers, stopped, outcome, created. Found by contact or by the hashes of the person's addresses and of the numbers on their contacts and orders.
- Never `token_hash`, `person_hash` or the staff columns (`staff_user_id`, `owner_user_id`, `task_id`). `pg_feedback_export_row()` gives the score only when `answered_at` is set (0 is an NPS answer) and decodes `answers`.

## What happens on a contact merge?
- `pg_contact_holders()` line `feedback_responses` (move, find): `merge_contacts()` moves the answers to the contact that stays.

## Inventory?
- `pg_privacy_inventory()` line `feedback_responses`: contact and messages, customers, legitimate interest, until erased, person column `contact_id`.

Related: docs/memory/platform/feedback-rules.md, docs/memory/core/personal-data-erasure.md, docs/memory/core/personal-data-export.md, docs/memory/core/privacy-inventory.md, docs/memory/plans/feedback-loop.md
