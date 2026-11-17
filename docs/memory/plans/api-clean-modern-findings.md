---
name: api-clean-modern-findings
description: What the test of 2026-11-17 measured on dev's external API: the GET sweep, write rounds, the document's gaps in figures, missing HTTP manners, the documentation pages; ground of plan api-clean-modern
---

## What was run?
- On dev, 2026.11.0, with a full-rights application key. The document (`/openapi.json`, OpenAPI 3.0.3): 530 paths, 798 operations (287 GET, 383 POST, 67 PATCH, 61 DELETE).
- Every GET without a required query: 244 calls. A create, read, change, delete round on contact groups, folders, offers, products and gift cards. A Workspace round in a channel of its own. No ERP write.

## What failed?
- `GET /users` and `GET /users/{id}`: 500, an HTML page, no request id (#10088).
- `GET /reports/cart-recovery`: 500 `server_error` (#10089); its table is empty and its three queries run by hand.
- `seo.issues` rows of pages, products and product groups carry four undeclared fields (#10090).
- The rest: 229 of 244 answered 200 with no required field missing and no wrong type. Six 403 were scopes the key lacks; two 404 fitted.

## What works and is left alone?
- Refusals: 401 `credentials_missing` and `unauthorized`, 422 with `field`, 400 `invalid_json` and `invalid_cursor`, 404 `not_found` and `unknown_endpoint`, 405 with `Allow`, 409 `name_taken`.
- Dry run, Idempotency-Key (a repeat answers the first id), ETag with 304, `X-RateLimit-*` (120 a minute), `X-Request-Id`, `Cache-Control: no-store`, `nosniff`.
- Names: every path segment kebab-case; every field, query parameter and operation id snake_case; no operation id twice; every operation has a summary, a description and a 4xx answer.
- 82 lists page by cursor; 64 answer whole.

## What is the document short of?
- No `tags` list. 2,989 of 3,020 fields and 800 parameters have no description; 340 request bodies have no example.
- 309 fields named like a moment (`*_at`, `*_date`, `*_time`) are strings without a format; `User.last_online_at`, `created_at` and `updated_at` say `type: datetime`.

## Which manners are missing?
- HEAD is 405. A 201 has no `Location`. A 401 has no `WWW-Authenticate`.
- No `Content-Language`; a message comes in the site's language whatever `Accept-Language` says.
- A refusal names one field, the first. A JSON list as body is read as an empty object.
- `If-Match` with a wrong ETag still writes (200).
- `GET /system/status` took 6 to 8 s on its first call, 1 s after: not a step, noted.

## What about the documentation pages?
- Both list all 798 operations: 726 cards, the 72 others folded into their twin's card. The 728 side links all land; "Try it" works; the public console opens without a key and fits a phone.
- The panel page has no heading element.
- The opening tells credentials, money, dates, the cursor and the dry run; Idempotency-Key, the rate limit, ETag, the twins and `X-Request-Id` are told on single cards only.
- The frame is in the panel's language, the endpoint and error-code texts in English.
