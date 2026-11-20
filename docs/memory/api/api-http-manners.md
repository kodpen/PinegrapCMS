---
name: api-http-manners
description: External API manners (#10142): HEAD as GET without a body, Location on a 201, Content-Language on an error, exposed headers, the Basic challenge on credentials_missing
---

## What does HEAD answer?
- The GET of the same address without its body: same status, headers, `ETag` (and 304 on `If-None-Match`), plus `Content-Length` of the body GET would send. Before, HEAD was 405.
- `api_request_method()` routes HEAD as GET (`api_route_method()`), so handlers, scopes and the rate limit see a GET; `api_send()`, `api_send_html()` and `api_send_file()` leave the body out when `api_request_is_head()`.
- A 405's `Allow` names HEAD wherever it names GET (`api_allow_methods()`).
- The MCP endpoint answers its own HEAD (`pg_mcp_run()`): unchanged. The OpenAPI document does not list HEAD operations.

## When does a 201 carry Location?
- When the answer is a record with an `id` and a GET route reads the request path plus `/<id>`: `POST /products` answers `Location: …/integration.php/products/12`. Absolute, built from `api_openapi_base_url()`.
- No id (`{"subscribed": true}`), or no GET route for that address: no header. Decided by `api_location_path()` over `api_get_route_patterns()`; a handler that set `Location` itself keeps its own.

## When is Content-Language sent?
- On an answer whose `error.message` is a sentence: the language of the texts, `api_text_language()` (`api-meta-language-timezone.md`). A record's answer has none: its fields are data.

## What may a cross-origin page read?
- Where an answer carries `Access-Control-Allow-Origin` (today `GET /discovery`) and names no list of its own, `api_send()` adds `Access-Control-Expose-Headers`: `X-Request-Id`, `Content-Language`, `Location`, `ETag`, `Retry-After`, `Idempotent-Replay`, `X-RateLimit-*` (`api_exposed_headers()`).
- The rest of the REST API sends no CORS header on purpose (a key does not belong in a web page). MCP keeps its own list in `pg_mcp_cors()`.

## Which 401s carry WWW-Authenticate?
- `credentials_missing` (nothing sent) and `unauthorized` (wrong key) both carry `Basic realm="Pinegrap API"` through `api_basic_challenge()`, unless `X-Requested-With: XMLHttpRequest` marks a script-made call (the /docs console). Before, `credentials_missing` carried none while OAuth was off.
- While OAuth is on, `api_fail()` puts the Bearer challenge in its place. The MCP endpoint's `credentials_missing` gets no Basic challenge: MCP clients sign in through OAuth.
- A 401 to a request that sent a Bearer token and got no other challenge carries `Bearer realm="Pinegrap API", error="invalid_token"` (`api_bearer_challenge_fallback()`). Before PR #10615 a team device's expired or revoked token got none while OAuth was off.

## Where is it tested?
- `tests/api_manners_test.php`. Added by PR #10142.
