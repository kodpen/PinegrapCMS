---
name: api-form-submission-writes
description: POST /forms/{id}/submissions, its delete twins, GET /forms/{id}/fields: the create and delete path shared with the submitted form screens, answers, refusals, events
module: submitted_form_writes
---

## Which write path do the routes use?
- `includes/fn/submitted_form_writes.php`, shared with the screens. `pg_submitted_form_create_check()` and `pg_submitted_form_create()` are the Add Submitted Form screen's checks and insert (`add_submitted_form.php` calls them too). `pg_submitted_form_delete()` is the one delete of `edit_submitted_form.php`, `delete_submitted_forms.php`, `view_submitted_forms.php` and the API. It removes files, rows, signatures, views and the comment thread (`data/submitted-form-delete-thread.md`) and announces `form.submission.deleted` {id, form_id, reference_code}. The import undo (`pg_import_unwrite_record()`) keeps its own delete and stays quiet.

## Why the staff screen and not custom_form.php?
- An API caller is staff (forms scope: role < 3 or the forms right). The visitor path also does the CAPTCHA, the fill-time and spam limits, contact creation, memberships and the confirmation page, and staff are exempt from those checks. So the route records what Add Submitted Form records: the owner is the submitter, office-use fields can be answered, and no visitor columns are written.

## How are the answers sent and checked?
- `values` is a list of {name, value}, addressed by field name. A check box group or a multiple pick list sends its name once per choice. `api_form_submission_answers()` (pure) refuses: an unknown name, a file upload or signature field, a value that is not one of a choice field's option values, a single-answer field sent twice, and an answer longer than `api_form_answer_max_length()` (10000, or `api_form_html_max_length()` for a rich text area). Each answer goes through `api_form_answer_check()`, shared with the update. The answers are put into a live form (`liveform('api_form_submission', $page_id)`, removed afterwards). The screen's checks read it: required fields only when `complete` (default true), dates, times, e-mail, a Title whose address name is taken, and collection fields. The first refusal is a 422 on `values`.
- `api_dry_run_stop()` comes after these checks. A complete record announces `form.submitted` (the payload of `custom_form.php`, `contact_id` null). `send_notifications` sends the visitor e-mails, the same as the screen's switch. Answers 201 with the FormSubmission.

## Rich text, author, Status field, record address?
- `api-form-rich-text-in.md`.

## What does GET /forms/{id}/fields answer?
- The FormField list of `GET /forms/{id}` (`api_form_fields_present()`, which both use), as one page, under the same folder rule.

Tests: `tests/api_forms_test.php`, `tests/submitted_form_delete_comments_test.php`.
