---
name: comment-counters-recount
description: Upgrade step 11.0.33 (data only) counts the comments of form records on designed pages again: two walks, a time pause, a data/temp progress file with a done marker
---

## Why is there a recount step?
- Until moderation rebuilt `submitted_form_info` from the comment's own item ([[comments-moderation-counters]]), `edit_comment.php` and the API keyed the rebuild on page type 'form item view'. A page built in the visual designer that shows records is 'standard', so released sites keep counts at 0 or too high and a stale newest comment there.
- `upgrade_2026_11_0_comment_record_counts()` (`includes/migrations/2026.11.0.php`, label 11.0.33, called right after 11.0.31) rebuilds every such pair once. No schema change, no changelog line of its own.

## Which pairs does it rebuild?
- Walk 1: every (page, record) with comments of item type `submitted_form` and item id > 0 on a page whose type is not 'form item view'. Pages from `SELECT DISTINCT comments.page_id`, then each page's records in batches of 500 through idx_thread's (page_id, item_type, item_id) prefix.
- Walk 2: counter rows of such pages that still count comments (`number_of_comments` or `newest_comment_id` > 0) for a record with no comment left there (NOT EXISTS): its last comment was deleted, so walk 1 never meets it. Batches of 500 after the last pair reached.
- Each pair goes through `pg_comment_refresh_record_info($page_id, 'submitted_form', $item_id)`: true counts as rebuilt, false as failed (logged; the pair is fixed by its next comment or moderation). Classic record pages are left alone: their counters were kept right.

## How does it pause on a big site?
- After each pair: `install_pass_seconds() >= install_pause_budget()` saves the place, then `install_pause()`. The check comes after the work, so every pass moves on. Not `install_pass_spent()`: the helper's `@mysqli_query` statements do not raise the runner's count.
- The place (phase `comments`/`stale`, page_id, item_id, rebuilt, failed, done) lives in a static and in `data/temp/upgrade_comment_record_counts.json`, read only when under 600 s old (an older one belongs to a run given up on, or a restored backup).

## Why is the file kept after the step finishes?
- A later step of 2026.11.0 that pauses runs the version again from its first step; the `done` marker skips the recount. The note repeats the counts of the run that did the work.
- Side effect: a deliberate rerun within 10 minutes skips it. On a dev DB delete the file first (with `config.version` set back, [[rerun-upgrade-steps]]).

## What does the operator see?
- One note: how many form records were counted again (and how many could not be), or that none had comments to count. An out-of-date `includes/fn/comments.php` gives a skipped note asking to upload the files again.

## How was it checked?
- `tests/comment_record_recount_test.php`: call and unique label, data only, both walks' filters and NOT EXISTS, the time guard with the save before the pause, the file, the done marker, the closing note.
- Sandbox, MariaDB 10.11 (2026-10-24), upgrade from 2026.4.10 through the screen's step endpoint: a standard page's record with 2 published and 1 held comment went 0/0 to 2/newest, views kept; a counter row 3/999 with no comments to 0/0; a classic record page's wrong row untouched. Rerun within 10 minutes skipped; with the file deleted the same result. Budget forced to 0: one pair per pass over 7 requests, all 6 pairs right, note counted 6.
