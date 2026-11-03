---
name: server-paged-lists
description: DataTables lists paged on the server (products, commissions, logs): data-pg-list-action, the JSON page action, search, counts, row cells, what select all and bulk buttons act on
module: list_paging
---

## Which lists are paged on the server, and why?
- Pages (#6114), users (#2460, then #6325), contacts (#2528, then #6347) and submitted forms (#2509, then #6366) moved to the list table driver (`ui/page-list-paging.md`, `ui/list-table-driver.md`). Products (#2655), commissions (#2675), Site Log, audit, firewall log: `ui/product-list-paging.md`, `ui/commission-list-paging.md`, `ui/site-log-paging.md`, `ui/audit-log-paging.md`, `ui/waf-log-paging.md`.

## How does such a screen work?
- The screen draws the toolbar and headings only; its `table.chart` carries `data-pg-list-action` (the `api.php` action) and `data-pg-list-params` (JSON, e.g. the filter). `pgListServerOptions()` in `backend.src.js` turns DataTables to `serverSide`: each draw posts `{action, token, draw, start, length, search, ...params}`.
- The action (`includes/panel/lists.php`, `any_role` + its own role check + `validate_token()`) builds the screen's view, counts without and with the search, reads one page with `LIMIT` and answers `pg_list_rows_answer()`. A refusal or failed query answers JSON with a message, shown in the empty row.
- Sort stays the heading links (`pg_list_state()`); the action reads it from the session and adds the id last so pages never overlap.

## What does the search box find?
- Every word (a "quoted phrase" is one word) somewhere in the row's text columns, case-insensitive, as DataTables' smart search did. `pg_list_search_where()` writes `LIKE ?` per column with bound patterns (`pg_list_like_pattern()` escapes `%`, `_`, `\`); a column showing a label for a stored value (the role) matches by label (`IN (?)`). Times and check marks are not searched.
- Turkish (`tr` panel or page language, `pg_list_search_turkish_charset()`: the connection's `utf8`/`utf8mb4`; `term` columns never): a word with I or ı adds `CONVERT(expr USING cs) COLLATE cs_turkish_ci LIKE ?` (PR #3677). A word with any i letter adds `REPLACE(REPLACE(CONVERT(expr USING cs), 'İ', 'i'), 'ı', 'i') LIKE ?`, the word folded by `pg_list_search_fold()` (İ, I, ı → i, lowered; `ws_fold()`'s rule), so "yildiz" finds "Yıldız" (PR #3763, decision #3742). The plain LIKE stays (ş/s, IPHONE→iPhone). Labels match by `mb_stripos()` or folded.

## What do select all and the bulk buttons act on?
- The rows on screen (exports and "all that match" read the whole filtered list); only the drawn page is in the DOM. "Unlimited" lists every row.
- `chart_checkbox_state_change()` marks `#select_all` mixed when some rows are ticked and sets the `disabled` property on `.enable-on-selected button` (`.btn.disabled` only stops the pointer; PR #3303).

## What must a row keep?
- A row is `array('class', 'cells')` of `pg_list_cell($html, $class[, $attributes])`; `pg_list_row_data()` sends the cell classes in `DT_RowData.pgCells` (attributes in `pgAttrs`) and `createdRow` puts them back. Every cell is escaped where it is built. The edit link's `send_to` is the screen with its filter, not `api.php`.
- Hints, menus and lazy pictures in later rows are bound by `pgInitInjected()` on each draw; select all is delegated to `document`.

Related: docs/memory/ui/list-and-table-screens.md
