---
name: api-datetime-end-bound
description: External API datetime end bounds (to, *_to): a date-only YYYY-MM-DD counts that day whole, to 23:59:59 in the site's time zone; a value with a time stays that moment (PR #4091)
---

## What does `to=2026-10-20` mean?
- Every `datetime` parameter named `to` or ending in `_to` is an end bound. A bare `YYYY-MM-DD` there counts to 23:59:59 of that day in the site's time zone (`date_default_timezone_get()`, set by init.php). An ISO-8601 value with a time, or a unix stamp, stays that exact moment.
- Start bounds (`from`, `*_since`) read a bare day as its 00:00; exclusive bounds (`*_before`) are not end bounds and stay that moment.

## Where is it done?
- Once, in the parameter reader: `api_validate_value()` (`includes/api/router.php`), case `datetime`, picks `api_time_parse_end_bound()` when `api_param_is_end_bound($name)` (both in `includes/api/time.php`), else `api_time_parse()`. MCP tool calls pass the same reader.
- A new end-bound filter is named `to` / `*_to` and compares `<=`; it needs no code of its own.

## Why?
- Read by `strtotime()`, a bare day was its 00:00, so `order_date <= to` left the last day out: GET /reports/sales `from=to=today` was always empty and GET /orders `date_to` missed that day's orders (issue #4022). GET /reports/traffic already counted its last day whole.

## Which routes does it touch?
- GET /reports/sales `to`, GET /orders `date_to`, GET /site-log `to`, GET /audit-log `to`: their descriptions in `api_schema()` say so.
- Visitor/traffic reports and the ERP `to` / `*_to` filters read only the day (`date('Y-m-d', …)`, `erp_api_day()`), so their answers did not change.
- Workspace `to` parameters are `string` YYYY-MM-DD, read by their own handlers.

## Tests
- `tests/api_end_bound_test.php`: names, Istanbul day end, a New York clock-change day, times and stamps kept, the router pin.
