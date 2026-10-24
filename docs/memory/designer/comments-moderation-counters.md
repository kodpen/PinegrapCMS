---
name: comments-moderation-counters
description: Comment moderation on designed record pages: the stored row decides, edit_comment.php and the API rebuild submitted_form_info and mail the record's submitter unless they wrote it
---

## Which comments belong to a form record?
- Those whose row has `item_type` 'submitted_form' and `item_id` > 0 (`pg_comment_record_id()`, `includes/fn/comments.php`). `add_comment.php` stores that only after its record-page check ([[comment-thread-verify]]).
- Never decide from `page.page_type`: a page built in the visual designer that shows a record (a `form_item_view` widget) is 'standard'.

## What symptom did this fix?
- On a designed record page, approving, holding, scheduling or deleting a comment in Comments > Edit Comment or through the API left the list's comment count at 0 or too high, the newest comment and last activity stale, and the piece's author was never mailed on approval. Both paths keyed on page type 'form item view'.

## What does moderation rebuild?
- `pg_comment_refresh_record_info($page_id, $item_type, $item_id)` rebuilds the (record, page) row of `submitted_form_info`: `number_of_comments` (published comments of that thread), `newest_comment_id` (created_timestamp DESC, id DESC; 0 when none), `number_of_views` kept with `MAX()`. Duplicate rows collapse into one; every value is an integer.
- Not a record comment: no query at all. A failed query is logged (`error_log()` behind `function_exists`) and answers false; a failed read writes nothing. It never calls `db()`, whose error screen would break the API and MCP.
- `comment_attachments` is a live subquery, not a stored counter; it only needs the row to exist.

## Which paths call it, and when?
- `edit_comment.php`: after the delete, and on every save (publish, hold, schedule, edit) right after the UPDATE.
- API `POST /comments/{id}`: after an update that sends `published` (not `featured` alone); `POST /comments/{id}/delete`: after the delete.
- `add_comment.php`, `job.php` (scheduled publish) and `submit_order.php` keep their own copies of the rebuild: the first two key on the row's item type already, `submit_order.php` maps classic page types only.

## Why before the e-mails?
- The subjects of the submitter and watcher e-mails (`^^number_of_comments^^`, `^^newest_comment^^`) read that row (`get_variable_submitted_form_data_for_content()`), so they show the count after the change, as on `add_comment.php`.

## Who is mailed on a first publish?
- The record's submitter when `pg_comment_submitter_mail_due()` is true: a record comment, the page's `comments_submitter_email_page_id` > 0, and the writer (`comments.created_user_id`) is not the submitter (`forms.user_id`). 0 is anonymous and never matches, as on `add_comment.php`.
- Watchers as before (watcher e-mail page set). The API dry run's `emails` flag uses the same rule.
- Changed on classic pages: approving the submitter's own held comment no longer mails them; a comment with item type '' writes no counter row.

## What is the known limit?
- Released sites keep counters that went wrong on designed record pages; each (record, page) pair is right again after its next comment write or moderation. A one-time recount is a separate data step.

## What is left?
- `includes/api/schema.php` comments.update text: add "unless they wrote it".
- `job.php`: switch to the helper and gate its submitter mail with the writer rule (today a staff-scheduled comment by the submitter mails them).
- `includes/fn/mail.php`: the admin e-mail link is built from page type (no `?r=` on a designed page); the submitter mail's From address comes via `form_item_view_pages`, which a designed page lacks.
- `submit_order.php` product comments; `duplicate_page_f.php` copies no counter rows; a submitter who also watches gets two e-mails.
