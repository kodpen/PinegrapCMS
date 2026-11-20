---
name: decisions-made-schema-45
description: Decisions list, schema part 45 (2026-11-20 on): the satisfaction loop's two tables, feedback_surveys (a survey's settings) and feedback_responses (one row per sent survey), their columns (poll 197)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-44.md`.

## Does the satisfaction loop get tables? (owner, 2026-11-20, agent channel poll 197, channel message 10839)
- "Evet, iki tablo eklensin": `feedback_surveys` and `feedback_responses`. No column is added to an existing table; the person's export and erase cover them (an erase keeps the score and removes the person and the comment).
- Why two: a survey sits in its own table and each answer names it by id, which the report and the API read.
- Not chosen: one table with the surveys in a settings text (a deleted survey's old answers would stand nameless in the report).
- The plan is the owner's yes to poll 187 (the satisfaction loop); step FD1b.

## Which columns does feedback_surveys have? (architect, 2026-11-20)
- `id` INT UNSIGNED AUTO_INCREMENT; `name` VARCHAR(150); `kind` TINYINT UNSIGNED (1 NPS, 2 CSAT, 3 effort); `touchpoint` VARCHAR(30), a key in code (`periodic` for the periodic NPS).
- `filter` TEXT NULL, JSON (service, event, course, form with its days or field value, mail box; the periodic months); `delay_minutes` INT UNSIGNED; `channel` VARCHAR(10).
- `purpose` TINYINT UNSIGNED (0 service message, 1 commercial; `decisions-made-product-91.md`).
- `fallback_user_id`, `escalation_user_id`, `ws_channel_id` INT UNSIGNED; `review_invite` TINYINT UNSIGNED; `texts` TEXT NULL, the question, comment label and up to three extra questions per language as JSON.
- `enabled` TINYINT UNSIGNED, 0 for a new survey; `archived_at` INT UNSIGNED, set in place of a delete once a survey has rows; `last_periodic_at`, `created_by`, `created_at`, `updated_at` INT UNSIGNED.
- Keys: (`enabled`, `touchpoint`).

## Which columns does feedback_responses have? (architect, 2026-11-20)
- One row per sent survey. `id` INT UNSIGNED AUTO_INCREMENT; `survey_id` INT UNSIGNED; `contact_id` INT UNSIGNED, 0 for none; `person_hash` CHAR(40) ascii, the hash of the folded e-mail or number; `recipient` VARCHAR(190).
- `record_type` VARCHAR(20) and `record_id` INT UNSIGNED, the booking, ticket, course, form, order or conversation; `subject_id` INT UNSIGNED, its service, event, course or form page; `staff_user_id` INT UNSIGNED.
- `channel` VARCHAR(10); `status` TINYINT UNSIGNED (0 queued, 1 sent, 2 answered, 3 cancelled, 4 no answer); `due_at`, `sent_at`, `reminded_at`, `answered_at` INT UNSIGNED; `token_hash` CHAR(64) ascii.
- `token_hash` is NULL DEFAULT NULL (architect, 2026-11-21): the token signs the row id, so the hash is written in the same call after the insert; '' would break the unique key. Lookups by equal hash only; the job skips a NULL hash.
- `score` TINYINT UNSIGNED, read only with `answered_at` set, since 0 is an NPS answer; `band` TINYINT UNSIGNED (1 low, 2 middle, 3 high); `comment` TEXT NULL; `answers` TEXT NULL, the extra questions as JSON.
- `stopped` TINYINT UNSIGNED, "Don't ask me again" said on this row; no table of its own.
- `owner_user_id`, `task_id`, `escalated_at` INT UNSIGNED; `outcome` TINYINT UNSIGNED (0 none, 1 called, 2 solved, 3 not reached); `created_at` INT UNSIGNED.
- Keys: unique (`token_hash`); (`person_hash`, `created_at`), the 30 days and the stop; (`status`, `due_at`), the job's pass; (`survey_id`, `answered_at`); (`record_type`, `record_id`), the cancels; `contact_id`; (`staff_user_id`, `answered_at`).

## What else holds?
- Every column NOT NULL with its default but the TEXT ones and `token_hash`. InnoDB, utf8mb4_unicode_ci.
- An erase sets `contact_id` 0, empties `person_hash` and `recipient` and nulls `comment` and `answers`; the score, band and dates stay.
- The step's worker writes the columns into its PR body and asks the architect before it is marked ready.
