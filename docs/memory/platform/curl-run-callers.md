---
name: curl-run-callers
description: Which outgoing calls run through pg_curl_run(), what moving each one changed (VERIFYHOST, proxy, http refused), and which stay on a raw handle and why.
---

## Which calls use pg_curl_run()?
- Both update-server version checks (`software_update_check.php`, `pg_update_server_version()`), the CA bundle download (`pg_ca_bundle_update()`), Cloudflare (`cf_request()`, `cf_graphql()` in `cloudflare.php`), the integrity reference (`pg_integrity_http_get()`) (PR #1884).
- What changed for them: the update checks set `VERIFYHOST 2` again after `pg_curl_tls()`, so with `ALLOW_INSECURE_UPDATE_TLS` on they still checked the host name; now the flag turns both checks off, as for every other caller. Cloudflare now honours `CURL_CA_BUNDLE`, the flag and `PROXY_ADDRESS`, and accepts no redirect.

## Which moved later?
- Since 1b: StopForumSpam (`pg_form_spam_ip_listed()`), the currency-rate fallback (`pg_currency_rates_http_get()`), the AI bot ranges (3 https redirects), `pg_sms_http()` (Netgsm, İYS), `_parasut_request()`, `_parasut_token_exchange()`, `erp_edoc_http()`. Timeouts, headers, bodies and error texts kept; they now also honour `PROXY_ADDRESS` and refuse http: a Paraşüt PDF address given as http:// fails as unreadable, and so does an İşbaşı API address typed as http:// ("Protocol http not supported").
- Since 1c (PR #1965): both licence checks (`license_check()` in `output.php`, dashboard widget 14) through `pg_license_request_options()`, and `pg_captcha_verify_request()`. Headers, bodies, timeouts and diagnostics kept. Widget 14 set `VERIFYHOST 2` after `pg_curl_tls()`: with `ALLOW_INSECURE_UPDATE_TLS` on it still checked the host name, now the flag turns both checks off. The status check followed any redirect, now at most 5, https only. CAPTCHA now honours `PROXY_ADDRESS`.
- Since 1e (#2022): `send_api_request()` in `migration.php` (six callers, all in that file) through `pg_migration_request_options()`: JSON body, Content-Type and Content-Length, 60 s, a fresh connection, `allow_http`. The body is still decoded whatever the status. Its `VERIFYHOST 2` after `pg_curl_tls()` is gone: with `ALLOW_INSECURE_UPDATE_TLS` on it still checked the host name, now the flag turns both checks off. Connect timeout is now 10 s.
- The design importer (a typed address) is on `api_http_request()` (1d): `import-design-fetch.md`.

## Which do not use it?
- Not moved: the CA store probe in `pg_ca_bundle_config_state()` (the flag would hide the host's store); the package download, `download_assistant.php`, the payment files; the location database and S3 backups (stream to or from a file); time-stamp authorities (often plain http). Remaining work: `plans/code-audit-2026-10-followups.md` item 1.

Related: docs/memory/platform/curl-run-wrapper.md, docs/memory/platform/update-tls-verification.md
