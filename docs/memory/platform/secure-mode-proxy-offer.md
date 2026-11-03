---
name: secure-mode-proxy-offer
description: Turning Secure Mode on through a proxy not yet trusted: the Add this proxy to Trusted Proxies box, what the save lists and checks, the log line, what test_secure_mode.php reports.
module: request_scheme
---

## How is Secure Mode turned on through an untrusted proxy?
- The HTTPS card (`includes/settings/firewall.php`) draws, while Secure Mode is off and this request's headers claim https from an untrusted peer, the box `secure_mode_trust_proxy` ("Add this proxy to Trusted Proxies") naming the peer and the entries; a public peer gets a sentence instead (a CDN or load balancer is listed with all its published ranges by hand, Cloudflare is recognised on its own). Nothing is drawn before the `waf_trusted_proxies` column exists.
- `firewall.save.php`, on the OFF -> ON transition from a request `check_if_request_is_secure()` refuses: with the box ticked it builds the list as it will be stored (`waf_proxy_list_on_save()` over the posted list plus the offered entries), asks `pg_https_peer_trusted()` against it, and only then goes on; the entries ride in `$waf_post_proxies` (an added entry reads X-Forwarded-For only, as any new entry) and `log_activity()` names the peer and the entries. Without the box, or for a public or other peer, the refusal names the peer and the entries, and nothing is written (`waf/waf-secure-mode-enable-guard.md`).
- `test_secure_mode.php` loads `request_scheme.php` (no `init.php`, no database: `workflow/decisions-made-access.md`): it names the peer, its kind, the entries the screen would offer, and whether the request counts as secure now (direct TLS, the constant, a Cloudflare edge) or once the proxy is listed; the stored list is not read, so it never says "listed already". Tests: `tests/secure_mode_offer_test.php`.

## Which texts name the fix?
- Texts that used to name `TRUST_PROXY_SSL_HEADERS` or `data/config.php` as the fix (`init.php`, `get_file.php`, `firewall.php`, `firewall.save.php`) point at Settings > Firewall > Trusted Proxies (#5831). No screen names `TRUST_PROXY_SSL_HEADERS`, `CURL_CA_BUNDLE`, `REQUIRE_SECURE_MODE` or `ALLOW_INSECURE_UPDATE_TLS` as the fix: they stay file-level overrides (owner, 2026-11-02, #5375; `workflow/decisions-made-product-19.md`).
- A public peer is never added with Secure Mode on: a CDN or load balancer moves between addresses, so the screen asks for all its published ranges on Trusted Proxies first.

Related: docs/memory/platform/proxy-https-trust.md, docs/memory/waf/waf-secure-mode-enable-guard.md
