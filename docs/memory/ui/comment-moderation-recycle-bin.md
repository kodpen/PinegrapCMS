---
name: comment-moderation-recycle-bin
description: Comments on pages in the Recycle Bin: left out of the moderation queue, its count, the greeting and the Workspace counter; marked without a link on the Comments list and edit screens
module: comments
---

## Which figures leave these comments out?
- The Comment Moderation card (`widget_25.php`, list and count, every role), the dashboard greeting's comment count (`includes/dashboard/brief.php`) and the Workspace `comments_pending` counter (`includes/workspace/scheduled.php`).
- Each query LEFT JOINs `page` and appends `pg_comment_live_page_sql(pg_recycle_bin_folder_ids())` (`includes/fn/comments.php`).

## Why leave them out?
- A binned page is not on the site; purging it deletes its comments, restoring brings them back. Approving them shows nothing, so they are not work. The greeting's sentence keys stay as they are ([[greeting-counts]]).

## How is a binned page recognised?
- By folder membership: `page.page_folder` in `pg_recycle_bin_folder_ids()` (the bin folder and its descendants), never by the `pgbin_` name. A page carried in with a binned folder keeps its own name.
- The fragment has an `page.page_folder IS NULL` arm: the LEFT JOIN gives NULL for a comment whose page is gone, and `NOT IN` alone would drop it.

## What do the Comments list and edit screens show?
- `view_comments.php` keeps them, adds an "In the Recycle Bin" badge after the Verified purchase badge and gives no Go To Comment link.
- `edit_comment.php` shows no Go To Comment button, prints the page name through `pg_comment_page_label()` (takes off `pgbin_<own page_id>_`) with "(In the Recycle Bin)".

## What symptom did this fix?
- #3612 (PR #3695): comments on a binned page showed as dashboard work under 'pgbin_1380_rehber-2', the greeting counted them as approve-and-it-shows, and Go To Comment opened /pgbin_….

## What was left?
- The activity card (`widget_7.php`) still lists comments on binned pages.

Test: `tests/comment_recycle_bin_test.php`.
