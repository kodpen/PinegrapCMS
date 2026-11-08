---
name: api-form-submission-revisions
description: The History of a collection item over the API: list revisions, one compared with the record, restore (dry_run), through includes/fn/record_revisions.php
---

## Which routes?
- `forms.submission_revisions` (GET, cursor paged newest first, `forms:read`): FormSubmissionRevision rows (`revision_no`, `source`, `user_id`, `user`, `address_name`, `complete`, `current` = its hash is the stored record's).
- `forms.submission_revision_get` (GET `…/revisions/{no}`): the revision plus `values`, field by field in form order (`pg_record_revision_compare()`): `values`/`file_id` beside `current_values`/`current_file_id`, `changed`, `restore`.
- `forms.submission_revision_restore` (POST `…/revisions/{no}/restore`, `forms:write`, `dry_run`): answers `{submission, restored_from, revision_no, changed, fields}`.

## Which access and refusals?
- The record is loaded with `api_form_submission_owned()` (the folder rule, 404 outside), as the screen's History section shows only to `$submitted_forms_manager`.
- `api_form_revisions_ready()`: 503 `service_unavailable` before the table exists, 409 `not_a_collection` when `pg_record_revision_history_enabled()` is false (the screen hides History then). Unknown number: 404.

## What does a restore write?
- What the screen's Load + save writes, without the edit form: `pg_record_revision_restore_values()` deletes and rewrites each `value` field (`pg_record_revision_restore_rows()`, pure: one row per value, typed as `pg_submitted_form_save_value()` types it, an empty field one empty row; rich text through `pg_sanitize_rich_text(…, 'staff')`), then `pg_record_revision_restore_files()` attaches the revision's files that still exist.
- `restore` per field: `value`, `file`, `gone`, `kept` (signature or no file in the revision), `unchanged`; `fields` lists those not unchanged.
- Then `last_modified_*`, the address name from the title on pretty URLs (`update_submitted_form_address_name()`), `pg_record_revision_after(…, 'restore', owner)`, `log_activity()`. `complete` stays. No webhook (the update route announces none either).
- When the revision's hash equals the record's, nothing is written: `changed` false, `revision_no` null.

## Where are the tests?
- `tests/api_form_revisions_test.php`; the writers' order in `tests/record_revisions_test.php` reads the two API handlers one by one.
