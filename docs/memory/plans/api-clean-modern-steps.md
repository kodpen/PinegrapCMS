---
name: api-clean-modern-steps
description: Steps AM1 to AM13 of plan api-clean-modern: failing routes, a fault as JSON, time formats, the answer check tool, HEAD and Location, every wrong field, If-Match, delete twins, the pages
---

## Which steps mend what is broken?
- AM1 (S) `GET /users` and `GET /users/{id}` answer again (#10088). `api_user_optional_columns()` calls `pg_user_permission_ws_replies_column()`, whose file only the write handlers load. A test loads what a read loads and asks for the column list.
- AM2 (S) `GET /reports/cart-recovery` answers again (#10089). The cause is read from the site's error log by the answer's `request_id`; a test for the pure part.
- AM3 (S) A fatal error inside `integration.php` answers the envelope: 500 `server_error` as JSON with `request_id` and `X-Request-Id`. Today the site's HTML error page comes back with no request id (seen on #10088). `mcp.php` is checked for the same.

## Which steps make the document true?
- AM4 (M) `format: date-time` on every moment and `format: date` on every day: 309 such fields are plain strings today, and three `User` fields say `type: datetime`, which OpenAPI does not have. `seo.issues` rows declare `source`, `label`, `weight`, `earned` (#10090). `tools/check_api_schema.php` refuses a type word the document cannot say.
- AM5 (M) `tools/check_api_answers.php`: given a site address and a key it calls every GET route (lists first, then one record by a list's first id) and compares each answer with the document: status, JSON, required fields, types, null only where declared, undeclared fields. Read only. It needs a database, so it is not a CI check. AM1, AM2 and #10090 are what it would have caught.
- AM6 (M) The document explains itself: a `tags` list with one sentence a group (none today), a description on every path parameter (800 parameters have none), one example body a write route, built from its declared fields (340 bodies have none).

## Which steps add manners?
- AM7 (S) HEAD answers like GET without a body (405 today). A 201 carries `Location`. An answer with a translated message carries `Content-Language`.
- AM8 (M) A refused body names every wrong field: `error.errors`, a list of `{field, code, message}`; `error.field` and `error.message` stay the first. A JSON list sent where an object is expected is 400 `invalid_json`, as a bare value already is (today it is read as empty: "name is required").
- AM9 (M, asked first) `If-Match` on an update takes the record's ETag: 412 `precondition_failed` when the record changed since. Without the header nothing changes. Today the header is ignored and the later write wins.
- AM10 (S, asked first) `POST /webhooks/{id}/delete` and `POST /workspace/scheduled-messages/{id}/delete`, the twin every other resource has. The old addresses keep deleting; their cards name the regular way.
- AM11 (S, asked first) `Accept-Language: en` or `tr` picks the language of `error.message` for that call; without it the site's language as today (`api/api-meta-language-timezone.md`).

## Which steps are for the pages and the end?
- AM12 (M) `api_docs.php` and `integration.php/docs`: an opening "Conventions" section (credentials, the error envelope and `X-Request-Id`, paging, Idempotency-Key, dry run, ETag, the rate limit headers, the POST twins, formats, language); real headings for sections, groups and endpoints (the panel page has none).
- AM13 (S) Close-out: what changed into `api/`, how AM5's tool is run into `workflow/`, this plan and its two files deleted.

## What does every step keep?
- One route row, one presenter (`api/api-endpoint-rules.md`); a new error code is explained in `api_error_catalogue()`.
- A test for each pure function added or changed; `php tools/check_api_schema.php` green.
- PHP 7.1 to 8.5 (`bash tools/static_analysis.sh`).
- A new user-facing text goes through `lang()` with its Turkish by `php tools/tr_add.php`.
