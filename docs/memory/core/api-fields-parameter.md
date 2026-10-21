---
name: api-fields-parameter
description: External API ?fields= on reads: which answers are trimmed row by row (paged listings and GET /webhooks, api_route_returns_listing()) and which as one record
---

## What does ?fields= do?
- `api_response_fields()` (`includes/api/response.php`), applied by `api_send()` to every 200 GET: keeps the named top-level fields (and `id`, always) of each record and drops the rest before encoding. An unknown name is not an error. The OpenAPI document and `docs.endpoints` are left whole.

## Which answers count as a listing?
- A listing is trimmed row by row inside `data` and keeps its envelope whole. It is one when the body has `page` (`api_ok_list()`) **or** the route declares it so: `api_route_returns_listing($route)` (`includes/api/openapi.php`) is true for `'returns' => array('list' => X)` and for `array('data' => 'X[]', …)`.
- Anything else is one record and is trimmed at its top level.

## What went wrong before?
- Only `page` marked a listing. `GET /webhooks` answers `array('data' => …, 'events' => …)` through `api_ok()` (no paging, declared `array('data' => 'Webhook[]', 'events' => 'string[]')`), so it was trimmed as a record: `data` and `events` dropped, no `id` at the top, answer `[]`. A client syncing its subscriptions with `?fields=url` read "no subscriptions" (issue #920).
- Now `GET /webhooks?fields=url` answers `{"data":[{"id":…,"url":…}],"events":[…]}`. Tested in `tests/api_response_fields_test.php` (the route rule; `response.php` loads only behind `PG_API_ENTRY`, checked on a running instance).

## What should a new endpoint do?
- Answer a listing with `api_ok_list()` (fields beside `data` and `page` through `$extra`, declared next to `'list'`). If it has to answer `data` without paging, declare `'data' => 'X[]'` in `returns` so `?fields=` reads it as rows.
