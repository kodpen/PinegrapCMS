---
name: content-review-rules
description: Content review rules (includes/fn/content_review.php): which items count, the four states of a review date in the site's time, the next date after a review, who may be an item's person
module: content_review
---

## Which items count?
- A page that exists, or a record (`forms` row) of a form used as a collection (`custom_form_pages.collection`). Pure `pg_content_review_item_counts($kind, $facts)`; kinds `page` and `record` (`pg_content_review_kind()`, exact).
- No column on `page` or `forms` (`plans/content-review.md`); the rows live in their own table, keyed by kind and id.

## What are the states of a date?
- Pure `pg_content_review_state($review_on, $today)`, both Y-m-d in the site's time (`pg_content_review_today()` uses PHP's zone): overdue before today; due soon from today up to 30 days ahead (`PG_CONTENT_REVIEW_DUE_SOON_DAYS`, the 30th day included); later after that; not set without a date (empty, zero or impossible).

## What does "Mark reviewed" move the date to?
- Pure `pg_content_review_next_date($today, $months)`: today plus 3, 6 or 12 months (`pg_content_review_intervals()`, 0 is "not at all"), the day kept inside the target month (30 November + 3 months is 28 February); null, the date cleared, without an interval or with one not offered.

## Who may be an item's person?
- Owner (2026-11-16, poll 68, `workflow/decisions-made-product-59.md`): any panel user who may edit the item.
- Pure `pg_content_review_person_may_edit($kind, $user, $folder_id, $folders)`: roles 0 to 2 edit every item; a user (3) edits a page in a folder of theirs with edit rights (`get_folders_that_user_has_access_to()`), a record only when they also manage forms, in its form page's folder (as `edit_submitted_form.php`); any other role is refused.

## Tested?
- `tests/content_review_test.php` (states at the day's edges and in two zones, next dates, intervals, items, persons).
