---
name: page-cache-gate
description: Anonymous page cache gate (PR #PRNUM): PAGE_CACHE_TTL, which page answers a CDN or proxy may keep, the pg_nc marker, bypass reasons, the header callback slot
---

## What does it do?
- `includes/fn/page_cache.php`. `PAGE_CACHE_TTL` in `data/config.php` (Features, Page Cache; 30-3600 s, default 120) is the lifetime; missing or 0 = off, and then nothing changes. A config line, not a column: known before any query.
- `get_page.php` calls `pg_page_cache_apply()` just before `echo $content`. A cacheable answer gets `Cache-Control: public, max-age=0, s-maxage=<ttl>`, `Vary: Accept-Encoding`, no `Set-Cookie`/`Pragma`/`Expires`, `var software_token = ""`; a session this request started is destroyed at shutdown.
- While on, every page answer says `X-Pinegrap-Cache: cacheable` or `bypass; reason=<reason>`.

## When is a page not cacheable (reason)?
- In this order (`pg_page_cache_decide()`): `off`, `method` (not GET/HEAD), `status` (not 200), `query` (any `$_GET` key but `page`: utm, short-link `t`, `?r=`), `not_html` (PDF/RSS/iCal), `toolbar` (staff), `access` (not public), `mobile` (`MOBILE` on: no Vary on User-Agent, Cloudflare ignores Vary), `marker` (`pg_nc`), `consent` (`pg_consent`), `signed_in` (`software[auth]`), `session`, `set_cookie` (any cookie but the session's), `token` (the CSRF token still in the HTML: forms, CAPTCHA, cart/account widgets), `personal_<why>`.
- `session`: `pg_page_cache_session_anonymous()` allows only `software.{token, device_type, visitor_id, http_referer, tracking_code, affiliate_code, utm_*, cookie_id}`. Sign-in, order, currency, offer code, notices, guest, auto-dialog `visit_start_timestamp`, `software.urls` all bypass; a key added later bypasses too (fails closed, never leaks).
- `personal_*` comes from `pg_page_cache_personal($why)`: `php_region` (PHP regions, `custom_php` designer block), `dynamic_region`, `language_redirect` (`pg_lang_redirect_page()` on a page the first-visit redirect may answer for, set before the browser/staff/referer returns), `crawler` (`pg_consent_inject()` leaves the notice out for `IS_BOT`). A new visitor-dependent renderer must call it.

## Why is a consent choice a bypass but the banner cached?
- The no-choice HTML works for everyone: `pg_consent.js` reads the cookie on load and starts the held scripts. An answer drawn for a visitor who chose is not stored.

## What is pg_nc?
- A session cookie (HttpOnly, Lax, Secure on https), set while the session is not anonymous and cleared when it is again, at header-send time, so the POST/redirect that fills a cart or signs in already carries it. A CDN or proxy passes requests with it. Listed as necessary in `pg_consent_catalogue()` while the cache is on.

## What is the header callback trap?
- `header_register_callback()` keeps one callback; a second call silently replaces the first. `pg_tr_define_from_request()` (init.php ~870) registered its redirect hook before the session started. Both now go through `pg_header_callback_add()`; never call `header_register_callback()` directly.

## What is lost on a CDN hit?
- `init_tracking()`, record view counts, the perf log, the WAF (bans, country rule). Stale content lives up to the TTL; no purge yet.
- A visitor who reads only cacheable pages keeps no session, so visitor rows fall back to the 30-minute IP match.

Related: platform/page-cache-proxy-rules.md, plans/anonymous-page-cache.md
