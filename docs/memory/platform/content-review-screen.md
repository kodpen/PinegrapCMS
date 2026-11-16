---
name: content-review-screen
description: The Content review screen (view_content_review.php, CH2): the list read as branches, filters, sorts, columns, the row menu, the columns, what a user (role 3) sees; actions in content-review-actions.md
module: content_review_list
---

## Where is it?
- `view_content_review.php` (gate `'user'`), the list module `includes/fn/content_review_list.php` (`pg_content_review_list_source()`, list `view_content_review` in `pg_list_table_definitions()`), the action file `content_review_action.php`. Main menu: Pages panel, "Content review", above Search and audits, for every panel user once `pg_content_review_ready()`; Ctrl+K chip under the `folders` gate; help `pages#content-review`.
- Rules of the table, the states and the writes: `content-review-rules.md`.

## How is the list read?
- As list table driver branches (`ui/list-table-branches.md`), id `row_id` = item id × 2 (+1 for a record), branch id the item's own id (`pg_content_review_list_id()` / `_id_parts()`).
- Overdue, Due soon, Nobody's and Mine drive from `content_review` joined to `page` / `forms` by primary key. All adds the undated pages and records read from `page` and `forms` (LEFT JOIN `content_review`, `review_on IS NULL`); Not set is only those. An undated branch sorts by the constant `'9999-12-31'`, so it comes in the item table's key order: `forms` is never sorted whole (architect, agent channel message 6410).
- All and Not set therefore sort only by Review by; name and views sort only in the filters read from `content_review` (`pg_content_review_list_columns()`).
- Today is `pg_content_review_today()`, bound as a value; Due soon is today to the 30th day.

## What do the filters mean?
- All; Overdue (before today); Due soon; Nobody's (a date, no person); Not set (no row, or no date); Mine (the signed-in user is the person). Search by name. Default All, overdue first.
- A user (role 3) gets Mine, Overdue, Due soon, each narrowed to the items they are the person of; All and Not set are not offered (`pg_content_review_list_filters()`).

## What does a row show?
- Name (opens the page's editor, `pg_page_edit_url()`, or `edit_submitted_form.php`), Kind (Page with a Draft pill in the drafts folder, or the collection's name), Person (or Nobody), Review by (date, state pill, interval, last review and by whom).
- Last published (newest `page_revisions` row) or Changed (`page_timestamp`, `last_modified_timestamp`); SEO (bar, or "Not scored" before `seo_checked_at`); Accessibility (count of `a11y_` checks with findings, "Not checked yet" before a full pass, "No problem found" after); Views (30 days) through `pg_seo_traffic_join()`. A record shows a dash in the last three.
- The batch's names, revisions and findings are three reads per batch (`pg_content_review_list_facts()`). Nothing runs an engine.
- Its actions and dialogs: `content-review-actions.md`. Tested: `tests/content_review_list_test.php` (filters per role, conditions with today bound, ids, branches, sorts, cells).
