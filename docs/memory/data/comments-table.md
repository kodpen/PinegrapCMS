---
name: comments-table
description: The comments table: thread key, 2026.11.0 columns (reviews 11.0.14, parent_id 11.0.31), indexes idx_parent and idx_thread, copy cost on MyISAM, code that copies or exports rows.
---

## What identifies a comment's thread?
- `page_id` + `item_type` + `item_id`. A page comment has `item_type = ''` and `item_id = 0`; a record's comments carry its type (`submitted_form`, `product`, `product_group`, `calendar_event`) and id. Edited at `edit_comment.php?id={id}` (`content-tables.md`).
- Review columns (11.0.14): `verified_order_id`, `seller_reply`, `seller_reply_at` (`commerce/product-reviews-verified.md`).

## What is `parent_id`?
- `INT UNSIGNED NOT NULL DEFAULT 0`, added by `upgrade_2026_11_0_comment_replies()` (2026.11.0, step 11.0.31): the comment this one answers, `0` for a top-level comment.
- Every comment written before the step reads 0 and stays top-level; no data is migrated. The classic comments block, `add_comment.php`, the API and the moderation screens do not read or write it yet. Replies are designable-comments work (`plans/designable-comments.md`).

## Which indexes were added with it, and why?
- `idx_parent (parent_id)`: the replies to one comment.
- `idx_thread (page_id, item_type, item_id, created_timestamp)`: one thread in the order it was written. Before it, a thread was read through the `page_id` key and every comment on the page was sorted, which a forum topic with thousands of replies pays on each view.
- Approved with the column under fixed rule 5 (2026-10-23).

## What does the step cost on a big site?
- The column goes in with `install_add_columns_instant()` (InnoDB on MySQL 8.0.12+ / MariaDB 10.3.2+: a moment), else `install_add_column()`. An index on InnoDB is built in place.
- The starter dumps create `comments` as MyISAM, and 2026.4.8 moves it to InnoDB unless it is over 250,000 rows or 128 MB (`table-engines-and-row-size.md`). A table still on MyISAM is copied whole by each of the three ALTERs.
- So the step pauses (`install_pause()`) before the next index once it has run an ALTER in this pass and the 30 s pass budget is spent; a pass that ran nothing never pauses, so each pass moves on. `comments` is on the 2026.11.0 row of `install_heavy_tables()` for the preflight warning (`release/migration-long-steps.md`).

## Which code copies or exports whole rows?
- `duplicate_page_f.php` copies a page's comments with a column list: `parent_id` is not copied, so a copied reply becomes top-level (its parent id would name the original page's comment). Remap it when replies are written.
- The privacy export reads `SELECT *` (`includes/fn/privacy_export.php`): the column appears there as a number. Backups dump every column.
- `add_comment.php`, `submit_order.php` (the comment a product adds when it is ordered) and the copy above insert with column lists, so new rows get 0.
- The starter dumps under `data/backups/` stay as they are (`workflow/decisions-made-data.md`); the installer's upgrade adds the column.

## How is the step checked?
- `tests/comment_replies_schema_test.php` (call, label, helpers, pause guard, heavy-table row).
- Sandbox, MariaDB 10.11 (2026-10-23), upgrade from 2026.4.10 run twice (`release/rerun-upgrade-steps.md`): on InnoDB the column went in instantly; on MyISAM INSTANT was refused (1845) and the copy ran; the second run reported the column and both indexes already in place. With the budget forced spent, each pass ran one ALTER and the third finished.
