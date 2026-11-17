---
name: api-clean-modern-steps
description: Steps AM1 to AM6 of plan api-clean-modern: the two failing routes, a fault as JSON, time formats in the document, the answer check tool, generated descriptions and example bodies
---

## Which steps mend what is broken?
- AM1 (S) `GET /users` and `GET /users/{id}` answer again (#10088). `api_user_optional_columns()` calls `pg_user_permission_ws_replies_column()`, whose file only the write handlers load. A test loads what a read loads and asks for the column list.
- AM2 (S) `GET /reports/cart-recovery` answers again (#10089). The cause is read from the site's error log by the answer's `request_id`; a test for the pure part.
- AM3 (S) A fatal error or an uncaught Throwable inside `integration.php` answers the envelope: 500 `server_error` as JSON with `request_id` and `X-Request-Id`. The answer never carries PHP's message; the log line carries the request id. Today the site's HTML error page comes back with no request id (seen on #10088). `mcp.php` is checked for the same. Done (#10120): `api-fault-envelope.md`.

## Which steps make the document true?
- AM4 (M) A moment says `format: date-time` and a day `format: date`: 309 such fields are plain strings today, and three `User` fields say `type: datetime`, which OpenAPI does not have.
  - The declarations get a type word for each, mapped once in `api_openapi_type()`. Day or moment is read from the presenter (`api_time()` or `Y-m-d`), never from the field's name.
  - `seo.issues` rows declare `source`, `label`, `weight`, `earned` (#10090).
  - `tools/check_api_schema.php` refuses a type word the document cannot say.
  - If its files cross open PRs: AM4a (the words, the check, the core objects, `User`, `seo.issues`), then AM4b (Workspace and ERP objects).
  - AM4a done (#10151): `api-openapi-time-formats.md`; `seo.issues` was declared by #10116. AM4b also takes `includes/api/resources/design.php` and the translation objects.
- AM5 (M) done (#10136). `tools/check_api_answers.php` calls every GET route of a running site (lists first, then one record by a list's first id) and compares each answer with the document: status, JSON, required fields, types, null only where declared, undeclared fields. Read only.
  - Address, key and secret come from the environment as `tools/ws_client.php` reads them: never an argument, never printed.
  - It skips routes marked `moves_money` and binary answers, and keeps under the rate limit.
  - It needs a database, so it is not a CI check. AM1, AM2 and #10090 are what it would have caught.
- AM6 (M) The document explains itself: a `tags` list with one sentence a group (none today) and, generated and never written by hand, a description on every id parameter (800 parameters have none) and one example body a write route (340 bodies have none).
  - Descriptions and example bodies come from one builder, the request builder of `tests/api_route_examples_test.php`, one function for the test and the document.
  - They go into the OpenAPI document only. The MCP tool list must not grow: the PR gives its size before and after.

## What does every step of the plan keep?
- One route row, one presenter (`api/api-endpoint-rules.md`); a new error code is explained in `api_error_catalogue()`.
- A test for each pure function added or changed; `php tools/check_api_schema.php` green.
- PHP 7.1 to 8.5 (`bash tools/static_analysis.sh`).
- A new user-facing text goes through `lang()` with its Turkish by `php tools/tr_add.php`.
