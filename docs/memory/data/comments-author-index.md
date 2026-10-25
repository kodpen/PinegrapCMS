---
name: comments-author-index
description: comments.idx_author (created_user_id, created_timestamp), 2026.11.0 step 11.0.36: why it exists, what reads it, how the step pauses, how it was checked
---

## What is `idx_author`?
- `INDEX idx_author (created_user_id, created_timestamp)` on `comments`, added by `upgrade_2026_11_0_comment_author_index()` (2026.11.0, label 11.0.36). Owner-approved under fixed rule 5 (2026-10-25).

## Why was it added?
- The comments widget's recent mode lists a member's own comments ("mine": `created_user_id = ?` ordered by `created_timestamp`, [[comments-widget-recent]]). Without an index on the author an account with no comments in the chosen pages read the whole table: 309-351 ms on a large copy. The other keys (`page_id`, `idx_thread`, `created_timestamp`) do not lead with the author.

## How does the step run on a big site?
- One `install_add_index()`: it checks first, so a re-run counts it as already in place. Before it the step asks `install_pass_spent()` (and only when the index is missing): on a table still on MyISAM the ALTER copies the table, and 11.0.14 and 11.0.31 may already have copied it in the same pass ([[comments-table]]). `comments` stays on the 2026.11.0 row of `install_heavy_tables()`.

## How was it checked?
- Sandbox, MariaDB 10.11: a fresh install builds it; the 2026.11.0 upgrade run again (`config.version` back to 2026.4.10, `php install/index.php automated_upgrade`) leaves it as it is; with the index dropped the re-run puts it back. `EXPLAIN SELECT id FROM comments WHERE created_user_id = 5 ORDER BY created_timestamp DESC LIMIT 20` uses `idx_author`.
- `tests/comment_author_index_schema_test.php` pins the call and its label, the helper, the pause and the note.
