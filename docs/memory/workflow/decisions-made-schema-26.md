---
name: decisions-made-schema-26
description: Decisions list, schema part 26 (2026-11-16 on): the release bundle's two tables as landed, page_bundle and page_bundle_item
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. `site_push_watch` and `file_translation`: `decisions-made-schema-25.md`.

## Which tables keep a release bundle? (owner, 2026-11-16, agent channel poll 65; columns the architect's, agent channel message 6621)
- Owner (poll 65): the release bundle may add two tables, their columns confirmed by the architect in the step's draft PR.
- Architect (message 6621, on RB1's draft PR #9961): `page_bundle` and `page_bundle_item`, step 11.0.228, as drafted, with three columns added on `page_bundle`, each INT UNSIGNED NOT NULL DEFAULT 0: `scheduled_by` (who set the time; the job publishes in that person's name; written with the time, 0 when it is cleared), `reverted_by` and `reverted_at` (written by RB3).
- Rules that came with the yes: after an item's insert the waiting rows of the same draft and of the same live page are read again and the smaller id stays; `mysqli_insert_id()` is read right after the INSERT; `failed_reason` is cut at 500 characters, never translated later, and cleared with `failed_page_id` when a new publish starts.
- What each column keeps, the statuses and the moves: `designer/release-bundle-tables.md`.
