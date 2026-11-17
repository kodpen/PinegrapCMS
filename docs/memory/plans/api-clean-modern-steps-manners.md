---
name: api-clean-modern-steps-manners
description: Steps AM7 to AM13 of plan api-clean-modern: HEAD, Location and the 401 challenge, every wrong field, If-Match, delete twins, Accept-Language held, the documentation pages, close-out
---

## Which steps add manners?
- AM7 (S) HEAD answers like GET without a body (405 today). A 201 carries `Location` where the record has a GET route. An answer with a translated message carries `Content-Language`. `Access-Control-Expose-Headers` names the new headers.
  - `credentials_missing` gets the `WWW-Authenticate` challenge under the rule `api_fail_unauthorized()` already follows; today it lacks it while OAuth is off.
  - Done (#10142): `api/api-http-manners.md`.
- AM8 (M) A refused body names every wrong field: `error.errors`, a list of `{field, code, message}`, at most 20 rows; `error.field` and `error.message` stay the first.
  - Only the router's own checks (declared parameters, unknown keys) fill it; a handler's refusal stays one.
  - A JSON list with items sent where an object is expected is 400 `invalid_json`, as a bare value already is (today it is read as empty: "name is required"). An empty list `[]` stays an empty body: PHP's `json_encode()` writes an empty array so.
  - Done (#10156): `api/api-field-errors.md`; the `Error` schema's `errors` in `openapi.php` follows once its open PRs land.
- AM9 (M) `If-Match` on an update: 412 `precondition_failed` when the record changed since. Without the header nothing changes. Today the header is ignored and the later write wins.
  - Honoured where the same path has a GET route; the document marks which updates do.
  - The tag is the one of the full GET answer for the same caller (no `fields=`), compared without `W/`; `*` matches an existing record.
  - `precondition_failed` goes into `api_error_catalogue()`. It is checked right before the write with no lock: Conventions says so.
- AM10 (S) `POST /webhooks/{id}/delete` and `POST /workspace/scheduled-messages/{id}/delete`, the twin every other resource has, ids ending in `_post`. The old addresses keep deleting; their cards name the regular way.
- AM11 (S, held) `Accept-Language: en` or `tr` picks the language of `error.message` for that call; without it the site's language as today (`api/api-meta-language-timezone.md`).
  - It opens with a census: the texts a call stores or sends (notification and inbox texts, mail) and what `?pg_lang=` does today. Only if the choice can be kept to the answer does the architect ask the owner; nothing is built before.
  - If it is still open when AM12 lands, AM13 closes without it and the question moves to `api/`.

## Which steps are for the pages and the end?
- AM12 (M) `api_docs.php` and `integration.php/docs`: an opening "Conventions" section (credentials, the error envelope and `X-Request-Id`, paging, Idempotency-Key, dry run, ETag and `If-Match`, the rate limit headers, the POST twins, formats, language); real headings for sections, groups and endpoints (the panel page has none); one translated line saying the reference is in English. Done (#10144): `api-docs-conventions.md`.
- AM13 (S) Close-out: what changed into `api/`, how AM5's tool is run into `workflow/`, this plan and its three other files deleted.
