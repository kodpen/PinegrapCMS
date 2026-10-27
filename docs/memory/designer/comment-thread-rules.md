---
name: comment-thread-rules
description: Comment rules the classic comments block shares with the widget (pg_comments_settings, _allow_new, _viewer, _rows, _thread_order); its HTML stays byte for byte, its quirks kept
module: comments
---

## What moved out of the classic comments block?
- Only its decisions (narrow extraction, owner decision 2026-10-23), into `includes/fn/comments.php`; `get_page_content.php` keeps its HTML and fills the same variables.
- `pg_comments_settings($page_row)` (pure): the comment columns as switches, texts and ids; `enabled` and `auto_publish` `== '1'`, the other switches `== 1`, `rating` not empty. The block takes `enabled` and `allow_new`.
- `pg_comments_allow_new($page_id, $item_type, $item_id, $page_allows)`: with an item id other than 0, the item's `allow_new_comments_for_items` row decides when there is one; else the page. `add_comment.php` asks the same. Pure: `pg_comments_allow_new_decision()`.
- `pg_comments_viewer($staff, $record_form, $search_index)`: `staff`, `form_editor`, `own_ids` (this session's `added_comments`; none for the search index), `search_index`; `pg_comment_parent_ok()` takes it. Pure: `pg_comments_viewer_decision()`.
- `pg_comments_rows($page_id, $thread, $viewer, $options)`: the three statements the block sent (count, featured count, rows), same text (`pg_comments_thread_sql()` . `pg_comments_visible_sql()`). `featured` 'filter' (the block's) reads only featured rows while some exist and `?comments` is unset or `featured`; the count is of all. Options `parent_id` (0 before 11.0.31) and `thread_key` (item_type, item_id) add columns; the block never asks.
- `pg_comments_thread_order($rows, $layout, $max_depth)` (pure): flat keeps the order read at depth 0; threaded is depth-first, depth capped at 1-3, a capped reply keeps its parent; a parent not among the rows (deleted, not visible) makes a top-level entry; loops are cut. Caller: the comments widget ([[comments-widget-replies]]).
- The block skips a page a comments widget drew in its pass (`pg_comments_widget_drawn()`).

## Why is the SQL text kept as it was?
- Rows are sorted by `created_timestamp` alone: comments of one second come in the server's order for that statement; a tie-break or an extra column may change it.

## Which quirks of the block are kept on purpose?
- A page that shows no item (not catalog, catalog detail, calendar event view or a record page) reads every comment on the page, whatever its item.
- The stars and the rating field follow the drawn page's switch (`$comments_rating`), not the region page's.
- The rows stay in `$comments`, the comments switch: a second blank `<system>` region draws no comments.
- `parent_id` is not read: a reply shows as a later comment ([[comment-replies]]).
- The block's loose comparisons (`$item_id != 0` on the raw id): PHP 7 and 8 answer as before. Only a non-scalar id (forged; fatal before) reads as no item.
- A failed query is logged and ends on `pg_db_run()`'s error page.

## How was it checked?
- `tests/comment_thread_rules_test.php`: every helper against a frozen copy of the block's old code (statements and decisions) for signed out, member with a pending comment, staff, form editor, featured, scheduled, failed post and search index; the thread order; the wiring of both files.
- Sandbox, MariaDB, PHP 8.3 (2026-10-24), CAPTCHA off: classic standard, form item view, catalog detail, calendar event view pages and a designed record page (record widget and block, built in SQL), two comments of one second; anonymous, member, admin; default, featured (unset, `featured`, `all`), closed, own pending, failed post; 103 responses. Masked `cmp`, old vs new files: all identical. Masked (two runs of the old files differ there): CSRF tokens, form stamps, "just now", the toolbar's edit age, the picker's clock. Posts alike: closed record refused, an event opened by its row taken on a closed page.

## What keeps its own copy?
- `add_comment.php`'s viewer for the parent check, `allow_or_disallow_new_comments.php`, `add_or_remove_watcher.php`, the block's watcher queries and its item pick.
