---
name: tls-settings-in-panel
description: Plan (owner 2026-11-02, #5375): HTTPS behind any proxy trusted through Trusted Proxies with a Secure Mode offer, the CA store chosen by the site; no TLS edit of data/config.php; steps TP1-TP4
---

## What is wrong now?
- Behind anything that ends TLS before PHP (a CDN, reverse proxy, load balancer, ingress, tunnel, or a TLS proxy in front of `docker/`), PHP sees plain http. Forwarded headers count only with `TRUST_PROXY_SSL_HEADERS` written into `data/config.php`. Without it Secure Mode is refused, `url_scheme` stays `http://`, and every address the site hands out (integration, OAuth, webhooks, mails, sitemap, pasted prompts) is http.
- The scheme is decided in six places with different rules: `check_if_request_is_secure()` (`includes/fn/core.php`), its copy in `get_file.php`, `includes/fn/page_cache.php`, `pg_request_is_https()` (`includes/fn/auth.php`), `includes/fn/system_status.php`, `install/index.php`. IIS ARR's `X-ARR-SSL` is read nowhere.
- Outgoing TLS: a host whose PHP finds no CA store is told to write `CURL_CA_BUNDLE` into `data/config.php`, and System Status offers a button that rewrites the file (`pg_ca_bundle_config_repair()`).

## What is decided?
- Owner (2026-11-02): no TLS setting needs a hand edit of `data/config.php` (`workflow/decisions-made-product-19.md`). Options A and B of #5375.
- A: forwarded HTTPS headers count when the peer (`REMOTE_ADDR`) is in Settings › Firewall › Trusted Proxies or is a Cloudflare edge address. The WAF already recognises Cloudflare automatically for the visitor's IP, so Cloudflare is recognised but never required. `TRUST_PROXY_SSL_HEADERS` keeps working as an override.
- B: when Secure Mode is turned on through a proxy that says https but is not trusted, the Firewall screen offers to add that proxy and turn Secure Mode on in one step.
- One recognised header saying https is enough, as today. An inner proxy may rewrite `X-Forwarded-Proto` to http while `CF-Visitor` keeps https (Cloudflare Flexible to nginx on port 80), and that request must stay https. A false https claim affects only the request that made it: no redirect for it, and a Secure cookie its browser drops.
- `pg_request_is_https()` and the page cache key keep reading the claim without the peer check, for the reasons their comments give. They share only the header parsing.
- The site chooses the CA store; there is no setting. `CURL_CA_BUNDLE`, `REQUIRE_SECURE_MODE` and `ALLOW_INSECURE_UPDATE_TLS` stay file-level overrides (`workflow/decisions-made-access.md`). No screen names them as the fix.
- No schema change. The installer is unchanged: its scheme only builds the install's own links, and the stored `url_scheme` starts as `http://`, the value in the shipped templates' config row.
- Rejected: trusting every loopback or private peer automatically, because shared hosts and container networks share those addresses.

## Where does it start?
- Read the `platform/README.md` and `waf/README.md` key rules, `platform/update-tls-verification.md`, `platform/ca-bundle-panel-update.md`, `platform/system-status-ca-bundle.md` and `workflow/decisions-made-access.md`. Steps: `tls-settings-in-panel-steps.md`.
