---
name: content-review-rules
description: Content review (content_review, 11.0.226, #9934): the table, which items count, date states in the site's time, the next date, the person, the daily sweep
module: content_review
---

## What does the table hold?
- `content_review` (`upgrade_2026_11_0_content_review()`, 11.0.226, in `get_tables()`): PRIMARY KEY (`item_kind` VARCHAR(10) ascii_bin `page`/`record`, `item_id` INT UNSIGNED = `page.page_id` / `forms.id`); `user_id` (the person, 0 nobody's), `review_on` DATE NULL, `interval_months` TINYINT (0, 3, 6, 12), `reviewed_at`, `reviewed_by` (0), `announced_on` DATE NULL; KEY `idx_review_on`, `idx_user`. No column on `page` or `forms`.
- Architect (2026-11-16, agent channel message 6410): the columns as listed; no `updated_at` (no feature reads a row's write time; a later step asks); `announced_on` now so CH3 needs no second step.

## Which items count?
- One rule for the screen, the announce and the API, pure `pg_content_review_item_counts($kind, $facts)`: a page outside the Recycle Bin, or a record of a form used as a collection (`custom_form_pages.collection`). `pg_content_review_item_facts()` reads exists, folder, in_bin (`pg_recycle_bin_folder_ids()`), collection.
- A binned page or a record of a form no longer a collection keeps its row but is not listed, announced or returned.

## What are the states of a date?
- Pure `pg_content_review_state($review_on, $today)`: overdue before today; due soon from today up to 30 days ahead (`PG_CONTENT_REVIEW_DUE_SOON_DAYS`, the 30th included); later; not set without a date.
- Today is `pg_content_review_today()` (PHP's zone, the site's), passed into every query as a value: never `CURDATE()` or `NOW()` in SQL (the database's zone is not the site's day). `review_on` is compared only with `IS NULL`, never with ''.
- Not set: no row, or a row whose `review_on` is NULL.

## What do the writes do?
- `pg_content_review_save($kind, $id, $user_id, $review_on, $months)`: refuses an item that does not count, a person who may not edit it and an impossible date (pure `pg_content_review_write_problem()`); a row left with nothing (never reviewed) is deleted.
- `pg_content_review_mark_reviewed()`: when and who; the date moves to today plus the interval (pure `pg_content_review_next_date()`, the day kept inside the target month), or is cleared without one; the row stays.
- `pg_content_review_clear()`: "Clear" deletes the row. Every read and write waits for `pg_content_review_ready()`.

## Who may be the person?
- Owner (2026-11-16, poll 68, `workflow/decisions-made-product-59.md`): any panel user who may edit the item.
- Pure `pg_content_review_person_may_edit()`: roles 0 to 2 every item; a user (3) a page in a folder with edit rights, a record only when they also manage forms (as `edit_submitted_form.php`). `pg_content_review_user_may_edit()` adds an existing, enabled account.

## What does the daily sweep do?
- `job.php` once a day (`data/temp/content_review_sweep.json`), 5 s: bounded single-table statements with NOT EXISTS (`pg_content_review_sweep_sql()`, a multi-table DELETE takes no LIMIT) delete the rows of pages and records that are gone; a `reviewed_by` whose account is gone becomes 0 (`reviewed_at` stays).
- Then a person who is removed, disabled or may no longer edit the item: `user_id` 0 only, `reviewed_by` stays. This walk keeps its place on the primary key in the state file across runs and reaches the end of a large table.
- Accepted edge (architect, 6410): a deleted item's row waits for the sweep (a day at most). Where the server does not keep its counter, a reused id after the highest item is deleted and the server restarts shows the old person and date; "Clear" fixes it. No delete path changes (V6), so no code for it.

## Tested?
- `tests/content_review_test.php`: states at the day's edges and in two zones, next dates, intervals, items, persons, write checks, the sweep's statements, the hooks.
- Run twice on MariaDB 10.11; save, refusals, Mark reviewed and the sweep run against it.
