---
name: comments-widget-watch-staff
description: The comments widget's watch form, watcher list and staff open-or-close form: wiring, who gets them, the post thread, address masking, count text, Messages re-stamped
---

## How are the small forms wired?
- `pg_comments_apply_control_bindings()`, outside the loop_area, one of each: a semantic form bound to `comment_watch_form` posts to add_or_remove_watcher.php (`id="software_watcher"`, the handler's anchor) for a visitor who may watch; one bound to `comment_allow_new_form` posts to allow_or_disallow_new_comments.php (`id="software_allow_or_disallow_new_comments"`) for staff. Action and method are the server's; `pg_comments_small_form_hidden_html()` comes first (token, send_to, page_id, item_id, item_type, action).
- Buttons send them as submits: `comment_watch` when not watching, `comment_unwatch` when watching, `comment_close` while open, `comment_reopen` while closed. Only a semantic button or button component inside its own form; elsewhere, not applying, or on a link it is dropped (with its label and an emptied wrapper).
- `pg_comments_drop_nested_forms()` drops a bound form, and the `comment_watchers` section, inside any form.
- Their Messages nodes are re-stamped after `_pg_inject_messages_node($tree, 'add_comment')` by `pg_comments_stamp_form_messages()`: `add_or_remove_watcher` and `allow_or_disallow_new_comments`. After the draw both add_comment and add_or_remove_watcher states are removed; a design without a Messages node in its watch form loses those notices.

## Who gets the watch box?
- `is_watching` / `not_watching`: watching on (watcher e-mail page), signed in, not the record's submitter when the page mails them (told anyway, as on the classic block). `can_watch` is either.
- The form's watcher checkbox (`pg_comments_watcher_box()`): signed in, not a watcher, not told another way (moderator address, form editor, conditional administrator, the record's submitter); ticked unless a post said not.
- All watch facts, the count and the list read the thread the forms post to (`post_type`/`post_id`), as the handler does, not the page read.

## Who manages watchers, and what do they see?
- `can_manage_watchers`: staff, or on a record thread its form editor, or its submitter (user id or e-mail) when `comments_watchers_managed_by_submitter` is on: add_or_remove_watcher.php's rule, checked again there.
- `pg_comments_watchers_html()` (the `comment_watchers` section): fixed entries (form editor, record submitter, not removable), each watcher with a remove form (management, id), an add box (`username_or_email_address`, a refused add's text kept). Addresses (`pg_comments_watcher_label()`) only for role < 3 or their owner; others see `j***@example.com` (`pg_comments_mask_email()`). No inline style.
- `__watcher_count_text` (`pg_comments_watcher_count_text()`): the classic rule, watchers plus the form editor (shown to staff or the editor) and the mailed submitter; one person only when it is not the visitor.

## How was it checked?
- `tests/comments_widget_test.php`, `tests/designer_messages_test.php` (both small forms re-stamped).
- Sandbox, MariaDB, PHP 8.3 (2026-10-24): member watch and unwatch with their notices in the watch form; staff list (address shown), add, duplicate refused with the text kept, remove; close and reopen. Record threads and a role-3 manager: unit tests only.
