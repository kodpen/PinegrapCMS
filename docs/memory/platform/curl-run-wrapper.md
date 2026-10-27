---
name: curl-run-wrapper
description: pg_curl_run() for fixed vendor hosts: what it sets, its answer, the opt-in address rule and allow_http, where its callers are listed.
---

## What does pg_curl_run() do?
- `pg_curl_run($method, $url, $options)` (`includes/fn/update.php`, beside `pg_curl_tls()`) makes one request to a fixed vendor host: `pinegrap_user_agent()`, timeouts (default 10 s, connect at most 10 s), https only, no redirect unless `follow` asks (then https only), `PROXY_ADDRESS` when set, and `pg_curl_tls()` applied last, so no option can undo `CURL_CA_BUNDLE` or `ALLOW_INSECURE_UPDATE_TLS`. The handle is freed with `unset()` (PHP 8.5 deprecates `curl_close()`).
- Options: `body`, `headers`, `timeout`, `connect_timeout`, `follow`, `max_size`, `encoding`, `forbid_reuse`, `user_agent`, `userpwd` (basic auth), `http_version`, `response_headers`, `check_address`, `allow_http`. They are built by the pure `pg_curl_run_options()` by constant name (`tests/curl_run_test.php`). A POST without `body` goes out bare (`CURLOPT_CUSTOMREQUEST`, no Content-Length): `CURLOPT_POST` alone sent an empty chunked body with Expect: 100-continue, a shape the İşbaşı hand-over to GİB was never tested with (#1921).

## What does it answer?
- Like `api_http_request()`: `ok` (2xx), `status`, `body`, `errno`, `error`, plus `connect` (a proxy's answer to the tunnel) and `request_size` (request header bytes written), which tell SMS whether a failed send can have reached the provider. `errno` is not 0 when cURL failed; the caller appends `pg_curl_tls_hint($errno)` to its own message. Without cURL it answers `ok` false with an error and errno 0.
- With `response_headers` (`CURLOPT_HEADER`) it also answers `headers`, the raw header block, and `body` is only the body: the raw answer is cut at `CURLINFO_HEADER_SIZE` by the pure `pg_curl_run_split()`. The option is not called `headers`, which already names the request's header lines (#2100).

## Why is the address rule opt-in?
- `check_address` runs `api_http_check_url()` and pins cURL to the checked address with `CURLOPT_RESOLVE`. A site behind `PROXY_ADDRESS` may not resolve the host itself, and a fixed vendor host gains nothing from the check, so the calls moved so far leave it off. An address an operator types belongs in `api_http_request()`, unless it may be on the same host or LAN (below).

## What is allow_http for?
- `migration.php`'s source site: the operator types its API address, and a source on the same host or LAN on plain http worked before. `allow_http` admits http for that request only (`CURLOPT_PROTOCOLS` http|https; redirects stay https only) and goes with `check_address` off. `api_http_request()` would refuse those private addresses, so the call is not there (#2022).

## Which calls use it?
- The list, what changed for each and which wait: `curl-run-callers.md`.
