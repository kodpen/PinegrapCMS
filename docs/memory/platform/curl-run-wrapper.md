---
name: curl-run-wrapper
description: pg_curl_run() for fixed vendor hosts: what it sets, its answer, the opt-in address rule, which calls use it and which wait.
---

## What does pg_curl_run() do?
- `pg_curl_run($method, $url, $options)` (`includes/fn/update.php`, beside `pg_curl_tls()`) makes one request to a fixed vendor host: `pinegrap_user_agent()`, timeouts (default 10 s, connect at most 10 s), https only, no redirect unless `follow` asks (then https only), `PROXY_ADDRESS` when set, and `pg_curl_tls()` applied last, so no option can undo `CURL_CA_BUNDLE` or `ALLOW_INSECURE_UPDATE_TLS`. The handle is freed with `unset()` (PHP 8.5 deprecates `curl_close()`).
- Options: `body`, `headers`, `timeout`, `connect_timeout`, `follow`, `max_size`, `encoding`, `forbid_reuse`, `user_agent`, `userpwd` (basic auth), `check_address`. They are built by the pure `pg_curl_run_options()` by constant name (`tests/curl_run_test.php`).

## What does it answer?
- Like `api_http_request()`: `ok` (2xx), `status`, `body`, `errno`, `error`, plus `connect` (a proxy's answer to the tunnel) and `request_size` (request header bytes written), which tell SMS whether a failed send can have reached the provider. `errno` is not 0 when cURL failed; the caller appends `pg_curl_tls_hint($errno)` to its own message. Without cURL it answers `ok` false with an error and errno 0.

## Why is the address rule opt-in?
- `check_address` runs `api_http_check_url()` and pins cURL to the checked address with `CURLOPT_RESOLVE`. A site behind `PROXY_ADDRESS` may not resolve the host itself, and a fixed vendor host gains nothing from the check, so the calls moved so far leave it off. An address an operator types belongs in `api_http_request()`.

## Which calls use it?
- Both update-server version checks (`software_update_check.php`, `pg_update_server_version()`), the CA bundle download (`pg_ca_bundle_update()`), Cloudflare (`cf_request()`, `cf_graphql()` in `cloudflare.php`), the integrity reference (`pg_integrity_http_get()`) (PR #1884).
- What changed for them: the update checks set `VERIFYHOST 2` again after `pg_curl_tls()`, so with `ALLOW_INSECURE_UPDATE_TLS` on they still checked the host name; now the flag turns both checks off, as for every other caller. Cloudflare now honours `CURL_CA_BUNDLE`, the flag and `PROXY_ADDRESS`, and accepts no redirect.
- Since 1b: StopForumSpam (`pg_form_spam_ip_listed()`), the currency-rate fallback (`pg_currency_rates_http_get()`), the AI bot ranges (3 https redirects), `pg_sms_http()` (Netgsm, İYS), `_parasut_request()`, `_parasut_token_exchange()`, `erp_edoc_http()`. Timeouts, headers, bodies and error texts kept; they now also honour `PROXY_ADDRESS` and refuse http: a Paraşüt PDF address given as http:// fails as unreadable.
- Not moved: the CA store probe in `pg_ca_bundle_config_state()` asks whether the host's own store verifies, which the flag would hide; the package download (`includes/panel/software.php`), `download_assistant.php`, the payment files, the importers' redirect walk; the location database and S3 backups stream to or from a file, which the wrapper does not; time-stamp authorities are often plain http. Remaining work: `plans/code-audit-2026-10-followups.md` item 1.
