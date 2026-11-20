---
name: application-review-store
description: Application review desk tables (11.0.258, #10832): defaults, personal data erase and export of them, audit_log types application_program and application_entry
---

## Which tables hold the desk?
- `application_programs`, `application_reviewers`, `application_entries`, `application_reviews`, made by `upgrade_2026_11_0_application_review()` (11.0.258) and listed in `get_tables()`. Columns and keys: `workflow/decisions-made-schema-44.md`; `tests/application_review_store_test.php` pins every line of the four CREATE TABLE statements.
- Defaults chosen in the step: `reviewers_per_entry` 2, `round` 1 (programme, entry, review); every other number 0, text '', TEXT and the two hashes NULL.
- Scores (`score_raw`, `score_adjusted`, `spread`, `total`) are stored times 100; `pg_application_export_score()` gives them back.
- `token_hash` and `fix_token_hash` are NULL when there is no link, never '': both are unique keys.

## What does the personal data erasure do?
- `pg_privacy_erase_tables()`: `application_reviews` (by `entry_id` in the scope's `application_entry_ids`) and `application_entries` (by `form_id` in `form_ids`) are deleted, before `forms`; the person's applications go with their submissions and the reviews given on them.
- `application_reviewers` found by `user_id` or `email` is kept: `name` '', `email` the person token. `_pg_privacy_erase_apply_application_reviewers()` first sets `token_hash` NULL and `revoked_at`, so the juror link stops; the reviews they gave on others' entries stay.
- The scope's `application_entry_ids` also finds the trail's `application_entry` lines; their label is cleared (`pg_privacy_erase_audit_row()`).
- Each table has a line in `pg_privacy_inventory()`.

## What does the personal data export give?
- Section `applications`, after `signatures`, for the person's submissions: id, form, programme name, category, round, eligible, shortlisted, decision, decided_at, score_raw, score_adjusted, and `reviews` (round, total, criterion => score) of submitted reviews (`status` 2).
- Never the reviewer or a note (owner, poll 200, `workflow/decisions-made-product-91.md`): the reviewers' table is not read, `eligibility_note` is not read, and `pg_application_export_review()` keeps only each criterion's whole `score`. The test pins the two SELECT column lists.
- The stored `scores` JSON is criterion id => {score, note}.

## Which audit_log record types does it use?
- `application_program` ("Application programme") and `application_entry` ("Application") in `pg_audit_view_labels('type')`. A score, conflict, override, decision or mail is written on the entry, so the erasure finds it. No edit screen link yet (AR2 on).

Related: docs/memory/core/application-review-rules.md, docs/memory/core/personal-data-erasure.md, docs/memory/core/personal-data-export.md, docs/memory/plans/application-review.md
