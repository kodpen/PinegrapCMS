---
name: server-paged-lists
description: DataTables lists paged on the server (users, submitted forms, contacts, products): data-pg-list-action, the JSON page action, search, counts, row cells, what select all and bulk buttons act on
module: list_paging, user_list, submitted_form_list, contact_list
---

## Which lists are paged on the server, and why?
- The users list (`view_users.php`, PR #2460). It printed every account and ran several queries per row (folder rights, sign-in lock, calendars, groups), so a site with thousands of users waited for all of them before DataTables showed 100.
- The submitted forms list (`view_submitted_forms.php`, PR #2509, issue #2038). It read every matching form, kept those in a folder the user may edit (`check_edit_access()` per row) and printed 100 of them with its own page links, so the search box searched those 100 only. The folder rule is now part of the query, the filters (date range, custom form, advanced filters, `query`) come from the session through `pg_submitted_form_list_filter()`, which Export and Delete All Forms use too. Turning the advanced filters on now checks the picked custom form at once (it used to end in a failed query until the next visit).
- The contacts list (`view_contacts.php`, PR #2528). It printed every contact with a picture lookup and a group lookup per row. The filters come from the session and the view from the address through `pg_contact_list_filter()` (Export, Delete All and Merge too); the action checks a user's groups again. The duplicates view orders the copies of an address in PHP and stays printed whole (no `data-pg-list-action`). Rows carry `id` (`DT_RowId`); lazy pictures in paged rows are bound on each draw.
- Products (#2655), commissions (#2675), Site Log, audit trail, firewall log: `ui/product-list-paging.md`, `ui/commission-list-paging.md`, `ui/site-log-paging.md`, `ui/audit-log-paging.md`, `ui/waf-log-paging.md`.

## How does such a screen work?
- The screen draws the toolbar and the headings only; its `table.chart` carries `data-pg-list-action` (the `api.php` action) and `data-pg-list-params` (JSON, e.g. the filter). `pgListServerOptions()` in `backend.src.js` turns DataTables to `serverSide`: each draw posts `{action, token, draw, start, length, search, ...params}`.
- The action (`includes/panel/lists.php`, `any_role` + its own role check + `validate_token()`) builds the same view as the screen (`pg_user_list_view()`), counts without and with the search, reads one page with `LIMIT` and answers `pg_list_rows_answer()`. A refusal or failed query comes back as JSON with a message, shown in the table's empty row.
- Sort stays the heading links (`pg_list_state()`); the action reads the kept sort from the session, and adds the id as the last level so pages never overlap.

## What does the search box find?
- What DataTables' own smart search found: every word (a "quoted phrase" is one word) somewhere in the row's text columns, case-insensitive. `pg_list_search_where()` writes it as `LIKE ?` per column with bound patterns (`pg_list_like_pattern()` escapes `%`, `_`, `\`); a column that shows a label for a stored value (the role) is matched through the labels (`IN (?)`). Relative times and check marks were never worth searching and are not searched.

## What do select all and the bulk buttons act on?
- The rows on screen (exports and "all that match" read the whole filtered list on the server): DataTables kept only the drawn page in the DOM, so "Delete Selected" posted that page's check boxes. "Unlimited" in the length menu still lists every row.

## What must a row keep?
- A row is `array('class', 'cells')` of `pg_list_cell($html, $class[, $attributes])`; `pg_list_row_data()` sends the cell classes in `DT_RowData.pgCells` (attributes in `pgAttrs`) and `createdRow` puts them back. Every cell is escaped where it is built. The edit link's `send_to` is the screen with its filter (the action's own address is `api.php`).
- Hints and menus in rows that arrive later are bound by `pgInitInjected()` on each draw; select all is delegated to `document`, so it finds them.

Related: docs/memory/ui/list-and-table-screens.md
