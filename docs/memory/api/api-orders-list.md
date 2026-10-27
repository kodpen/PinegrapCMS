---
name: api-orders-list
description: GET /orders: status comma list, sort placed_desc, modified_since on last_modified_timestamp, cursor marks, and the writers that move the stamp (pg_order_touch_sql(), PR #2258)
---

## What does GET /orders filter and sort on?
- `status`: one status or several, comma separated (`complete,exported`), each one of `api_order_statuses()`; an unknown or empty value refuses the whole call with 422 on `status` (`api_orders_status_filter()`), so a typo never widens the list. Declared `string` (max 100), checked by the handler.
- `sort`: `placed` (default, oldest placed first) or `placed_desc` (`ORDER BY order_date DESC, id DESC`, keyset backward). A new order takes the current time, so it lands before the first newest-first page, never inside a later one.
- `modified_since`: orders whose `last_modified_timestamp` is at or after it, oldest change first, keyset on (stamp, id). Sent with `sort` it is 422 on `sort` (no default on `sort`, so the handler sees it).
- `updated_since` / `date_to` still compare `order_date` ("placed at or after"). `include_count` counts with the same filters, without the cursor (`api_orders_list_where()`).
- Page SQL: `api_orders_list_sql()`; listing chosen by `api_orders_list_order()` (`placed`, `placed_desc`, `modified`).

## How does the cursor know its listing?
- It starts with `p.`, `d.` or `m.` (`api_orders_cursor_mark()`); under another listing it is 400 `invalid_cursor` (`api_orders_cursor_parts()`), as `GET /seo/not-found` does.
- A cursor with no mark (handed out before #2258) is read as a `placed` one, so an integration mid-walk survives the update. `.` is not in the cursor's base64url alphabet, so it never mistakes one for the other.

## What is modified_at?
- `modified_at` (ISO-8601 UTC, null for 0) and `modified_at_unix` on every Order, from `orders.last_modified_timestamp` (always selected by `api_order_select()`).

## Which writers move the stamp?
- Every UPDATE that changes what `GET /orders/{id}` shows sets `pg_order_touch_sql()` (`includes/fn/order_status.php`, `last_modified_timestamp = UNIX_TIMESTAMP()`) in the same statement: `process_order_cancellation()` and its refund outcome, `pg_order_restore_cancelled()` (all three UPDATEs), `api_orders_update()`'s status/notes write, `ws_change_write_order()` (status and fields), `pg_order_quick_update()` (list status cell), `view_order.php` (refunded amount, operator's refund mark), the exports (`view_orders.php` twice, `shipworks.php`).
- Already moving it before: basket insert, placing the order, marketplace import, `update_order.php` (shipping), counter sales.
- An exported order's stamp moves, so the ShipWorks feed (which polls by the same stamp) sends it once more, now as exported.
- No index on the stamp (none asked); `tests/order_touch_test.php` reads each writer's source and fails an orders UPDATE without the touch.

Tests: `tests/api_orders_list_test.php`, `tests/order_touch_test.php`.
