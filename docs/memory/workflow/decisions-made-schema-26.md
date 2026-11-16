---
name: decisions-made-schema-26
description: Decisions list, schema part 26 (2026-11-16 on): page_bundle and page_bundle_item as landed; ws_tasks.app_id; ws_profiles.meeting_url; gift_cards.kind gets referral
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. `site_push_watch` and `file_translation`: `decisions-made-schema-25.md`.

## Which tables keep a release bundle? (owner, 2026-11-16, agent channel poll 65; columns the architect's, agent channel message 6621)
- Owner (poll 65): the release bundle may add two tables, their columns confirmed by the architect in the step's draft PR.
- Architect (message 6621, on RB1's draft PR #9961): `page_bundle` and `page_bundle_item`, step 11.0.228, as drafted, with three columns added on `page_bundle`, each INT UNSIGNED NOT NULL DEFAULT 0: `scheduled_by` (who set the time; the job publishes in that person's name; written with the time, 0 when it is cleared), `reverted_by` and `reverted_at` (written by RB3).
- Rules that came with the yes: after an item's insert the waiting rows of the same draft and of the same live page are read again and the smaller id stays; `mysqli_insert_id()` is read right after the INSERT; `failed_reason` is cut at 500 characters, never translated later, and cleared with `failed_page_id` when a new publish starts.
- What each column keeps, the statuses and the moves: `designer/release-bundle-tables.md`.

## Which column marks a task an application created? (owner, 2026-11-16, agent channel poll 80; shape the architect's, agent channel message 6897)
- Owner (poll 80): one column on `ws_tasks` (`decisions-made-schema-23.md`).
- Architect (message 6897, on AW3's draft PR #9994): `app_id` INT UNSIGNED NOT NULL DEFAULT 0 after `creator_id`, 0 for a person, the name the other Workspace tables give the writing application. No backfill.
- Index `idx_app (app_id, status)`, the pattern of `idx_delegate` and `idx_department` on the same table: the lists filter by status with it, and `made_by=apps` is the call an agent repeats. "Made by agents" reads `app_id > 0`, "Made by people" `app_id = 0`.
- The column only says who created the task: no right is read from it. A deleted application's number stays on the row. No other column or table for the plan.

## Which column keeps a member's meeting address? (architect, 2026-11-16, on SL1's draft PR #10031)
- `ws_profiles.meeting_url` VARCHAR(500) NULL after `quiet_hours`, by `install_add_column()`, step 11.0.232; no index, no backfill. The owner had asked that what exists be grown instead of a table (poll 84).
- Written only through `ws_meeting_url_clean()`; an empty address is stored as NULL and readers treat NULL and '' alike.

## How does a gift card become a referral reward? (architect, 2026-11-16, agent channel message 7257, on RF1's draft PR #10033)
- `gift_cards.kind` is widened with the value `referral` in step 11.0.233: the step reads the column's values and appends the new one; the install definition and the kinds list get it too. No table.
