---
name: page-cache-gate
description: Anonymous page cache gate (PR #1382): PAGE_CACHE_TTL, which page answers a CDN or proxy may keep, the pg_nc marker, bypass reasons, the header callback slot
---

## What does it do?
- `includes/fn/page_cache.php`. `PAGE_CACHE_TTL` in `data/config.php` (Features, Page Cache; 30-3600 s, default 120) is the lifetime; missing or 0 = off, and then nothing changes. A config line, not a column: known before any query.
- `get_page.php` calls `pg_page_cache_apply()` just before `echo $content`. A cacheable answer gets `Cache-Control: public, max-age=0, s-maxage=<ttl>`, `Vary: Accept-Encoding`, no `Set-Cookie`/`Pragma`/`Expires`, `var software_token = ""`; a session this request started is destroyed at shutdown.
- While on, every page answer says `X-Pinegrap-Cache: cacheable` or `bypass`; `; reason=<reason>` only for staff (toolbar or role < 3): it tells what the page runs and the session holds.

## When is a page not cacheable (reason)?
- In this order (`pg_page_cache_decide()`): `off`, `method` (not GET/HEAD), `status` (not 200), `query` (any `?` in `REQUEST_URI`, or a `$_GET` key but `page`, which the router writes, so `/about?page=x` is no new CDN entry), `not_html` (PDF/RSS/iCal), `toolbar` (staff), `access` (not public), `mobile` (`MOBILE` on: no Vary on User-Agent, Cloudflare ignores Vary), `marker` (`pg_nc`), `consent` (`pg_consent`), `signed_in` (`software[auth]`), `session`, `set_cookie` (any cookie but the session's), `token` (the CSRF token still in the HTML: forms, CAPTCHA, cart/account widgets), `personal_<why>`.
- `session`: `pg_page_cache_session_anonymous()` allows only `software.{token, device_type, visitor_id, http_referer, tracking_code, affiliate_code, utm_*, cookie_id}`. Sign-in, order, currency, offer code, notices, guest, auto-dialog `visit_start_timestamp`, `software.urls` all bypass; a key added later bypasses too (fails closed, never leaks).
- `personal_*` comes from `pg_page_cache_personal($why)`: `php_region` (PHP regions, `custom_php` designer block), `dynamic_region`, `language_redirect` (`pg_lang_redirect_page()` on a page the first-visit redirect may answer for, set before the browser/staff/referer returns), `crawler` (`pg_consent_inject()` leaves the notice out for `IS_BOT`). A new visitor-dependent renderer must call it.

## Why is a consent choice a bypass but the banner cached?
- The no-choice HTML works for everyone: `pg_consent.js` reads the cookie on load and starts the held scripts.

## What is pg_nc?
- Lifetime and path of the session cookie (`session_get_cookie_params()`), HttpOnly, Lax, Secure on https; set while the session is not anonymous and cleared when it is again, at header-send time, so the POST/redirect that fills a cart or signs in already carries it. A CDN or proxy passes requests with it. Listed as necessary in `pg_consent_catalogue()` while the cache is on.

## What is the header callback trap?
- `header_register_callback()` keeps one callback; a second call silently replaces the first (`pg_tr_define_from_request()`, init.php ~870, registered one first). Use `pg_header_callback_add()`, never `header_register_callback()`.

## What is lost on a CDN hit?
- `init_tracking()`, record views, the perf log, the WAF (bans, country rule). Stale content up to the TTL; a purge reaches no CDN.
- Setting on, CDN or not: a new visitor's session is dropped on each cacheable page, so visitor rows use the 30-minute IP match; `INET_ATON()` is NULL (0) for IPv6, so cookieless IPv6 visitors in 30 minutes share one row. The help text does not say this yet.
- Sessions personal before the switch-on lack `pg_nc` until their next origin answer: a CDN may show them the anonymous copy once; nothing leaks.
- Fail-closed cost: `software.urls`, an empty `software.liveforms` or an auto dialog keep the session personal for the visit (a site with an auto dialog caches nothing); `/` with the language redirect never caches. Unchecked on a real session.

Related: page-file-cache.md, page-cache-proxy-rules.md, visitors/page-view-beacon.md
