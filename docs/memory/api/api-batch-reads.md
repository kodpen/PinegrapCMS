---
name: api-batch-reads
description: POST /batch (M8b): up to 20 GETs of the API in one request, each checked as its own call; which routes are no entry, rate limit, 304, headers, page cache, no MCP tool
---

## What does POST /batch do?
- Body `{"requests":[{"id","path","query","if_none_match"}]}`, 1 to 20 entries (`api_batch_limit()`). Answer `{"responses":[{id, status, etag, body}]}` in the order asked; an entry's error is its own body (the usual envelope), never a failure of the batch. Code: `includes/api/resources/batch.php`.
- `path` is the address after the API's base (`/products/12`, `/orders?status=new`); `query` an object of single values or a query string, and it wins over a query in the path; `fields=` trims as on a GET; `id` any text up to 100, echoed (a number as text).
- A matching `if_none_match` answers 304 with a null body and the tag. The tag is the one a GET of its own sends (`api_etag_of_json()` of the trimmed answer).

## How is an entry run?
- In process, as the MCP server runs a handler (`api-mcp-server.md`): the router's setters take the entry's verb, path, input and route, `api_send_capture()` makes `api_send()` throw the answer (`ApiAnswerCaptured`), then everything is put back, `$_GET['fields']` and `If-None-Match` included.
- Each entry gets its route's scope check, `api_validate_input()`, the handler with its row rule and 404. A handler that throws or answers nothing is that entry's 500.
- An entry's own headers (Allow, Retry-After, Location) are dropped (`api_extra_headers()` takes a whole array to put a set back); the `X-RateLimit-*` count stays at its latest.

## Which addresses are no entry?
- `api_batch_route_allowed()`: writes (a path only a non-GET route takes), `'binary' => true` routes, `'public' => true` routes (the token routes), `openapi`, `docs`, `docs.endpoints`, `discovery` and `/batch`: 400 `not_batchable`. No route at all: 404 `unknown_endpoint`. A malformed entry: 422 `validation_failed` with field `requests[n].<key>`.

## Who may call, and what does it cost?
- The route has no scope of its own (`'scope' => ''`, not public): any valid caller; each entry needs its route's scope.
- Every entry counts against the rate limit: the router counted the call for the first entry, `api_batch_count_hit()` counts the others; a refused entry is 429 and the next ones too while the window lasts.
- Only the batch request is written to the request log, not its entries.
- `'mcp' => false`: an MCP client calls each tool itself. No X-Dry-Run (400 `dry_run_unsupported`): it writes nothing.

## Why does it not empty the page cache?
- Every API POST marked the page cache old (`platform/page-cache-invalidation.md`); a device polling /batch would have kept it empty. `pg_page_cache_request_dirties()` takes the API path (`pg_page_cache_request_api_path()`) and `pg_page_cache_api_path_reads()` leaves `/batch` out, also as IIS's ORIG_PATH_INFO gives it.

## Where is it tested?
- `tests/api_batch_test.php`: the entry check, route choice, the page cache, and the run through the real `response.php` in a process of its own (`tests/helpers/api_batch_run.php`).

Related: `api-mcp-server.md`, `api-endpoint-rules.md`, `api-file-endpoints.md`, `docs/memory/plans/mobile-api-shelved-m8.md`
