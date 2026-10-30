---
name: dashboard-activity-card-counts
description: Dashboard Activity card (widget_7.php): each tile's "N today" count and bars read the same condition as its list; a contributor's Forms and Contacts scope, Recycle Bin comments left out
---

## Where do a tile's count and bars come from?
- `pg_activity_daily()` (`includes/dashboard/widgets.php`), eight daily counts; the last is "N today", and the tiles add up to All Activity. The Content kind has no series and counts today from its rows.
- Each kind passes its list's own condition as `$extra`, worked out once in `pg_dashboard_activity_kinds()`, so a tile never counts rows the reader is not shown.

## How are Forms and Contacts scoped for a User (role 3)?
- `pg_dashboard_activity_forms_where()`: complete submissions on form pages in folders they may edit, `forms.page_id IN (…)`.
- `pg_dashboard_activity_contacts_where()`: `contacts.id IN (SELECT contact_id FROM contacts_contact_groups_xref WHERE contact_group_id IN (…))` for the groups from `get_items_user_can_edit('contact_groups', …)`; a subquery, so a contact in two groups is one row and one count.
- Null means nothing is granted: an empty list and `array_fill(0, 8, 0)`, no query.

## How are comments on binned pages left out?
- The series takes `comments LEFT JOIN page ON comments.page_id = page.page_id` as its table and `pg_comment_live_page_sql(pg_recycle_bin_folder_ids())` as `$extra`; the list appends the same fragment after `WHERE 1 = 1` ([[comment-moderation-recycle-bin]]).

## What symptom did this fix?
- #3734: both role-3 series were site-wide, so a tile could read '7 today' over one row or none; comments on binned pages were counted and listed as 'Awaiting approval'.

Test: `tests/dashboard_activity_counts_test.php`.
