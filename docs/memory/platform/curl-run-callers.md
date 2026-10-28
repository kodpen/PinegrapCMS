---
name: curl-run-callers
description: Which outgoing calls use pg_curl_run(), what moving each changed (VERIFYHOST, proxy, http refused, TLS settings), which stay raw and why.
---

## Which calls use pg_curl_run()?
- Both update-server version checks (`software_update_check.php`, `pg_update_server_version()`), the CA bundle download (`pg_ca_bundle_update()`), Cloudflare (`cf_request()`, `cf_graphql()` in `cloudflare.php`), the integrity reference (`pg_integrity_http_get()`) (PR #1884).
- What changed: the update checks set `VERIFYHOST 2` again after `pg_curl_tls()`; now `ALLOW_INSECURE_UPDATE_TLS` turns both checks off, as for every caller. Cloudflare now honours `CURL_CA_BUNDLE`, the flag and `PROXY_ADDRESS`, and accepts no redirect.

## Which moved later?
- Since 1b: StopForumSpam (`pg_form_spam_ip_listed()`), the currency-rate fallback (`pg_currency_rates_http_get()`), the AI bot ranges (3 https redirects), `pg_sms_http()` (Netgsm, İYS), `_parasut_request()`, `_parasut_token_exchange()`, `erp_edoc_http()`. Timeouts, headers, bodies, error texts kept; now honour `PROXY_ADDRESS` and refuse http (an http:// Paraşüt PDF or İşbaşı API address fails).
- Since 1c (PR #1965): both licence checks (`license_check()` in `output.php`, dashboard widget 14) through `pg_license_request_options()`, and `pg_captcha_verify_request()`. Widget 14's `VERIFYHOST 2` after `pg_curl_tls()` is gone (the flag now turns both checks off); the status check follows at most 5 https redirects instead of any. CAPTCHA now honours `PROXY_ADDRESS`.
- Since 1e (#2022): `send_api_request()` in `migration.php` (six callers, all in that file) through `pg_migration_request_options()`: JSON body, Content-Type and Content-Length, 60 s, a fresh connection, `allow_http`. Body decoded whatever the status; `VERIFYHOST 2` gone, as for widget 14; connect 10 s.
- Since 1f (#2074): the package download step of `pg_panel_software_update()` (`includes/panel/software.php`; 120 s, connect 15 s; status, truncation and zip checks read `status`, `content_length`, `body`), `mailchimp_request()` (30/30 s, basic auth, HTTP/1.0 against error 52; no answer is now logged as a cURL error) and the IndexNow ping in `update_sitemap_and_ping()` (10/8 s; any HTTP answer still logs as sent). All three now honour `CURL_CA_BUNDLE` and `ALLOW_INSECURE_UPDATE_TLS`; Mailchimp and IndexNow also `PROXY_ADDRESS`.
- Since 1g (#2100): the carrier APIs (`pg_carrier_api_http()`; 10/10 s, compression, fresh connection, unannounced-gzip fallback, `pg_curl_tls_hint()` message), Pinegrap AI (`ws_ai_http()`; connect 8 s, `max(5, timeout)`, `headers`/`body`/`json` answer via `response_headers`), the Claude routine fire (`ws_claude_fire()`; 5/10 s, Retry-After read from the header block). All now honour `PROXY_ADDRESS` and `ALLOW_INSECURE_UPDATE_TLS` (AI and Claude forced `VERIFYHOST 2` before); behind a proxy the header block holds the tunnel answer too. Their hosts are all https.
- Since 1h (#2124): Givex (`send_givex_request()`; 15 s, connect 10 s, secondary host after a cURL error). Now sends the User-Agent, refuses http.
- Since 1i (#2743): the Stripe driver (`pg_payment_stripe_request_options()`; basic auth, 60 s, connect 15 s, an empty POST still a POST). Now sends the User-Agent, honours `PROXY_ADDRESS`, refuses http. PayPal Checkout was on it already.
- The design importer (a typed address) is on `api_http_request()` (1d): `import-design-fetch.md`.

## Which do not use it?
- Not moved: the CA store probe in `pg_ca_bundle_config_state()` (the flag would hide the host's store); `url_exists()` (`core.php`, no caller); the location database and S3 backups (stream to or from a file); time-stamp authorities (often plain http).
- Payments stay raw: `submit_order.php`'s 17 calls, 16 of them the six gateways frozen by `plans/payment-driver-layer.md` (2026-10-19), 1 classic PayPal Express DoExpressCheckoutPayment (kept, not frozen); `recurring_payment_job.php`'s 1 (Payments Pro).

Related: docs/memory/platform/curl-run-wrapper.md, docs/memory/platform/update-tls-verification.md
