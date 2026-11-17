---
name: api-list-newest-first
description: sort=newest on the lists automation triggers sample (customers, form submissions, products, subscriptions, ERP invoices and quotes): the walk, the marked cursor, list_order.php
---

## Which lists take sort=newest?
- `GET /customers` and `GET /forms/{id}/submissions` (by id), `GET /products` and `GET /subscriptions` (last change, then id), `GET /erp/invoices` and `GET /erp/quotes` (`updated_at`, then id). Added by PR #10174 (step AU2 of plan automation-platforms) for the samples an automation platform shows (`automation-contract.md`).
- `sort` takes `oldest` (the default) or `newest`. The default walk of every list is unchanged: an integration paging forward still never re-reads a row.
- Not yet: the Workspace task list. `GET /orders` has its own `sort=placed_desc`; `GET /returns` is newest first by itself; a table's rows take `dir=desc`.

## How does the newest-first walk page?
- The same column and id as the default walk, the other way: `ORDER BY col DESC, id DESC`, rows `col < v OR (col = v AND id < i)`. A row added meanwhile has the newest value and lands before the first page, never inside a later one; ties on one moment are broken by the id.
- The cursor of a newest-first page starts with `n.`. A cursor used under the other sort is 400 `invalid_cursor`; an unmarked cursor is read under the default walk, so cursors handed out before stay valid.

## Where is it written?
- Once, in `includes/api/list_order.php`: `api_list_descending()`, `api_list_cursor()` (decode or 400), `api_list_cursor_encode()`, `api_list_keyset_sql()`, `api_list_order_sql()`; `api_list_after()` and `api_list_compare()` state the same rule on values for the test. Its gate is the resource files' (`PG_API_ENTRY`, `PG_API_PANEL`, `PG_INIT_LOADED`): a stricter one would end a panel request that loads them.
- `erp_api_page()` takes the direction as an optional last argument; only the invoice and quote lists pass `api_list_descending($params)`. `erp_api_cursor()` was removed: `api_list_cursor()` reads every ERP cursor.
- A new list joins by declaring the `sort` row (`values` oldest, newest; `default` oldest) and calling these helpers; `tests/api_list_order_test.php` names each list's handler.

## Where is it tested?
- `tests/api_list_order_test.php`: a walk over rows with ties at limits 1 to 50 hands out each row once, newest value and higher id first; the default walk unchanged; the id-only walk; the cursor marks; the SQL text; every contract list has `newest` and its handler reads the sort. No database run: the SQL is checked as text.
