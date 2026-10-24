---
name: comments-widget-watch-staff
description: The comments widget's watch form, watcher list and staff open-or-close form: wiring, who gets them, address masking, count text, where their messages print, canvas fades by place
---

## How are the small forms wired?
- `pg_comments_apply_control_bindings()`, outside the loop_area, one of each: a semantic form bound to `comment_watch_form` posts to add_or_remove_watcher.php (`id="software_watcher"`, the handler's anchor) for a visitor who may watch; `comment_allow_new_form` to allow_or_disallow_new_comments.php (`id="software_allow_or_disallow_new_comments"`) for staff. The server writes action and method; `pg_comments_small_form_hidden_html()` first (token, send_to, page_id, item_id, item_type, action).
- Buttons send them as submits: `comment_watch` when not watching, `comment_unwatch` when watching, `comment_close` while open, `comment_reopen` while closed. Only a semantic button or button component inside its own form; elsewhere, not applying, or on a link it is dropped (with its label and an emptied wrapper).
- `pg_comments_drop_nested_forms()` drops a bound form, and the `comment_watchers` section, inside any form.

## Where do their messages print?
- `pg_comments_stamp_form_messages()`, after `_pg_inject_messages_node($tree, 'add_comment')`, re-stamps a Messages node in a small form `add_or_remove_watcher` / `allow_or_disallow_new_comments`. With no node left for add_comment (a design kept one only in a small form), one goes first in the first container.
- `pg_comments_watchers_feedback()` (draw, visitor's tree): a drawn `comment_watchers` section gets first `<a id="software_watcher">` when nothing carries the id and a Messages node `add_or_remove_watcher` when none is left, as the classic manager area. Case: the record's submitter, told anyway, has no watch form but manages the list.
- After the draw add_comment and add_or_remove_watcher states are removed: a non-manager's watch form without a Messages node loses its notices.

## Who gets the watch box?
- `is_watching` / `not_watching`: watching on (watcher e-mail page), signed in, not the record's submitter when the page mails them. `can_watch` is either.
- The form's watcher checkbox (`pg_comments_watcher_box()`): signed in, not a watcher, not told another way (moderator address, form editor, conditional administrator, the record's submitter); ticked unless a post said not.
- Watch facts, count and list read the thread the forms post to (`post_type`/`post_id`), as the handler.
- The starter's Watch box is `pg-hide-if-empty`: signed out, nobody told, no list leaves no empty rule.

## Who manages watchers, and what do they see?
- `can_manage_watchers`: staff, or on a record thread its form editor, or its submitter when `comments_watchers_managed_by_submitter` is on: the handler's rule, checked again there.
- `pg_comments_watchers_html()`: fixed entries (form editor, record submitter), each watcher with a remove form (management, id), an add box (`username_or_email_address`, a refused add's text kept). Addresses (`pg_comments_watcher_label()`) only for role < 3 or their owner; others see `j***@example.com`.
- `__watcher_count_text` (`pg_comments_watcher_count_text()`): the classic rule, watchers plus the form editor (shown to staff or the editor) and the mailed submitter; one person only when it is not the visitor.

## What does the canvas fade for where a control sits?
- `_sdCommentsBindingOff()` asks `_sdCommentsPlace()` (ancestors in the drawn widget tree): reply/share outside the loop_area; cancel, watch, unwatch, close, reopen, the small forms, `comment_watchers`, `comment_pagination` inside it; a small form's button outside that form.

## How was it checked?
- `tests/comments_widget_test.php` (draw feedback, empty box, canvas twin under Node), `tests/designer_messages_test.php`.
- Sandbox, MariaDB, PHP 8.3 (2026-10-24): member watch and unwatch with their notices; staff list, add, duplicate refused with the text kept, remove; close and reopen. Record threads, a role-3 or submitter manager: unit tests only.
