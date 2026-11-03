---
name: explorer-access-drawers
description: File Manager access drawers (folder and commerce) list only rights holders, add users by search, save changed rows, None deletes the row; end date layout, inherit note
---

## Which users do the access drawers list?
- Customers and members are role 3 like basic users, so listing every role-3 account drew thousands of rows on a store (#5480). Now the folder drawer lists users with a direct or inherited right (`aclfolder_rights > 0` on the folder or a parent); the commerce drawer lists users with any commerce right.
- The others are added from the Add a user box: `explorer_access_user_search` (`pg_explorer_access_user_search_rows()`, role <= 2, username, e-mail, contact first or last name, 20 rows, `pg_list_like_pattern()` with placeholders). It is a page cache "clean" read.

## What does a save write?
- Only rows whose right or end date changed (`accessChangedRows()`, `data-initial-*`); the commerce drawer compares its three switches and sends nothing when none changed.
- `pg_explorer_folder_access_entries()` keeps valid rows (last per user wins); one role query checks them; each row is deleted and, for rights 1 or 2, inserted again. None leaves no row: zero-rights rows are no longer written.

## How is the end date drawn?
- One block per user: name and inherited badge, the rights select, then for view access a labelled "Access ends on" date field with "Leave empty for access with no end date." The table and its blank third column are gone, so the inline datepicker no longer squeezes the selects (#5479).
- The inherit note shows what Default takes (`inherited_type`, `inherited_from`, sent whatever the folder has now) and is shown only while Default is selected.
- Shared Folders writes `expiration_label` (panel date format) as "Access until …" or "Ended on …".

Related: docs/memory/ui/explorer-details-rows.md, tests/explorer_folder_access_drawer_test.php
