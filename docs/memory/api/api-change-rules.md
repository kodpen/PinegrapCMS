---
name: api-change-rules
description: How the external API may change: by adding only, no version in the address, English endpoint texts, the checks every API change keeps, and what the 2026-11-17 test measured that is still open
---

## How may the API change?
- By adding: a header, a field, a route, or a correction to the OpenAPI document. Nothing a running client relies on changes; a change to what an answer means is asked first (Working mode 10, `workflow/architect-asking.md`).
- No version in the address (`/v1/…`): the API grows by adding.
- Kept as they are: PATCH and DELETE behind their POST twins (`external-api-iis-environment.md`), the unchecked query string and `fields=` ignoring an unknown name (`api-endpoint-rules.md`), `POST /webhooks/{id}` and `POST /workspace/scheduled-messages/{id}` still deleting beside their `…/delete` twins.
- One resource, one shape: every delete has the same `POST …/delete` twin.

## In which language are the endpoint texts?
- Architect (2026-11-17): English. Route summaries, descriptions, field names and error-code texts come from `api_schema()` and `api_error_catalogue()` untranslated: the MCP tool a model reads prints the same text, and user documentation is English only (`api-openapi-declarations.md`). The documentation pages say so in one translated line (`api-docs-conventions.md`).
- Only the frame of the pages and `error.message` follow the site's language (`api-meta-language-timezone.md`).

## What does every API change keep?
- One route row, one presenter (`api-endpoint-rules.md`); a new non-409 error code explained in `api_error_catalogue()`.
- `php tools/check_api_schema.php` green; a test for each pure function added or changed.
- PHP 7.1 to 8.5 (`bash tools/static_analysis.sh`).
- A new user-facing text through `lang()`, its Turkish by `php tools/tr_add.php`.
- A rule the Conventions section states changes its sentence in the same PR (`api-docs-conventions.md`).
- After a change to answers, `tools/check_api_answers.php` on a running site finds what the document no longer says (`api-answer-check.md`, `workflow/api-answer-check-run.md`).

## What did the 2026-11-17 test measure that is still open?
- Field descriptions: 2,989 of 3,020 fields had none. No sweep is planned; only id parameters and example bodies are generated (`api-openapi-explains.md`). Write a description when a field is not plain from its name.
- 64 lists answer whole (lookups, a record's own rows) and stay so; 82 page by cursor.
- `GET /system/status` took 6 to 8 s on its first call and 1 s after on dev; not looked into.
- What the test found working and is left alone: refusals (401, 422 with `field`, 400 `invalid_json` / `invalid_cursor`, 404, 405 with `Allow`, 409 `name_taken`), dry run, Idempotency-Key, ETag with 304, `X-RateLimit-*`, `X-Request-Id`, `Cache-Control: no-store`, `nosniff`; kebab-case paths, snake_case fields, parameters and operation ids, no operation id twice.
