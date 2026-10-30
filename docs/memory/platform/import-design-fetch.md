---
name: import-design-fetch
description: How the design importer fetches a page (import_design.php): each hop one api_http_request() with opt-in options, the 5-hop walk and the stream fallback kept, what changed with the move
---

## How does the importer fetch an address?
- `import_design_fetch()` walks redirects itself, at most five hops after the first request; each hop is `import_design_fetch_once()`. A 3xx without a usable Location is the answer.
- With cURL, a hop is one `api_http_request()` (`includes/api/outbound/http.php`) with `import_design_fetch_options()`: `allow_http`, `head_get_on_405`, the importer's browser `user_agent`, `decode` (any compression cURL can undo), timeout 30, `max_body` 0 (whole body), `response_headers`. `import_design_http_answer()` turns the answer into status/content/content_type/location from the `content-type` and `location` headers; status 0 (refused address or cURL error) is false.
- `api_http_request()` checks the address (`api_http_check_url($url, true)`: loopback, private and reserved refused) and pins cURL to it with `CURLOPT_RESOLVE`; it never follows a redirect.
- Without cURL the stream fallback stays in `import_design.php`: checked first, `follow_location => 0`, `ignore_errors`, Location read from the stream's headers.

## Which api_http_request() options did this add?
- All off by default, so webhook, MCP, marketplace and connector calls are unchanged: `allow_http`, `user_agent` (whole string), `decode`, `head_get_on_405`. HEAD now sets `CURLOPT_NOBODY` (no caller used HEAD before; without it cURL waits for a body). `max_body` 0 already kept the whole body.
- The settings come from the pure `api_http_request_settings()`; `api_http_retry_as_get()` says when a HEAD is asked again (`tests/import_design_fetch_test.php`, which also loads the importer's fetch functions out of `import_design.php` by name).

## What changed for the importer with the move (1d)?
- `ALLOW_INSECURE_UPDATE_TLS` no longer reaches it: `api_http_request()` always verifies (`CURL_CA_BUNDLE` still read).
- Connecting must finish within 5 seconds (before: only the 30-second total).
- Location is the header as sent, resolved by `url_to_absolute()`, instead of cURL's own resolved redirect URL.
- A failed HEAD hop answers false instead of status 0; `check_if_url_exists()` answered false for both.
- Still not honoured: `PROXY_ADDRESS` (neither before nor now).

## Can the final Import run twice?
- No: the scan-result form sends once and says it is importing (`ui/button-busy-state.md`); the server still takes the scanned list from the session on the first request.
