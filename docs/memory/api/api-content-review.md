---
name: api-content-review
description: GET /content-review and POST /content-review/{kind}/{id} (CH4): the screen's filters, the keyset over its branches, the fields, partial writes, the dry run, the scope and a user-role caller's rows
---

## What do the routes do?
- (CH4, #10006) `GET /content-review` (`content_review.list`) lists pages and collection records as `ContentReviewItem`: `kind`, `id`, `name`, `collection` (a record's collection name), `user_id`, `review_on` (Y-m-d, site time), `state` (`pg_content_review_state()`: overdue, due_soon, later, not_set), `interval_months`, `reviewed_at`, `reviewed_by` (`api_content_review_present()`, `includes/api/resources/content_review.php`).
- `filter` is the screen's (`pg_content_review_list_filters()`): all (default), overdue, soon, nobody, notset, mine; `kind` page or record. A site before the table answers an empty list.
- `POST /content-review/{kind}/{id}` (`content_review.update`, PATCH also accepted) takes `user_id`, `review_on`, `interval_months` (0, 3, 6, 12) and `reviewed`; only the fields sent change (`api_content_review_update_plan()`), then `pg_content_review_save()` and `_mark_reviewed()` write, the person checked by `pg_content_review_user_may_edit()` (422). An item that does not count (gone, in the Recycle Bin, a record whose form is no collection) is 404.

## How is the list paged?
- It runs the screen's own branches (`pg_content_review_list_branches()`), each narrowed with `key >= ?` and ordered by its key with LIMIT (`api_content_review_page_sql()`): `cr.item_id` for the dated branches, `page.page_id` / `forms.id` for the undated ones (`api_content_review_branch_key()`), so no branch is sorted whole. Rows are merged by the list id (page id × 2, record id × 2 + 1) and the cursor carries the last one (`api_content_review_first_item()`).
- No name search and no other sort: a record's name is a subquery.

## Where is the dry run?
- After every check (item, role 3 rule, date, person), directly above the first write; it answers what would be written.

## Who may call it?
- Scopes `content_review:read` / `content_review:write`, offered to every panel role (`api_owner_scopes()`), since the screen opens to a user (role 3) too.
- A user-role (3) owner: filters mine (default), overdue and soon only (another is 422), each narrowed to the items it is the person of by `pg_content_review_list_condition()`; it may only send `reviewed`, for its own item: another field is 403, another item 404.

## Tested?
- `tests/api_content_review_test.php` (filters per role, keyset per branch, the cursor's ids, a user-role caller's rows, partial writes, the dry run's place). Sandbox (MariaDB): paging all with the cursor, dry run, a bad date 422, a write and reviewed, a role-3 key's 422, 403, 404 and its own mark.
