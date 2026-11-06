---
name: list-table-quick-edit-comments
description: Comments list quick edit (includes/fn/comment_list_edit.php): the fields, Published and schedule vs the publish choice, the shared check and save, who may change them
module: comment_list_edit
---

## Which cells change?
- Every value `edit_comment.php` changes (`pg_comment_quick_edit_columns()`): Name, Message (`textarea`, required as on the screen; the cell shows the first 84 characters, the field the whole text), Published, Featured, At a Scheduled Time (typed in the site's date and time format), Cancel if Added First, Rating (None, 1–5; a new column, sortable). Text: Submitted, Go To Comment. The store's reply and a question's answer are several lines and not on the list. `tests/comment_list_edit_test.php` fails for a new column that is neither.
- Per row (`pg_comment_quick_row_fields()`): Cancel if Added First only while the comment is scheduled, Rating only while its page rates comments, as the screen draws them; the write refuses the others.

## How do Published and the schedule map to the screen's publish choice?
- `pg_comment_quick_form_values()` starts from the stored choice (`pg_comment_form_publish_state()`: published / schedule / not_published). Published Yes publishes now (the schedule is cleared); No takes a published comment off the site and leaves a scheduled one scheduled. A date schedules (Cancel starts on, as the screen's switch, unless it was already scheduled); an empty date takes a scheduled comment off the schedule, unpublished.

## Who may change them?
- The comment screen's rights: role below User or a User with edit rights (`no_acl_check()`), answered by `view_comments.php` before its own access check; then `check_edit_access()` on the comment's page folder in `pg_comment_quick_update()` (403 `denied`).

## How is a change checked and written?
- `includes/forms/comment_form.php`, shared with the edit screen: `pg_comment_form_validate()` (message required; a schedule valid and in the future, so a scheduled comment whose date passed before the job ran refuses every change until it is rescheduled, as on the screen), `pg_comment_form_publish_columns()`, `pg_comment_form_save()` (parameterised UPDATE of the given columns only, so the rating is written only while the page draws stars), `pg_comment_form_after_save()` (record comment info, then the submitter's and watchers' e-mails for a comment just published). The list also recounts the product rating (`pg_review_comment_changed()`) and logs "comment on page (…) was modified".
- The answer is the row read again (`pg_comment_list_sql()` for one id, `pg_comment_list_cells()`): every cell, so publishing clears the schedule and the cancel switch follows.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
