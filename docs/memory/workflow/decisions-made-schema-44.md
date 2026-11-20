---
name: decisions-made-schema-44
description: Decisions list, schema part 44 (2026-11-20 on): the application review desk's four tables and their columns, no reviewer right column (poll 199)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-43.md`.

## Does the application review desk get tables? (owner, 2026-11-20, agent channel poll 199, channel message 10841)
- "Evet, dört tablo eklensin": `application_programs`, `application_reviewers`, `application_entries`, `application_reviews`. No column is added to `forms` or `user`.
- With it: a reviewer is one by being added to a programme, no new right column; a juror with no panel sign-in works through a signed link.
- Not chosen: the four tables with a "jury only" right column.
- The plan is the owner's yes to poll 196; step AR1b.

## Which columns does application_programs have? (architect, 2026-11-20)
- `page_id` INT UNSIGNED, the form's page as `forms.page_id`; `name` VARCHAR(150); `status` TINYINT UNSIGNED (0 draft, 1 open, 2 closed); `opens_at`, `closes_at` INT UNSIGNED.
- `category_field_id` INT UNSIGNED; `reviewers_per_entry`, `round`, `blind`, `waitlist_auto` TINYINT UNSIGNED.
- JSON in TEXT NULL: `rounds` (each round's criteria and weights, end, cut-off, spread bound, the score that ranks), `eligibility`, `blind_fields`, `texts`.
- `created_by`, `created_at`, `updated_at` INT UNSIGNED. Keys: (`page_id`, `status`). Criteria templates sit in one `config_text` row.

## Which columns does application_reviewers have? (architect, 2026-11-20)
- `program_id` INT UNSIGNED; `user_id` INT UNSIGNED, 0 for an outside juror; `name` VARCHAR(150) and `email` VARCHAR(190), an outside juror's.
- `token_hash` CHAR(64) ascii NULL, the hash of an outside juror's link, NULL for a panel user; `categories` TEXT NULL, the pool as JSON, NULL for all; `revoked_at`, `last_seen_at`, `created_at` INT UNSIGNED.
- Keys: unique (`program_id`, `user_id`, `email`); unique (`token_hash`); `user_id`.

## Which columns does application_entries have? (architect, 2026-11-20)
- One row per submission in a programme. `program_id` INT UNSIGNED; `form_id` INT UNSIGNED, the `forms` row; `category` VARCHAR(100); `round` TINYINT UNSIGNED.
- `eligible` TINYINT UNSIGNED (0 not checked, 1 yes, 2 no, 3 overridden); `eligibility_note` TEXT NULL, the reasons and an override's note as JSON.
- `score_raw`, `score_adjusted`, `spread` SMALLINT UNSIGNED, each times 100; `consensus` TINYINT UNSIGNED (0 none, 1 needed, 2 settled); `shortlisted` TINYINT UNSIGNED.
- `decision` TINYINT UNSIGNED (0 none, 1 accepted, 2 rejected, 3 waitlisted, 4 declined); `decided_at`, `decided_by`, `mailed_at` INT UNSIGNED.
- `fix_token_hash` CHAR(64) ascii NULL and `fix_fields` TEXT NULL, the correction link; `created_at`, `updated_at` INT UNSIGNED.
- Keys: unique (`form_id`); unique (`fix_token_hash`); (`program_id`, `round`, `category`); (`program_id`, `decision`).

## Which columns does application_reviews have? (architect, 2026-11-20)
- One row per entry, reviewer and round. `program_id`, `entry_id`, `reviewer_id` INT UNSIGNED; `round` TINYINT UNSIGNED; `status` TINYINT UNSIGNED (0 assigned, 1 started, 2 submitted, 3 conflict).
- `scores` TEXT NULL, each criterion's score and note as JSON; `total` SMALLINT UNSIGNED, the weighted sum times 100; `conflict_reason` VARCHAR(500); `assigned_at`, `submitted_at`, `reminded_at` INT UNSIGNED.
- Keys: unique (`entry_id`, `reviewer_id`, `round`); (`reviewer_id`, `status`); (`program_id`, `round`, `status`).

## What else holds?
- Each table's `id` is INT UNSIGNED AUTO_INCREMENT. Every column NOT NULL with its default but the TEXT ones and the two hashes. InnoDB, utf8mb4_unicode_ci.
- The person's erase removes their entries with their reviews; an erased outside juror keeps the reviews, without name and e-mail. The export: `decisions-made-product-91.md`.
- The step's worker writes the columns into its PR body and asks the architect before it is marked ready.
