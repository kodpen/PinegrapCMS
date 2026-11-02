---
name: tls-settings-in-panel-steps
description: Steps TP1-TP4 of plans/tls-settings-in-panel.md: scheme helper, Secure Mode offer, CA store chosen by the site, Workspace Claude address check; claim as [plan:tls-settings-in-panel] TP<n>
---

## Which run side by side?
- Wave 1: TP1, TP3 and TP4 at once, since they touch different files. Wave 2: TP2 after TP1. The last step to land moves the decisions to `platform/proxy-https-trust.md` and deletes both plan files.
- S is under 400 product lines, M about 1,000. Pure functions get `tests/<topic>_test.php` (red first); texts go in only through `tr_add.php`. Changelog: the version's `[FIX]` line.

## Which steps are there?
- TP1 (M) One scheme helper; Trusted Proxies decides. `free`
  - New `includes/fn/request_scheme.php`, loadable without `functions.php` (it requires `waf.php`, which is guarded and only defines). It holds two functions:
    - `pg_forwarded_https($server)`, pure: `X-Forwarded-Proto` (first value), `Forwarded` proto, `X-Forwarded-Ssl`, `Front-End-Https`, `X-ARR-SSL` (new), `CF-Visitor`, `X-Forwarded-Port` 443; any one is enough.
    - `pg_https_peer_trusted($remote, $trusted_raw)`: the constant, `waf_proxy_ranges()` or `waf_cloudflare_ranges()`.
  - Both are used by `check_if_request_is_secure()`, the copy in `get_file.php` and `check_ssl_status()` (System Status). `pg_request_is_https()` and `page_cache.php` use only the parser. The list counts whether or not the firewall is on.
  - Tests: the header matrix, the chain case, and the trust matrix (constant, listed, Cloudflare, unlisted, loopback not listed). Memory: `platform/proxy-https-trust.md` (`module: request_scheme`).

- TP2 (M) Secure Mode one-click offer. After TP1.
  - `includes/settings/firewall.save.php`: when Secure Mode is asked for, the request is untrusted and `pg_forwarded_https()` is true, the screen shows the peer and the entries to add. A second POST (with token) writes them through `waf_proxy_list_on_save()`, checks again, saves Secure Mode and calls `log_activity()`.
  - What is added depends on the peer (#5375: the peer's own address, never its network):
    - loopback: both `127.0.0.1` and `::1` (a local tunnel may use either);
    - private IPv4 or ULA: that one address (/32, /128); a proxy that moves within its network is listed by hand on Trusted Proxies;
    - public: never added with Secure Mode on. The screen says a CDN or load balancer needs all its published ranges listed first.
  - Texts that name `TRUST_PROXY_SSL_HEADERS` or the config file as the fix (`init.php`, `get_file.php`, `firewall.php`, `firewall.save.php`) point at Trusted Proxies instead. `test_secure_mode.php` still opens no database (`decisions-made-access.md`); it names the peer, its kind and the entries to add.

- TP3 (M) The site chooses the CA store. Done, #5711.
  - `pg_ca_bundle_effective()` (`includes/fn/update.php`) answers in this order:
    1. `CURL_CA_BUNDLE`, when set.
    2. The shipped `data/cacert.pem`, when PHP has no store (`curl.cainfo` unset and `openssl_get_cert_locations()` paths missing) or when the last probe found the server's store failing (`pg_ca_bundle_config_state()` `needed`, kept in `data/temp/ca_store.json`; the probe runs from System Status and once a day from the general job).
    3. Otherwise '' (the server's own store).
  - Every reader of `CURL_CA_BUNDLE` uses it: `pg_curl_tls()`, `api_http_request()`, and the stream `cafile` in `import_design.php`, `imap_client.php` and `output.php`. `da_curl_tls()` (`download_assistant.php`) uses only the offline rule.
  - `pg_ca_bundle_config_repair()` and its button are removed. The System Status CA row says which store is in use and why, and the TLS hint points there.
  - Tests: the chooser matrix.

- TP4 (S) Workspace Claude notices an address change. `free`
  - `ws_claude_bootstrap_prompt()` sends its Site API with the instructions call, and the version is bumped. The instructions name the current Site API. The settings card warns when the sent address differs from `ws_claude_api_base()`.
