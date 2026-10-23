---
name: page-cache-proxy-rules
description: Cache rules for a CDN or reverse proxy in front of Pinegrap (Cloudflare, nginx, Varnish) that follow the page cache gate: what to pass, what to keep, and what a hit skips
---

## What must every proxy rule do?
- Cache only GET/HEAD, never `/<SOFTWARE_DIRECTORY>/` (panel, endpoints, `get_file.php` has its own headers).
- Pass (never serve from cache) a request carrying a `pg_nc` or `software[auth]` cookie (`software%5Bauth%5D` when encoded).
- Take the lifetime from the origin: `s-maxage` for the proxy, `max-age=0` for the browser. Never ignore `Cache-Control` or `Set-Cookie`: a non-cacheable answer is `no-store` and may set a session cookie.
- Never strip the request's cookies: the gate decides per request at the origin, so a miss must reach it with them (`pg_language`, the session, visit counters, the form's session). The cache key stays host + path.
- Check with `curl -sI`: `X-Pinegrap-Cache: cacheable` or `bypass` (the reason only for staff, `page-cache-gate.md`).

## Cloudflare?
- Cache Rule: when method is GET or HEAD, URI path does not start with `/<SOFTWARE_DIRECTORY>/`, and the Cookie header contains neither `pg_nc=` nor `software[auth]` / `software%5Bauth%5D`: Eligible for cache; Edge TTL "Use cache-control header if present"; Browser TTL "Respect origin".
- HTML is not cached by default, so the rule is needed. Cloudflare ignores `Vary` on HTML; that is why the gate refuses mobile and consent variants rather than varying.
- A hit skips the Pinegrap WAF, bans and country rule: repeat them in Cloudflare's WAF if needed.

## nginx?
- `proxy_cache_path ... keys_zone=pg:10m;` and in the site's location: `proxy_cache pg; proxy_cache_key "$scheme$host$request_uri"; proxy_cache_methods GET HEAD;`
- `map $http_cookie $pg_skip { default 0; ~pg_nc= 1; ~software(\[|%5B)auth 1; }` then `proxy_cache_bypass $pg_skip; proxy_no_cache $pg_skip;` and the same for the software directory location (or no `proxy_cache` there).
- Keep `Cache-Control` honoured: never `proxy_ignore_headers Cache-Control Set-Cookie`, which would store a per-visitor page (a form's token) for everyone. Check that the nginx in use keeps a page on `s-maxage` with `max-age=0` (`$upstream_cache_status` HIT on the second request); if it does not, cache nothing rather than ignore the header.
- FastCGI cache: the same map with `fastcgi_cache_bypass` / `fastcgi_no_cache`.

## Varnish?
- `vcl_recv`: `if (req.method != "GET" && req.method != "HEAD") { return (pass); }`, pass `req.url ~ "^/<SOFTWARE_DIRECTORY>/"` and `req.http.Cookie ~ "(pg_nc=|software(\[|%5B)auth)"`; otherwise `return (hash);`, which skips the builtin `vcl_recv` (it passes every request with a cookie).
- Never `unset req.http.Cookie`: a miss would reach the origin without the visitor's cookies, so a no-store page (the language redirect on `/`, a form) loses `pg_language` and the session and visit counters restart on every view (PR #1382).
- The builtin `vcl_backend_response` honours `s-maxage` and does not cache an answer with `Set-Cookie` or `no-store`; keep it.

## What does a hit skip?
- Visitor tracking, record view counts and the performance log until the visitor beacon step lands; stale pages up to the lifetime (no purge yet).
