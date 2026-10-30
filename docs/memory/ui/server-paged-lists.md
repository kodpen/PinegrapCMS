---
name: server-paged-lists
description: DataTables lists paged on the server (users, submitted forms, contacts, products): data-pg-list-action, the JSON page action, search, counts, row cells, what select all and bulk buttons act on
module: list_paging, user_list, submitted_form_list, contact_list
---

## Which lists are paged on the server, and why?
- The users list (`view_users.php`, PR #2460). It printed every account with several queries per row.
- The submitted forms list (`view_submitted_forms.php`, PR #2509, issue #2038). It checked `check_edit_access()` per row and printed 100, so search saw those 100 only. The folder rule is in the query; the filters (date range, custom form, advanced filters, `query`) come from the session through `pg_submitted_form_list_filter()`, which Export and Delete All Forms use too.
- The contacts list (`view_contacts.php`, PR #2528). It printed every contact with a picture and a group lookup per row. Filters come from the session, the view from the address, through `pg_contact_list_filter()` (Export, Delete All and Merge too); the duplicates view stays printed whole. Rows carry `DT_RowId`; lazy pictures are bound on each draw.
- Products (#2655), commissions (#2675), Site Log, audit, firewall log: `ui/product-list-paging.md`, `ui/commission-list-paging.md`, `ui/site-log-paging.md`, `ui/audit-log-paging.md`, `ui/waf-log-paging.md`.

## How does such a screen work?
- The screen draws the toolbar and headings only; its `table.chart` carries `data-pg-list-action` (the `api.php` action) and `data-pg-list-params` (JSON, e.g. the filter). `pgListServerOptions()` in `backend.src.js` turns DataTables to `serverSide`: each draw posts `{action, token, draw, start, length, search, ...params}`.
- The action (`includes/panel/lists.php`, `any_role` + its own role check + `validate_token()`) builds the screen's view (`pg_user_list_view()`), counts without and with the search, reads one page with `LIMIT` and answers `pg_list_rows_answer()`. A refusal or failed query answers JSON with a message, shown in the empty row.
- Sort stays the heading links (`pg_list_state()`); the action reads it from the session and adds the id last so pages never overlap.

## What does the search box find?
- Every word (a "quoted phrase" is one word) somewhere in the row's text columns, case-insensitive, as DataTables' smart search did. `pg_list_search_where()` writes `LIKE ?` per column with bound patterns (`pg_list_like_pattern()` escapes `%`, `_`, `\`); a column showing a label for a stored value (the role) matches by label (`IN (?)`). Times and check marks are not searched.
- Turkish I (PR for #3584): `utf8mb4_unicode_ci` folds I with i, not ı, so "YILDIZ" missed "Yıldız". With a `tr` panel or page language (`pg_list_search_turkish_charset()`: the connection's `utf8`/`utf8mb4`) a word with I or ı adds `CONVERT(expr USING cs) COLLATE cs_turkish_ci LIKE ?` beside the plain LIKE, which stays (turkish_ci alone loses ş/s, ç/c and IPHONE→iPhone); `term` columns never. Labels also match lowered the Turkish way (`pg_list_turkish_lower()`). "yildiz" finding "Yıldız" is a product decision, not done.

## What do select all and the bulk buttons act on?
- The rows on screen (exports and "all that match" read the whole filtered list on the server); only the drawn page is in the DOM. "Unlimited" lists every row.
- `chart_checkbox_state_change()` marks `#select_all` mixed when some rows are ticked and sets the `disabled` property on `.enable-on-selected button` (`.btn.disabled` only stops the pointer; PR #3303).

## What must a row keep?
- A row is `array('class', 'cells')` of `pg_list_cell($html, $class[, $attributes])`; `pg_list_row_data()` sends the cell classes in `DT_RowData.pgCells` (attributes in `pgAttrs`) and `createdRow` puts them back. Every cell is escaped where it is built. The edit link's `send_to` is the screen with its filter, not `api.php`.
- Hints and menus in later rows are bound by `pgInitInjected()` on each draw; select all is delegated to `document`.

Related: docs/memory/ui/list-and-table-screens.md
