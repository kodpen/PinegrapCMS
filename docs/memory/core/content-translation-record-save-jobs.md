---
name: content-translation-record-save-jobs
description: Translate on save for form records: who opens a translation job by saving a record (never a visitor), the records scope, and the save paths that are not hooked
module: translate
---

## What happens when a record is saved?
`pg_tr_records_saved($form_page_id, $user_id)` (`includes/fn/translate.php`): when a page draws the form's records translated (`translation_uses` rows of owner `form_records`, or a `page_ref` row naming them) and a language has "translate on save" with Pinegrap AI, Claude or Google ready (`pg_tr_auto_languages()`, `pg_tr_engine()`), `pg_tr_auto_update($auto, 'records:<form page id>')` opens one job per language: same lock and open-job guard as a page save ([[content-translation-jobs-duplicates]]), no engine call during the save. With no engine ready the records are not read again and nothing else changes.

## What does the records scope cover?
`pg_tr_scope_owners('records:14')` is `form_records` 14; `pg_tr_scope_extract()` reads the form's records again (`pg_tr_extract_form_records()`) only while a widget still includes them (`pg_tr_record_forms()`). The job carries every pending text of the form's records, as a page job carries the page's. The Translations screen names such a job by the Forms group's label.

## Who may open one?
Only a signed-in user who may edit the form's records (owner decision 2026-11-09, `workflow/decisions-made-product-38.md`):
- `add_submitted_form.php` (completed records) and `edit_submitted_form.php` when `$submitted_forms_manager` (a submitter or form editor editing their own record does not);
- `custom_form.php` on the site when `pg_tr_records_editor(role, manage_forms, check_edit_access(folder))`: a manager or above, or the forms right with edit access to the form's folder;
- the external API with a key (`api_form_submission_create()` completed, `api_form_submission_update()` on a complete record).
A visitor's submission never does: anybody could otherwise spend the site's engine credit by posting a form.

## What is not hooked?
The submitted forms list's quick edit (status), CSV import, the Workspace's record changes and a revision restore: their texts are picked up by the next record save, page job or "Update translations".

## Why?
A record save opened no job, so with list records counting toward the verdict a new blog post kept `/en/blog` out of search until someone translated it by hand. Now a list never keeps a page out, and the job brings the record's own page into search once reviewed.
