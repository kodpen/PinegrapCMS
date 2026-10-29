---
name: collection-revisions
description: Collection item revisions (record_revisions, 11.0.123, C7a #3068; History and restore C7b #3099): which items, which writers, what a row holds, the History section, restore, retention, erasure
module: record_revisions
---

## Which records get revisions?
- Only items of forms with `custom_form_pages.collection = 1` (architect, #3051): enquiries and other submissions get none, so running sites change nothing until a form is marked a collection. `pg_record_revisions_ready()` needs the table and `pg_coll_ready()`.

## Which saves write one?
- Each writer calls `pg_record_revision_before($form_id)` before its write and `pg_record_revision_after($form_id, $before, $source, $user_id)` after the address-name rename: `edit_submitted_form.php` (`edit`), `api_form_submission_update()` (`api`), `ws_change_write_form()` (`workspace`). `restore` is the C7b History section. Other paths (CSV import, bulk list actions, the visitor's own form) write none.
- `restore`: the edit save of a revision loaded from the History section (below).
- `pg_record_revision_plan()` (pure): with history, one row when the hash differs from the newest row; without, the state before is seeded as revision 1 (source `edit`, signed and dated by the record's last save: `last_modified_*`, else `user_id` / `submitted_timestamp`), then the new state, only when the save changed it. No migration backfill.
- The hash (`pg_record_revision_hash()`) is SHA-1 of `values_json` and the address name: a save that only flips `complete` writes none.

## What does a row hold?
- `form_id` (= `forms.id`), `page_id` (its form), `revision_no` (per item, `COALESCE(MAX)+1` in the INSERT, unique `(form_id, revision_no)`; retries by `pg_page_revision_retry_delay()`), `values_json` MEDIUMTEXT, `record_json` TEXT (`address_name`, `complete`), `values_hash`, `user_id`, `source` (edit/restore/api/workspace; anything else becomes `edit`), `created_at`. No foreign keys.
- `values_json` (`pg_record_revision_values()`, pure): field id => list in `form_data.id` order; a file as `{"file_id": n}` only (the file is never copied: a restore whose file is gone brings none back and says so); empty values and blank rows dropped, so a blank row written or removed is no change.
- A row above MEDIUMTEXT / TEXT (`pg_record_revision_fits()`) is skipped and logged ("Item revisions: …"); a failed write is logged. The save is never stopped.

## Where are History and restore?
- `designer/collection-history.md` (C7b, #3099).

## How long are they kept?
- The page settings (`pg_page_revisions_retention()`, `pg_page_revisions_expired()`): no config column of their own; the Features screen says "Revisions Kept per Page or Item". `pg_record_revisions_trim()` after each write.
- `job.php` runs `pg_record_revisions_sweep()` daily (state `data/temp/record_revisions_sweep.json`): rows whose `forms` row or `custom_form_pages` row is gone (no delete path knows the table), then items over `keep`.

## What does an erasure do?
- `pg_privacy_erase_tables()` entry `record_revisions` deletes by `form_id` in scope `revision_form_ids` = the records erased plus the records of the blanked address cells (`pg_privacy_erase_revision_form_ids()`, pure). The export shows current values only (no revisions).

## Where are the tests?
- `tests/record_revisions_test.php` (values, hash, plan, size, sources, writers' order, erasure list, migration step), `tests/privacy_erase_test.php` (pinned table list).

Related: docs/memory/designer/collection-history.md, docs/memory/designer/page-revisions.md, docs/memory/designer/collection-fields.md, docs/memory/core/personal-data-erasure.md
