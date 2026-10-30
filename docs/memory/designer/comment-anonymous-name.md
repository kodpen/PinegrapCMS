---
name: comment-anonymous-name
description: The stored literal 'Anonymous' of a nameless comment and every reader that shows it in the site or panel language through pg_comment_display_name()
module: comments
---

## Where is 'Anonymous' stored?
- `add_comment.php` stores the literal `Anonymous` when no name is given, and the name select's `anonymous` choice (`pg_comment_name_choices()`, `includes/fn/comments.php`) stores the same literal, untranslated. No data step changes it.

## Which readers translate it?
- Every one goes through `pg_comment_display_name($name)` (`comments.php`): `''` and `Anonymous` read `lang('Anonymous')`, any other name stays raw (escaped once, later).
- Site: the comments widget's row, parent and reply names (`widgets_comments.php`: `__comment_name`, `__comment_parent_name` in the link and e-mail values, the "Replying to" name), the recent comments list and widget e-mails (they reuse `pg_comments_widget_row_values()`), the classic comments block (`get_page_content.php`), the submitter and watcher e-mails (`mail.php`).
- The record field `newest_comment_name` is already prepared for output, so its readers only widen the blank check to the exact value `Anonymous` when a newest comment exists (`widgets.php`, `content.php`, `get_form_item_view.php`, `get_form_list_view.php`).
- Panel: `view_comments.php`, the comment dashboard widgets (`widget_7.php`, `widget_25.php`).

## What keeps the stored value?
- The staff e-mail's "Display Name: <stored> (<username>)", the `edit_comment.php` name field, the search index and the external API: they show or edit what is stored.

## Why?
- #3452: under a Turkish site a nameless comment read "Anonymous (admin)" on blog and conversation pages while the panel read "Anonim"; the site checked only for an empty name. Fixed in #3574.
