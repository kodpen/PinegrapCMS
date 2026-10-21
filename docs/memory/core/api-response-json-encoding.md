---
name: api-response-json-encoding
description: How the external API writes its answer as JSON (api_json_encode()): broken UTF-8 bytes become U+FFFD, an answer JSON cannot hold (INF, NAN) becomes a 500 server_error, never an empty body
---

## What went wrong before?
- `api_send()` (`includes/api/response.php`) used a bare `json_encode()` and did not look at the result. A string that is not valid UTF-8 (a form-encoded field from a client encoding Windows-1254, a value read from a damaged row) or an INF/NAN made it return `false`, and `echo false` printed nothing: the answer went out with its status (200 included) and an **empty body**, no `error.code`, no `request_id`. On a GET every such answer had the same ETag (digest of the empty string), so `If-None-Match` answered 304. Seen as `POST /products` with `X-Dry-Run` and `name=x%FFy`: 200, 0 bytes (issue #867).

## What does it do now?
- `api_json_encode($body)` is the one encoder of an answer: `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`, plus `JSON_INVALID_UTF8_SUBSTITUTE` when defined (PHP 7.2+), so a broken byte is written as U+FFFD and the rest of the answer stays. On PHP 7.1 a UTF-8 failure is retried with `JSON_PARTIAL_OUTPUT_ON_ERROR`: the broken value alone becomes `null`. It returns `null` only when the answer cannot be JSON at all.
- On `null`, `api_send()` writes a warning line to `data/temp/php_errors.log` (method, path, `json_last_error_msg()`) and answers 500 `server_error` with the usual body and request id, without an ETag. The idempotency reservation is then given back (an error is never recorded).
- `api_idempotency_store()` writes the record with the same encoder, so a stored answer is never an empty string the replay cannot read.

## What about the Idempotency-Key fingerprint?
- `api_idempotency_hash()` hashes `api_idempotency_input_text($input)`: `json_encode()` as before (keys on file keep matching), and `'php:' . serialize()` when JSON fails, which keeps every byte. Before, such a body hashed as "METHOD PATH " alone, and the same key with another body was replayed instead of 409 `idempotency_key_reused`. Substitution is not used there: it would make two different bodies alike. Tested in `tests/api_idempotency_test.php`.

## What is outside it?
- A route run by the MCP endpoint answers through `api_send_hook()`; the hook (`includes/mcp/tools.php`) encodes the body itself.
- `api_json_encode()` has no unit test: `response.php` loads only behind `PG_API_ENTRY`, and the stand-ins other tests declare for `api_time()`/`api_money()` would collide with it. It was checked on a running instance.
