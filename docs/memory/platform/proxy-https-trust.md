---
name: proxy-https-trust
description: HTTPS behind a proxy: pg_forwarded_https() parses the headers, pg_https_peer_trusted() says whose word counts (Trusted Proxies, Cloudflare, the constant); who asks which.
module: request_scheme
---

## Where is the scheme decided?
- `includes/fn/request_scheme.php`, loaded by `functions.php` and on its own by `get_file.php`, `router.php`, the router's page cache module and `prototype.php` (it also holds the request's address, `platform/x-rewrite-url-iis-only.md`). The peer check requires `waf.php` (guarded, only defines) when it is asked and the firewall is not loaded yet; it is not loaded at the top, so `functions.php` alone still loads no firewall (`tests/log_activity_ip_test.php` counts on that). Two functions:
  - `pg_forwarded_https($server)`, pure: does a proxy in front say https? `X-Forwarded-Proto` first value, `Forwarded` proto, `X-Forwarded-Ssl` on, `Front-End-Https` on, `X-ARR-SSL` any value (IIS ARR sets it only on an https front connection), `CF-Visitor` scheme https, `X-Forwarded-Port` 443. One is enough: an inner proxy may rewrite `X-Forwarded-Proto` to http while `CF-Visitor` keeps https (Cloudflare Flexible to nginx on port 80).
  - `pg_https_peer_trusted($remote, $trusted_raw, $override = null)`: `TRUST_PROXY_SSL_HEADERS` true (any peer), else the peer is in Settings > Firewall > Trusted Proxies (`waf_proxy_ranges()`, read whether or not the firewall is on) or a Cloudflare edge address (`waf_cloudflare_ranges()`). `$override` stands in for the constant in tests.
- `pg_https_peer_offer($remote)`, pure: what the Firewall screen offers to list for an untrusted peer that claims https: a loopback peer gets `127.0.0.1` and `::1` (a local tunnel may use either family), a private or ULA peer its one address (never its network), a public (`public`) or link-local/reserved (`other`) peer nothing. An IPv4 peer handed over as `::ffff:a.b.c.d` is offered as `a.b.c.d`.
- Direct TLS (`HTTPS` not off, `SERVER_PORT` 443) is checked by each caller before the headers; it is set by the web server, never the client.

## Who asks both, who only the parser?
- Both, through `check_if_request_is_secure()` (`core.php`; its copy in `get_file.php` is the same two calls): Secure Mode in `init.php` and `get_file.php`, the HSTS header, the Secure Mode guard of `firewall.save.php`, `check_ssl_status()` (System Status). A forged claim from an unknown peer must not redirect the site or pass the guard.
- Only the parser: `pg_request_is_https()` (`auth.php`: cookie Secure flags) and `pg_page_cache_request_scheme()` (the cache key). A false https claim affects only the request that made it (a Secure cookie its browser drops, a cache copy for itself), and a true claim from a proxy not yet listed must still give a cookie that sticks.
- `check_proxy_ssl_headers()` (`core.php`) is the parser on `$_SERVER`: the guard's diagnostics tell "a proxy says https but is not trusted" from "nothing says https" with it.

## Why not trust loopback or private peers by themselves?
- Shared hosts and container networks share those addresses; a local tunnel (`cloudflared`, a TLS proxy in front of `docker/`) is listed on Trusted Proxies by the operator (owner, 2026-11-02, #5375). `TRUST_PROXY_SSL_HEADERS` stays a file-level override for an origin only the proxy can reach (`workflow/decisions-made-access.md`).
- The installer keeps its own `X-Forwarded-Proto` read: its scheme only builds the install's own links, and the stored `url_scheme` starts as `http://` (the shipped templates' config row). No schema change came with it (TP1 #5714, TP2 #5831, TP3 #5711, TP4 #5829).

Related: docs/memory/platform/secure-mode-proxy-offer.md, docs/memory/waf/waf-secure-mode-enable-guard.md, docs/memory/waf/waf-security-headers-hsts.md, docs/memory/platform/page-file-cache.md
