---
name: submitted-form-delete-thread
description: What deleting a submitted form removes besides forms/form_data (comments, attachments, notifications, watchers, counters), the shared helper and the three delete paths.
---

# Deleting a submitted form

## What does a form record delete remove?

`forms` and `form_data` rows, the record's uploaded files, its signatures
(`pg_signature_delete()`), its view rows (`pg_sfv_delete_views()`) and, through
`pg_comment_delete_record_thread()` (`includes/fn/comments.php`), everything
that names the record as `item_type = 'submitted_form'`, `item_id = <id>`:
`comments` (on every page), their `notifications` (by `comment_id`), their
attachment `files` unless a comment outside the thread shares the `file_id`
(duplicated pages copy it), `watchers`, `allow_new_comments_for_items`, and
`submitted_form_info` by `submitted_form_id`.

## Which paths delete a form record?

`edit_submitted_form.php` (Delete button), `delete_submitted_forms.php` (bulk)
and `view_submitted_forms.php` (list delete). A new delete path calls the
same three helpers; `tests/submitted_form_delete_comments_test.php` checks the
three files.

## Why?

No foreign key ties these tables to `forms`. Before #4803 the comments of a
deleted booking or reservation (private messages) stayed in the database and
in Panel › Comments with no page or record link. Comments orphaned before the
fix are not cleaned by a migration.
