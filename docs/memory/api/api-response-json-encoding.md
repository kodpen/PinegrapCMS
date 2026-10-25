---
name: api-response-json-encoding
description: API and MCP answers as JSON (pg_json_encode_answer()): broken UTF-8 bytes, keys too, become U+FFFD on every PHP version; INF or NAN becomes a 500 or -32603, never an empty body
---

## What went wrong before?
- `api_send()` (`includes/api/response.php`) used a bare `json_encode()` and did not look at the result. A string that is not valid UTF-8 (a form-encoded field from a client encoding Windows-1254, a value read from a damaged row) or an INF/NAN made it return `false`, and `echo false` printed nothing: the answer went out with its status (200 included) and an **empty body**, no `error.code`, no `request_id`. On a GET every such answer had the same ETag (digest of the empty string), so `If-None-Match` answered 304. Seen as `POST /products` with `X-Dry-Run` and `name=x%FFy`: 200, 0 bytes (issue #867, fixed by PR #1007).

## What does it do now?
- `pg_json_encode_answer()` (`includes/fn/core.php`) is the one encoder: `api_json_encode()` and the MCP server's `pg_mcp_json_encode()` only call it. `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`; a broken byte in a key or a value is written as U+FFFD and the rest stays. It returns `null` only when the answer cannot be JSON at all (INF, NAN, deeper than 512), a broken byte beside it or not.
- On `null`, `api_send()` writes a warning line to `data/temp/php_errors.log` (method, path, `json_last_error_msg()`) and answers 500 `server_error` with the usual body and request id, without an ETag. The idempotency reservation is then given back (an error is never recorded).
- `api_idempotency_store()` writes the record with the same encoder, so a stored answer is never an empty string the replay cannot read.

## How does PHP 7.1 get U+FFFD?
- 7.2+ uses `JSON_INVALID_UTF8_SUBSTITUTE`. 7.1 lacks it: on a `JSON_ERROR_UTF8` failure, `pg_json_utf8_substitute()` replaces the broken bytes of every string, array key and object property (`htmlspecialchars()` with `ENT_SUBSTITUTE` and back, the same sequences), then the value is encoded again with the same flags.
- Never `JSON_PARTIAL_OUTPUT_ON_ERROR` for an answer, as was done before: on 7.1 it wrote a broken key as a bare `null` (`{null:"v"}`, not JSON, sent as a 200), and it hid every other error of the value: INF and NAN came out as 0, a resource as null, any depth written in full, so a body with a broken byte and an INF was a successful answer instead of the 500/-32603.
- Tested in `tests/json_answer_test.php` (the walk runs on every version) and run on PHP 7.1.33 and 8.3.

## What about the Idempotency-Key fingerprint?
- `api_idempotency_hash()` hashes `api_idempotency_input_text($input)`: `json_encode()` as before (keys on file keep matching), and `'php:' . serialize()` when JSON fails, which keeps every byte. Before, such a body hashed as "METHOD PATH " alone, and the same key with another body was replayed instead of 409 `idempotency_key_reused`. Substitution is not used there: it would make two different bodies alike. Tested in `tests/api_idempotency_test.php`.

## What does the MCP server write with it?
- A route run by the MCP endpoint answers through `api_send_hook()`, before `api_json_encode()`. The MCP server writes the body with `pg_mcp_json_encode()` in a tool's text, a resource read and the context channel_summary embeds, and `pg_mcp_send()` writes the payload with it. Before, each used a bare `json_encode()`: a broken byte gave a 200 result with text `""` (an empty resource, an empty embedded context the prompt then called empty), and in `structuredContent` an empty HTTP body.
- A body JSON cannot hold at all is -32603 "Internal error" on tools/call and resources/read, and a prompt embeds nothing; a payload `pg_mcp_send()` cannot write becomes -32603 under the request's id (`pg_mcp_payload_json()`). Tested in `tests/mcp_test.php` and `tests/mcp_resources_test.php`.
