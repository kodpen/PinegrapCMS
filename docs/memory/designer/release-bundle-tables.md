---
name: release-bundle-tables
description: Release bundles' two tables, statuses and moves, the hold on a bundled draft, the copy of a live page and "changed since" (includes/fn/page_bundles.php)
module: page_bundles
---

## What is kept?
- `page_bundle` (step 11.0.228): name, status, `publish_at`, who made and who published it, `failed_page_id` and `failed_reason` for a publish that stopped. `page_bundle_item`: the draft, `live_page_id` (0 for a new page), `live_state`, its own status (`waiting` until written), `revision_before` and `written_state` for the take-back.
- `pg_page_bundles_ready()` probes both tables; every reader returns empty before the upgrade.

## Which statuses and moves are there?
- `open`, `scheduled`, `published`, `partial` (partly published), `reverted` (taken back). `pg_page_bundle_may_move()`: open and scheduled to each other, both to published or partial; partial to published or reverted; published to reverted. Reverted is closed.
- Pages are added or removed only while open or scheduled (`pg_page_bundle_may_change()`). Delete: open, scheduled, reverted; a published or partial bundle keeps the record its take-back reads.
- `pg_page_bundle_move()` keeps the old status in the WHERE, so two clicks or the job and a person never move it twice.

## Which drafts are held, and where is it refused?
- An item `waiting` in an open, scheduled or partial bundle (`pg_page_bundle_hold()`). `pg_experiment_variant_hold_message()` answers it after the experiment's own hold, so the page save (published alone), both bin paths and `DELETE /pages/{id}` refuse it, naming the bundle; `pg_experiment_sheet_variant_choices()` leaves it out, so it is never made a variant.
- The API's delete answers it with the same `409 experiment_variant_held` code for now.
- The Publish sheet does not mark a bundled draft as held yet: its texts speak of an experiment and live in `style_designer.src.js`, which RB1 did not touch.

## How is a page added?
- One open bundle per draft and per live page: `pg_page_bundle_add_conflict()` over `pg_page_bundle_open_items()`, checked in code because closed bundles keep their rows.
- Add a copy (`pg_page_bundle_add_copy()`): a live visual-design page (`pg_experiment_sheet_live_page()`, so no classic page), not with an open experiment (architect, agent channel message 5763), copied with `duplicate_page()` into Drafts and a `page_drafts` row naming the live page's folder, as an experiment's variant is.
- Add a draft (`pg_page_bundle_add_draft()`): a visual-design draft that is no open experiment's variant; its own `publish_at` is cleared and `time_cleared` says so for the screen.

## What is "changed since"?
- `live_state` is `pg_page_bundle_state_hash()` of the live page's tree, title and description, read before the copy is made, so a publish in between is seen, never missed. `pg_page_bundle_item_changed()` compares it with now; a live page gone or moved to Drafts counts as changed.
