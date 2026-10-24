---
name: api-mcp-client-streams
description: The MCP client reads an event-stream answer only until the event that answers its request (api_http_request() option complete); why curl's error 23 then counts as success; other callers unchanged
---

## What was wrong?
- An MCP server may answer a POST with `Content-Type: text/event-stream` and keep the stream open after the JSON-RPC answer. GitMCP holds it about 10 s, so every tools/list and tools/call took 10 s; a server that never closes held each call until the timeout.

## What stops the read?
- `pg_mcp_client_post()` gives a request (a message with an id) the `complete` option of `api_http_request()`: `pg_mcp_client_sse_stop($id)`. A notification gets none and is read as it comes.
- The read ends at the first finished event (a blank line after it; LF, CRLF or CR line ends) whose data lines, joined with "\n", are a JSON-RPC message, or a batch (JSON array) holding one, with that id and `result` or `error`: `pg_mcp_client_sse_complete($body, $id)`, pure.
- The id is matched by `pg_mcp_client_is_answer()`, which `pg_mcp_client_read()` uses too: strict `===` (the client sends integers, a server echoes them), and a message with `method` (a server request or notification) is never an answer. Notifications, progress, server requests and other ids read on.
- `pg_mcp_client_read()` then reads the body as before. It reads no batch, so a stop on one ends as it would have without the stop, only sooner.
- The sign-in probe (`pg_mcp_client_discover_auth()`) passes the stop test on through `pg_mcp_client_fetcher()`'s fifth argument; metadata reads get none.

## Why is a long stream not read again and again?
- The stop test searches only the bytes new since the last piece (and one before them) for a blank line (`pg_mcp_client_sse_event_end()`), and parses only the events after the last one it read: an event found not to be the answer never becomes it.

## Why is curl's error 23 a success there?
- With `complete` the body is read through `CURLOPT_WRITEFUNCTION` (`api_http_take_chunk()`). When the test says true, the write function returns 0, a short count, and curl ends the transfer with error 23 (CURLE_WRITE_ERROR); `curl_exec()` answers false.
- `api_http_stop_is_success($stopped, $errno)` takes 23 after that stop as the answer: the status and headers already received, the body read so far (cut at `max_body` as before), `ok` by status, `errno` 0. Error 23 with no stop, or any other error, stays a failure.

## Are other callers changed?
- No. Without `complete` no write function is set and the body comes through `CURLOPT_RETURNTRANSFER` as before: webhooks, marketplace connectors, connected accounts, the OAuth server's CIMD reads and the MCP client's metadata reads are untouched.
- `api_http_request()` has no path without curl (it refuses), so there is no second reader to teach.

## How is it tested?
- `tests/mcp_client_test.php`: the pure functions, the chunk helper's counts, a stand-in transport that holds its stream open after the answer (the transport's sixth argument is the stop test). No network; GitMCP itself not measured here.

Related: api-mcp-client.md (refusal texts), api-connected-accounts-usage.md (`api_http_request()`).
