---
name: api-fault-envelope
description: An uncaught Throwable or a fatal error inside integration.php or mcp.php answers 500 server_error as JSON with request_id and X-Request-Id, never PHP's message; the error log line carries the same id
---

## What did a fault answer before?
- An uncaught Throwable inside a handler (bug #10088: a call to an undefined function in `GET /users`) reached the site's exception handler `pg_error_uncaught()` (`includes/fn/errors.php`), which echoed "An unexpected error occurred." as `text/html`: no `error.code`, no `request_id`, no `X-Request-Id`. A fatal error (memory, time limit) gave PHP's own output or an empty 500. The error log line had its own 12-character id, unrelated to the API's.

## What does it answer now?
- `integration.php` and `mcp.php` run their endpoint through `api_fault_guard()` (`includes/api/fault.php`), which catches every Throwable and registers `api_fault_shutdown()` for fatal errors.
- REST: 500 with `{"error":{"code":"server_error","message":"A system error occurred.","request_id":…}}`, `X-Request-Id`, `Cache-Control: no-store`. A Throwable goes through `api_fail()`, so the request log row and the Idempotency-Key record see a 500 like any other failure.
- MCP: a fault inside a tool, resource read or prompt comes back through the route's answer hook as that envelope in an `isError` result; anywhere else, a JSON-RPC `-32603 Internal error` with `id: null` and `data.request_id`, HTTP 500.
- An answer that already works is unchanged: `api_send()` and `pg_mcp_send()` exit, so the guard never sees them.

## Why is PHP's message never in the answer?
- It can name files, queries and values. The answer always carries the generic sentence; the message, file, line and short trace are in `data/temp/php_errors.log` (Site Log).
- The guard turns `display_errors` off for the request unless `DEBUG` is on: with display on, PHP prints its message and flushes every output buffer before any shutdown function runs, so the envelope could not be sent.

## How does the operator find the log line?
- `pg_error_request_id()` returns `api_request_id()` when `PG_API_ENTRY` is defined, so every error line of an API or MCP request carries the id the client got in `X-Request-Id` and the API request log keeps. A line written before `includes/api/bootstrap.php` loaded still has a short id of its own.
- `pg_error_record_throwable()` writes the Throwable's line for both `pg_error_uncaught()` and the API guard.

## What is left as before?
- A fault in `init.php` before the API bootstrap loads (database guard, config) answers as the site does.
- When headers are already sent the client holds part of an answer: nothing more is written, the request is still logged.
- After a fatal error the shutdown function writes the answer directly and does not exit, so later shutdown functions still run.

## Where is it tested?
- `tests/api_fault_test.php`: which errors are fatal, answering only before headers, the envelope, the generic sentence, the MCP body. Introduced by step AM3 of plan api-clean-modern.
