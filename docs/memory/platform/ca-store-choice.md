---
name: ca-store-choice
description: pg_ca_bundle_effective() chooses the CA store: the CURL_CA_BUNDLE override, the shipped data/cacert.pem when PHP has no store or the probe failed, else the server's store; the probe record.
module: update
---

## Who chooses the store, and in which order?
- Owner (2026-11-02, #5375; TP3 #5711): no TLS setting needs a hand edit of `data/config.php`; the site chooses, there is no setting. `pg_ca_bundle_effective()` (`includes/fn/update.php`) answers:
  1. the `CURL_CA_BUNDLE` override, when it names an existing file (a missing file is ignored, and the System Status row says so);
  2. the shipped `data/cacert.pem`, when PHP has no store of its own (`curl.cainfo` unset and every `openssl_get_cert_locations()` file or directory missing: `pg_ca_bundle_php_store_present()`, no OpenSSL extension counts as a store) or when the last probe found the server's store failing;
  3. `''`, the server's own store; also when the shipped file is missing.
- `pg_ca_bundle_choose($override, $shipped, $php_store, $probe_state)` is the pure chooser (`tests/ca_store_test.php`); `pg_ca_bundle_choice()` adds `override_missing`, `probe` and `shipped` for the screens.
- Every reader goes through it: `pg_curl_tls()`, `api_http_request()`, the stream `cafile` in `import_design.php`, `imap_client.php` and the licence fallback of `output.php`. `da_curl_tls()` (`download_assistant.php`) uses the offline half only. The installer still writes the override on a host whose store fails at install time, so the first request verifies before any probe ran.

## How does the probe work?
- `pg_ca_bundle_probe_run()`: one HEAD request to the bundle source (`pg_ca_bundle_source_url()`), five-second limit, verified against the store cURL finds on its own (no `CAINFO`, never `ALLOW_INSECURE_UPDATE_TLS`: both would hide a failing store), `PROXY_ADDRESS` honoured. cURL errors 35, 51, 60, 77 mean `needed`; 0 `ok`; anything else (no DNS, no route, a timeout) `unknown` (`pg_ca_bundle_probe_state_of()`).
- The record `state`, `checked_at`, `error` is `data/temp/ca_store.json`, written through a temporary file; `pg_ca_bundle_probe_decode()` reads its text, `pg_ca_bundle_probe_read()` once per request. An outbound call reads two files at most and never probes.
- It runs from System Status (`pg_ca_bundle_config_state()`, unless the override is in use; `system-status-ca-bundle.md`) and from the general job: `pg_ca_bundle_probe_daily()` in `job.php`, once a day, again after an hour when the last probe reached nothing (`pg_ca_bundle_probe_due()`), never with the override set. A store that breaks or heals is therefore followed without anyone opening the dashboard.

## What was removed?
- `pg_ca_bundle_config_repair()`, the panel action `ca_bundle_config_repair`, the widget's Fix button and its JavaScript: the old row asked the operator to point `CURL_CA_BUNDLE` at the shipped file. The TLS hint (`pg_curl_tls_hint_text()`) and the Workspace AI and Claude certificate texts now point at the CA store row of System Status.

Related: docs/memory/platform/update-tls-verification.md, docs/memory/platform/system-status-ca-bundle.md, docs/memory/platform/ca-bundle-panel-update.md
